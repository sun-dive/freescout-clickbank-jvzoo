<?php

namespace Modules\ClickBankJVZoo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ClickBankJVZoo\Entities\Order;
use Modules\ClickBankJVZoo\Misc\Codes;
use Modules\ClickBankJVZoo\Misc\Signed;
use Modules\ClickBankJVZoo\Providers\ClickBankJVZooServiceProvider as Module;

/**
 * The member site asks to use a buyer's code, in one signed call: {"code", "member", "time"}. The answer is signed by
 * this help desk: {"ok", "code", "member", "time"}, with the product when it is ok, the reason when it is not.
 */
class CodesController extends Controller
{
    public function redeem(Request $request)
    {
        $public = Module::memberKey();
        if ($public === '') {
            return response('Not set up', 503);
        }
        try {
            $asked = Signed::open($request->getContent(), $request->header(Signed::HEADER), $public);
        } catch (\Exception $e) {
            \Log::warning('[Code redeem] refused: '.$e->getMessage());

            return response('Refused', 401);
        }

        $result = Order::redeem($asked['code'] ?? '', $asked['member'] ?? '');
        $answer = [
            'ok'     => $result['ok'],
            'code'   => Codes::format(Codes::normalize($asked['code'] ?? '')),
            'member' => (string)($asked['member'] ?? ''),
        ];
        if ($result['ok']) {
            $order = $result['order'];
            $answer['product'] = ['source' => $order->source, 'item' => $order->item, 'title' => $order->title];
            $answer['test'] = (bool)$order->test;
        } else {
            $answer['reason'] = $result['reason'];
        }
        $answer['time'] = time();
        $body = json_encode($answer);

        return response($body, 200)->header('Content-Type', 'application/json')
            ->header(Signed::HEADER, Signed::sign($body, Module::ownKeys()['secret']));
    }
}
