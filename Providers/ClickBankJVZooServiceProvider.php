<?php

namespace Modules\ClickBankJVZoo\Providers;

use Illuminate\Support\ServiceProvider;

class ClickBankJVZooServiceProvider extends ServiceProvider
{
    // The settings page: Settings » ClickBank & JVZoo.
    const SECTION = 'clickbankjvzoo';

    // Each seller's secret key, kept in FreeScout's options encrypted with the app's key, never in this code.
    const SECRETS = [
        'clickbank' => 'clickbankjvzoo_clickbank_secret',
        'jvzoo'     => 'clickbankjvzoo_jvzoo_secret',
    ];

    // The member site: its public key, which checks its requests to use a code, and the address that is told when a
    // code's order is refunded, charged back or canceled.
    const MEMBER = [
        'public' => 'clickbankjvzoo_member_public',
        'url'    => 'clickbankjvzoo_member_url',
    ];

    // This help desk's own key pair, made on first use: the secret encrypted, like the sellers' keys.
    const OWN = [
        'secret' => 'clickbankjvzoo_own_secret',
        'public' => 'clickbankjvzoo_own_public',
    ];

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'clickbankjvzoo');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->hooks();
    }

    public function register()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../Resources/lang');
        $this->commands([\Modules\ClickBankJVZoo\Console\SendNotices::class]);
    }

    /**
     * A seller's secret key, or '' when it is not set. Read from the database each time: FreeScout's option cache lives
     * as long as the process, so a key changed in the settings would otherwise be missed by one that runs on.
     */
    public static function secret($seller)
    {
        $value = \Option::get(self::SECRETS[$seller], '', true, false);
        if ($value === '' || $value === null) {
            return '';
        }
        try {
            return (string)decrypt($value);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * The member site's public key, or '' when it is not set.
     */
    public static function memberKey()
    {
        return trim((string)\Option::get(self::MEMBER['public'], '', true, false));
    }

    public static function memberUrl()
    {
        return trim((string)\Option::get(self::MEMBER['url'], '', true, false));
    }

    /**
     * This help desk's key pair, ['secret' => ..., 'public' => ...], base64; made and kept the first time it is needed.
     */
    public static function ownKeys()
    {
        $secret = \Option::get(self::OWN['secret'], '', true, false);
        $public = \Option::get(self::OWN['public'], '', true, false);
        if ($secret !== '' && $public !== '') {
            try {
                return ['secret' => (string)decrypt($secret), 'public' => (string)$public];
            } catch (\Exception $e) {
                // the app's key changed since: a new pair, whose public key the member site must be given again
            }
        }
        list($secret, $public) = \Modules\ClickBankJVZoo\Misc\Signed::keypair();
        \Option::set(self::OWN['secret'], encrypt($secret));
        \Option::set(self::OWN['public'], $public);

        return ['secret' => $secret, 'public' => $public];
    }

    public function hooks()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[self::SECTION] = ['title' => __('ClickBank & JVZoo'), 'icon' => 'shopping-cart', 'order' => 600];

            return $sections;
        }, 30);

        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section != self::SECTION) {
                return $settings;
            }
            // a saved key is shown as one star per character of the key (not of its encrypted form), and never sent
            foreach (self::SECRETS as $seller => $option) {
                $settings[$option] = str_repeat('*', mb_strlen(self::secret($seller)));
            }
            $settings[self::MEMBER['public']] = self::memberKey();
            $settings[self::MEMBER['url']] = self::memberUrl();

            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section != self::SECTION) {
                return $params;
            }

            return [
                // the addresses to give each seller, and what to give the member site
                'template_vars' => [
                    'notice_urls' => [
                        'clickbank' => route('clickbankjvzoo.clickbank'),
                        'jvzoo'     => route('clickbankjvzoo.jvzoo'),
                    ],
                    'redeem_url' => route('clickbankjvzoo.redeem'),
                    'own_public' => self::ownKeys()['public'],
                ],
                // shown as stars, never sent back to the page; saved encrypted
                'settings' => array_fill_keys(array_values(self::SECRETS), ['safe_password' => true, 'encrypt' => true]),
                'validator_rules' => [
                    'settings.'.self::SECRETS['clickbank'] => [new \Modules\ClickBankJVZoo\Misc\SecretKeyRule('clickbank')],
                    'settings.'.self::SECRETS['jvzoo']     => [new \Modules\ClickBankJVZoo\Misc\SecretKeyRule('jvzoo')],
                    // an Ed25519 public key in base64: 32 bytes
                    'settings.'.self::MEMBER['public']     => [new \Modules\ClickBankJVZoo\Misc\MatchRule('/^[A-Za-z0-9+\/]{43}=$/',
                        __("The member site's public key is 44 characters of base64, ending in =."))],
                    'settings.'.self::MEMBER['url']        => [new \Modules\ClickBankJVZoo\Misc\MatchRule('/^https:\/\/[^\s"<>]+$/',
                        __('The address starts with https:// and has no spaces.'))],
                ],
            ];
        }, 20, 2);

        // the member site's notices go out from FreeScout's scheduler, every minute; a run cut short frees its lock
        // after five minutes
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->command('clickbankjvzoo:send-notices')->everyMinute()->withoutOverlapping(5);

            return $schedule;
        });

        // beside each ticket, above Previous Conversations: the customer's orders, read-only
        \Eventy::addAction('conversation.before_prev_convs', function ($customer, $conversation, $mailbox) {
            try {
                $orders = \Modules\ClickBankJVZoo\Entities\Order::forEmails($customer->emails->pluck('email')->all());
                if (count($orders)) {
                    echo view('clickbankjvzoo::orders_sidebar', ['orders' => $orders])->render();
                }
            } catch (\Throwable $e) {
                // a fault here must never break the ticket page
                \Log::error('[ClickBank & JVZoo] orders beside the ticket: '.$e->getMessage());
            }
        }, 20, 3);

        // spaces around a pasted key are dropped before it is saved
        \Eventy::addFilter('settings.before_save', function ($request, $section) {
            if ($section != self::SECTION || !is_array($request->settings)) {
                return $request;
            }
            $values = $request->settings;
            foreach (array_merge(array_values(self::SECRETS), array_values(self::MEMBER)) as $option) {
                if (isset($values[$option]) && is_string($values[$option])) {
                    $values[$option] = trim($values[$option]);
                }
            }
            $request->merge(['settings' => $values]);

            return $request;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section == self::SECTION ? 'clickbankjvzoo::settings' : $view;
        }, 20, 2);
    }
}
