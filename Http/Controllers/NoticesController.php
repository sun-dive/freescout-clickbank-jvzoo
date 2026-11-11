<?php

namespace Modules\ClickBankJVZoo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ClickBankJVZoo\Entities\Order;
use Modules\ClickBankJVZoo\Misc\ClickBankNotice;
use Modules\ClickBankJVZoo\Misc\JVZooNotice;
use Modules\ClickBankJVZoo\Providers\ClickBankJVZooServiceProvider as Module;

/**
 * Receives the sellers' notices and keeps their orders. A notice that does not check out is answered 401 and keeps
 * nothing; its contents are never logged, as they name the buyer.
 */
class NoticesController extends Controller
{
    public function clickbank(Request $request)
    {
        $secret = Module::secret('clickbank');
        if ($secret === '') {
            // ClickBank tries again later (every four hours, five times at most)
            return response('Not set up', 503);
        }
        try {
            $events = ClickBankNotice::read($request->getContent(), $secret);
        } catch (\Exception $e) {
            \Log::warning('[ClickBank notice] refused: '.$e->getMessage());

            return response('Refused', 401);
        }

        return $this->keep($events);
    }

    public function jvzoo(Request $request)
    {
        $secret = Module::secret('jvzoo');
        if ($secret === '') {
            return response('Not set up', 503);
        }
        // the fields exactly as posted: the signature covers them before any of FreeScout's input handling
        parse_str($request->getContent(), $fields);
        try {
            $events = JVZooNotice::read($fields, $secret);
        } catch (\Exception $e) {
            \Log::warning('[JVZoo notice] refused: '.$e->getMessage());

            return response('Refused', 401);
        }

        return $this->keep($events);
    }

    protected function keep(array $events)
    {
        foreach ($events as $event) {
            Order::record($event);
        }

        // ClickBank counts any answer in the 200s, given within 3 seconds, as delivered
        return response('', 204);
    }
}
