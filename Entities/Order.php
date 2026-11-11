<?php

namespace Modules\ClickBankJVZoo\Entities;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'clickbankjvzoo_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'variables' => 'array',
        'test'      => 'boolean',
    ];

    protected $dates = ['bought_at'];

    // The status each kept event leaves an order in.
    const STATUS = [
        'SALE'            => 'active',
        'BILL'            => 'active',
        'UNCANCEL-REBILL' => 'active',
        'RFND'            => 'refunded',
        'CGBK'            => 'chargeback',
        'INSF'            => 'chargeback',
        'CANCEL-REBILL'   => 'cancelled',
    ];

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
            $order->status = self::STATUS[$latest];
            $order->save();

            return $order;
        });
    }
}
