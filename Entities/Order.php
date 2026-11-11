<?php

namespace Modules\ClickBankJVZoo\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\ClickBankJVZoo\Misc\Codes;

class Order extends Model
{
    protected $table = 'clickbankjvzoo_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'variables' => 'array',
        'test'      => 'boolean',
    ];

    protected $dates = ['bought_at', 'accessed_at', 'redeemed_at'];

    // The status each kept event leaves an order in.
    const STATUS = [
        'SALE'            => 'paid',
        'BILL'            => 'paid',
        'UNCANCEL-REBILL' => 'paid',
        'RFND'            => 'refunded',
        'CGBK'            => 'charged back',
        'INSF'            => 'charged back',
        'CANCEL-REBILL'   => 'canceled',
    ];

    // How each status is marked beside a ticket (FreeScout's label colours).
    const STATUS_CLASS = [
        'paid'         => 'success',
        'canceled'     => 'default',
        'refunded'     => 'warning',
        'charged back' => 'danger',
    ];

    const SOURCES = ['clickbank' => 'ClickBank', 'jvzoo' => 'JVZoo'];

    /**
     * A code no order has yet.
     */
    public static function newCode()
    {
        do {
            $code = Codes::generate();
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * A member site asks to use a code for one of its members: ['ok' => true, 'order' => ...] if the code is real, its
     * order is paid, and no other member has used it; ['ok' => false, 'reason' => ...] if not ("unknown", "used", or
     * the order's status). The first use marks the code as that member's; every use counts as an access.
     */
    public static function redeem($typed, $member)
    {
        $code = Codes::normalize($typed);
        $member = trim((string)$member);
        if ($code === '' || $member === '' || mb_strlen($member) > 64) {
            return ['ok' => false, 'reason' => 'unknown'];
        }

        return \DB::transaction(function () use ($code, $member) {
            $order = self::where('code', $code)->lockForUpdate()->first();
            if (!$order) {
                return ['ok' => false, 'reason' => 'unknown'];
            }
            if ($order->status != 'paid') {
                return ['ok' => false, 'reason' => $order->status, 'order' => $order];
            }
            if ($order->redeemed_by !== null && $order->redeemed_by !== $member) {
                return ['ok' => false, 'reason' => 'used', 'order' => $order];
            }
            if ($order->redeemed_by === null) {
                $order->redeemed_by = $member;
                $order->redeemed_at = now();
            }
            $order->accessed_at = $order->accessed_at ?: now();
            $order->accesses++;
            $order->save();

            return ['ok' => true, 'order' => $order];
        });
    }

    /**
     * A customer's orders, newest first: those bought with any of their email addresses.
     */
    public static function forEmails($emails)
    {
        $emails = array_values(array_unique(array_map(function ($e) {
            return strtolower(trim((string)$e));
        }, (array)$emails)));

        return self::whereIn('email', $emails)->orderBy('bought_at', 'desc')->orderBy('id', 'desc')->get();
    }

    /**
     * Keeps one event from a seller's notice (ClickBankNotice, JVZooNotice): the order it belongs to, made by the
     * first event that names it, and the event itself, once. The order's status follows its latest event by when it
     * happened, not by when its notice arrived: a sale whose notice is retried after its refund's stays refunded.
     */
    public static function record(array $e)
    {
        return \DB::transaction(function () use ($e) {
            $order = self::firstOrNew(['source' => $e['source'], 'receipt' => $e['receipt'], 'item' => $e['item']]);
            if (!$order->exists) {
                $order->fill(array_intersect_key($e, array_flip([
                    'parent_receipt', 'title', 'email', 'name', 'price', 'currency', 'affiliate', 'variables', 'test',
                ])));
                $order->status = self::STATUS[$e['type']];
                $order->code = self::newCode();
                $order->save();
            }
            if ($e['type'] == 'SALE') {
                $order->bought_at = $e['happened_at'];
            }

            $events = \DB::table('clickbankjvzoo_events');
            $seen = (clone $events)->where(['order_id' => $order->id, 'type' => $e['type'], 'happened_at' => $e['happened_at']])->exists();
            if (!$seen) {
                $events->insert([
                    'order_id' => $order->id, 'type' => $e['type'], 'happened_at' => $e['happened_at'], 'created_at' => now(),
                ]);
            }
            $latest = \DB::table('clickbankjvzoo_events')->where('order_id', $order->id)
                ->orderBy('happened_at', 'desc')->orderBy('id', 'desc')->value('type');
            $before = $order->status;
            $order->status = self::STATUS[$latest];
            $order->save();
            // a member who used the order's code is told when its status changes
            if ($order->redeemed_by !== null && $order->status !== $before) {
                Notice::queue($order);
            }

            return $order;
        });
    }
}
