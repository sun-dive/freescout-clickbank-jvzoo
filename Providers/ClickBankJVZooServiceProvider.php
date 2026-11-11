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

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'clickbankjvzoo');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->hooks();
    }

    public function register()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../Resources/lang');
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
            foreach (self::SECRETS as $option) {
                $settings[$option] = \Option::get($option, '');
            }

            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section != self::SECTION) {
                return $params;
            }

            return [
                // the addresses to give each seller
                'template_vars' => [
                    'notice_urls' => [
                        'clickbank' => route('clickbankjvzoo.clickbank'),
                        'jvzoo'     => route('clickbankjvzoo.jvzoo'),
                    ],
                ],
                // shown as stars, never sent back to the page; saved encrypted
                'settings' => array_fill_keys(array_values(self::SECRETS), ['safe_password' => true, 'encrypt' => true]),
                'validator_rules' => [
                    // ClickBank's secret key is up to 16 capitals and digits; stars are the saved key, unchanged
                    'settings.'.self::SECRETS['clickbank'] => ['nullable', 'regex:/^(\*+|[A-Z0-9]{1,16})$/'],
                    'settings.'.self::SECRETS['jvzoo']     => ['nullable', 'max:255'],
                ],
            ];
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section == self::SECTION ? 'clickbankjvzoo::settings' : $view;
        }, 20, 2);
    }
}
