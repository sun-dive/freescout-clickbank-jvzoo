<?php

namespace Modules\ClickBankJVZoo\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\ClickBankJVZoo\Misc\Codes;
use Modules\ClickBankJVZoo\Misc\Signed;
use Modules\ClickBankJVZoo\Providers\ClickBankJVZooServiceProvider as Module;

/**
 * One thing the member site is to be told: a used code's order changed status (refunded, charged back, canceled, or
 * paid again). Sent signed, {"code", "member", "status", "time"}, to the member site's address; kept and tried again,
 * waiting longer each time up to an hour, until the member site answers in the 200s.
 */
class Notice extends Model
{
    protected $table = 'clickbankjvzoo_notices';

    protected $guarded = ['id'];

    protected $dates = ['next_attempt_at', 'sent_at'];

    // How a notice is posted: replaced in tests; it answers the HTTP status.
    public static $transport = null;

    /**
     * A notice for an order whose code a member has used.
     */
    public static function queue(Order $order)
    {
        return self::create([
            'order_id' => $order->id,
            'code'     => $order->code,
            'member'   => $order->redeemed_by,
            'status'   => $order->status,
        ]);
    }

    /**
     * Sends the notices that are due, oldest first. One order's notices go in the order they were made: a later one
     * waits while an earlier one is unsent. Returns how many reached the member site.
     */
    public static function sendDue()
    {
        $url = Module::memberUrl();
        if ($url === '') {
            // nowhere to send yet: they wait
            return 0;
        }
        $sent = 0;
        $due = self::whereNull('sent_at')->where(function ($q) {
            $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
        })->orderBy('id')->get();
        foreach ($due as $notice) {
            if (self::where('order_id', $notice->order_id)->whereNull('sent_at')->where('id', '<', $notice->id)->exists()) {
                continue;
            }
            $sent += $notice->attempt($url) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * One try at telling the member site. The time is the time of this try, as the member site refuses a message
     * more than five minutes from its clock.
     */
    public function attempt($url)
    {
        $body = json_encode(['code' => Codes::format($this->code), 'member' => $this->member, 'status' => $this->status, 'time' => time()]);
        $headers = ['Content-Type' => 'application/json', Signed::HEADER => Signed::sign($body, Module::ownKeys()['secret'])];
        try {
            $status = self::$transport ? call_user_func(self::$transport, $url, $body, $headers) : self::post($url, $body, $headers);
            $error = ($status >= 200 && $status < 300) ? null : 'HTTP '.$status;
        } catch (\Exception $e) {
            $error = mb_substr($e->getMessage(), 0, 255);
        }
        $this->attempts++;
        if ($error === null) {
            $this->sent_at = now();
            $this->last_error = null;
        } else {
            // 1, 2, 4 ... minutes, then hourly
            $this->next_attempt_at = now()->addMinutes(min(60, 2 ** ($this->attempts - 1)));
            $this->last_error = $error;
            \Log::warning('[Member site notice] not delivered, will try again: '.$error);
        }
        $this->save();

        return $error === null;
    }

    protected static function post($url, $body, $headers)
    {
        $client = new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5]);

        return $client->post($url, ['body' => $body, 'headers' => $headers, 'http_errors' => false])->getStatusCode();
    }
}
