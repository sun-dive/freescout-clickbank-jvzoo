<?php
// The module inside a FreeScout test copy: the test notices posted through FreeScout as the sellers post them, and
// what is kept checked in its database. Run on the test copy only, with the module installed:
//   php Tests/integration.php /path/to/freescout-test
// It refuses a FreeScout whose storage/.installed does not say "(test copy)", and empties the module's tables and
// secret keys when it starts and ends. Exits 1 if any check fails.

$app_dir = rtrim($argv[1] ?? '', '/');
if (!$app_dir || strpos((string)@file_get_contents($app_dir.'/storage/.installed'), '(test copy)') === false) {
    fwrite(STDERR, "refused: give the path of a FreeScout test copy\n");
    exit(2);
}
require $app_dir.'/vendor/autoload.php';
$app = require $app_dir.'/bootstrap/app.php';
// started as FreeScout's own commands start (that binds a request, which its providers need); the notices then go
// through the web side, as the sellers' do
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Modules\ClickBankJVZoo\Entities\Notice;
use Modules\ClickBankJVZoo\Entities\Order;
use Modules\ClickBankJVZoo\Misc\Codes;
use Modules\ClickBankJVZoo\Misc\Signed;
use Modules\ClickBankJVZoo\Providers\ClickBankJVZooServiceProvider as Module;

$failed = 0;
function check($what, $ok)
{
    global $failed;
    echo ($ok ? 'ok   ' : 'FAIL ').$what."\n";
    $failed += $ok ? 0 : 1;
}
function vector($name)
{
    return json_decode(file_get_contents(__DIR__.'/vectors/'.$name.'.json'), true);
}
function post($path, $body, $type)
{
    global $kernel;
    $url = rtrim(config('app.url'), '/').$path;
    $request = Illuminate\Http\Request::create($url, 'POST', [], [], [], ['CONTENT_TYPE' => $type], $body);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response->getStatusCode();
}
function clean()
{
    foreach (['ana.muller@example.com', 'nobody@example.com'] as $address) {
        $email = \App\Email::where('email', $address)->first();
        if ($email) {
            $customer = $email->customer;
            $email->delete();
            if ($customer) {
                $customer->delete();
            }
        }
    }
    \DB::table('clickbankjvzoo_notices')->delete();
    \DB::table('clickbankjvzoo_events')->delete();
    \DB::table('clickbankjvzoo_orders')->delete();
    foreach (array_merge(array_values(Module::SECRETS), array_values(Module::MEMBER), array_values(Module::OWN)) as $option) {
        \Option::remove($option);
    }
}
function orders()
{
    return Order::orderBy('id')->get();
}

clean();
$cb = vector('clickbank-sale');
$jv = vector('jvzoo-sale');

check('not set up: answered 503, so ClickBank tries again later', post('/clickbank/notice', $cb['body'], 'application/json') == 503);

\Option::set(Module::SECRETS['clickbank'], encrypt($cb['secret']));
\Option::set(Module::SECRETS['jvzoo'], encrypt($jv['secret']));
check('the secret keys are kept encrypted', \Option::get(Module::SECRETS['clickbank'], '', true, false) != $cb['secret'] && Module::secret('clickbank') == $cb['secret']);

// ClickBank
check('clickbank sale: answered 204', post('/clickbank/notice', $cb['body'], 'application/json') == 204);
$o = orders();
check('clickbank sale: two orders, one per product', count($o) == 2);
check('clickbank sale: as sent', $o[0]->source == 'clickbank' && $o[0]->receipt == 'CWOGBZLN' && $o[0]->item == '1'
    && $o[0]->email == 'ana.muller@example.com' && $o[0]->name == 'Ana Müller' && $o[0]->price == '27.00'
    && $o[0]->status == 'paid' && $o[0]->variables == ['member' => '12345'] && $o[0]->bought_at == '2026-11-11 16:30:15'
    && $o[1]->title == 'Example Bump' && $o[1]->price == '10.00');

check('clickbank sale sent again: answered 204', post('/clickbank/notice', $cb['body'], 'application/json') == 204);
check('clickbank sale sent again: kept once', Order::count() == 2 && \DB::table('clickbankjvzoo_events')->count() == 2);

check('clickbank URL check: answered 204', post('/clickbank/notice', vector('clickbank-url-test')['body'], 'application/json') == 204);
check('clickbank URL check: keeps nothing', Order::count() == 2);

$body = json_decode($cb['body'], true);
$body['notification'][40] = $body['notification'][40] == 'A' ? 'B' : 'A';
check('clickbank changed notice: answered 401', post('/clickbank/notice', json_encode($body), 'application/json') == 401);
check('clickbank changed notice: keeps nothing', Order::count() == 2);

// a refund whose notice comes before its sale's (ClickBank retried the sale)
check('clickbank refund first: answered 204', post('/clickbank/notice', vector('clickbank-r2-refund')['body'], 'application/json') == 204);
check('clickbank refund first: the orders are refunded', Order::where('receipt', 'R2RECEIPT')->pluck('status')->unique()->values()->all() == ['refunded']);
check('clickbank sale after its refund: answered 204', post('/clickbank/notice', vector('clickbank-r2-sale')['body'], 'application/json') == 204);
$r2 = Order::where('receipt', 'R2RECEIPT')->get();
check('clickbank sale after its refund: still refunded, with its time of sale', $r2->pluck('status')->unique()->values()->all() == ['refunded']
    && $r2[0]->bought_at == '2026-11-11 17:00:00');

check('clickbank test purchase: answered 204', post('/clickbank/notice', vector('clickbank-test-sale')['body'], 'application/json') == 204);
check('clickbank test purchase: kept, marked as a test', Order::where('receipt', 'TESTRCPT')->where('test', true)->count() == 2);

// JVZoo
$form = http_build_query($jv['fields']);
check('jvzoo sale: answered 204', post('/jvzoo/notice', $form, 'application/x-www-form-urlencoded') == 204);
$j = Order::where('source', 'jvzoo')->first();
check('jvzoo sale: as sent', $j && $j->receipt == 'AP-1A2B3C4D5E' && $j->name == 'José Müller' && $j->email == 'jose@example.com'
    && $j->price == '27.00' && $j->status == 'paid' && $j->variables == ['member' => '12345', 'src' => 'example']);
$changed = $jv['fields'];
$changed['ctransamount'] = '0.01';
check('jvzoo changed amount: answered 401', post('/jvzoo/notice', http_build_query($changed), 'application/x-www-form-urlencoded') == 401);
check('jvzoo changed amount: keeps nothing', Order::where('source', 'jvzoo')->count() == 1);

// beside a ticket: the customer's orders, read-only
function sidebar($customer)
{
    ob_start();
    \Eventy::action('conversation.before_prev_convs', $customer, new \App\Conversation(), new \App\Mailbox());

    return ob_get_clean();
}
$ana = \App\Customer::create('ana.muller@example.com', ['first_name' => 'Ana']);
$nobody = \App\Customer::create('nobody@example.com', ['first_name' => 'No']);
$html = sidebar($ana);
check('beside a ticket: all the customer\'s orders', strpos($html, 'Orders (6)') !== false
    && strpos($html, 'Example Product') !== false && strpos($html, 'Example Bump') !== false);
check('beside a ticket: newest first', strpos($html, 'R2RECEIPT') < strpos($html, 'CWOGBZLN'));
check('beside a ticket: paid, refunded and test, each marked', strpos($html, '>paid<') !== false && strpos($html, '>refunded<') !== false
    && strpos($html, '>test<') !== false);
check('beside a ticket: where, how much, the receipt', strpos($html, 'ClickBank') !== false && strpos($html, '27.00 USD') !== false
    && strpos($html, 'receipt CWOGBZLN') !== false);
check('beside a ticket: not accessed until used', substr_count($html, 'not accessed') == 6);
Order::where('receipt', 'CWOGBZLN')->where('item', '1')->update(['accessed_at' => '2026-11-12 09:00:00', 'accesses' => 2]);
$html = sidebar($ana);
check('beside a ticket: accessed, once used', substr_count($html, 'not accessed') == 5 && strpos($html, '>accessed Nov') !== false);
check('beside a ticket: nothing for a customer with no orders', sidebar($nobody) === '');
Order::record(['source' => 'jvzoo', 'type' => 'CGBK', 'test' => false, 'receipt' => 'XSS1', 'parent_receipt' => null, 'item' => '9',
    'title' => '<b>bold</b>', 'email' => 'ana.muller@example.com', 'name' => 'Ana', 'price' => 5, 'currency' => '', 'affiliate' => null,
    'variables' => [], 'happened_at' => '2026-11-13 00:00:00']);
$html = sidebar($ana);
check('beside a ticket: charged back is marked, and a product name is shown as text, never as markup',
    strpos($html, '>charged back<') !== false && strpos($html, '&lt;b&gt;bold&lt;/b&gt;') !== false && strpos($html, '<b>bold</b>') === false);

// codes: the member site uses a buyer's code, in one signed call
check('codes: every order has one', Order::whereNull('code')->count() == 0 && strlen(Order::first()->code) == 12);
function redeem(array $fields, $secret, $time = null)
{
    global $kernel;
    $fields['time'] = $time ?? time();
    $body = json_encode($fields);
    $request = Illuminate\Http\Request::create(rtrim(config('app.url'), '/').'/codes/redeem', 'POST', [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => Signed::sign($body, $secret)], $body);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}
list($member_secret, $member_public) = Signed::keypair();
$bump = Order::where('receipt', 'CWOGBZLN')->where('item', '2')->first();
$typed = strtolower(Codes::format($bump->code));
check('codes: before the member site is set up, answered 503', redeem(['code' => $typed, 'member' => '12345'], $member_secret)->getStatusCode() == 503);
\Option::set(Module::MEMBER['public'], $member_public);
$r = redeem(['code' => $typed, 'member' => '12345'], $member_secret);
$answer = json_decode($r->getContent(), true);
check('codes: a code typed in lower case with dashes is used', $r->getStatusCode() == 200 && $answer['ok'] === true
    && $answer['product']['item'] == '2' && $answer['product']['title'] == 'Example Bump' && $answer['member'] == '12345');
check('codes: the answer is signed by this help desk', Signed::open($r->getContent(), $r->headers->get(Signed::HEADER), Module::ownKeys()['public'])['ok'] === true);
$bump = $bump->fresh();
check('codes: the order is marked as the member\'s, and accessed', $bump->redeemed_by === '12345' && $bump->redeemed_at && $bump->accessed_at && $bump->accesses == 1);
$answer = json_decode(redeem(['code' => $typed, 'member' => '12345'], $member_secret)->getContent(), true);
check('codes: the same member again is fine, and counts', $answer['ok'] === true && $bump->fresh()->accesses == 2);
$answer = json_decode(redeem(['code' => $typed, 'member' => '99999'], $member_secret)->getContent(), true);
check('codes: another member is refused: used', $answer['ok'] === false && $answer['reason'] == 'used');
$refunded = Order::where('receipt', 'R2RECEIPT')->first();
$answer = json_decode(redeem(['code' => Codes::format($refunded->code), 'member' => '12345'], $member_secret)->getContent(), true);
check('codes: a refunded order\'s code is refused: refunded', $answer['ok'] === false && $answer['reason'] == 'refunded');
$answer = json_decode(redeem(['code' => 'ZZZZ-ZZZZ-ZZZZ', 'member' => '12345'], $member_secret)->getContent(), true);
check('codes: an unknown code is refused: unknown', $answer['ok'] === false && $answer['reason'] == 'unknown');
check('codes: a request signed with another key is answered 401', redeem(['code' => $typed, 'member' => '12345'], Signed::keypair()[0])->getStatusCode() == 401);
check('codes: a request over five minutes old is answered 401', redeem(['code' => $typed, 'member' => '12345'], $member_secret, time() - 400)->getStatusCode() == 401);
$html = sidebar($ana);
check('beside a ticket: the code, and the member who used it', strpos($html, 'code '.Codes::format($bump->code)) !== false
    && strpos($html, 'used by member 12345') !== false);

// telling the member site when a used code's order changes status: a stand-in member site answers on cue
$member_calls = [];
$member_answer = 500;
Notice::$transport = function ($url, $body, $headers) use (&$member_calls, &$member_answer) {
    $member_calls[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

    return $member_answer;
};
function send_notices()
{
    \Artisan::call('clickbankjvzoo:send-notices');
}
check('notices: none while the used code\'s order is still paid', Notice::count() == 0);
check('notices: the refund of that sale is answered 204', post('/clickbank/notice', vector('clickbank-refund')['body'], 'application/json') == 204);
$notice = Notice::first();
check('notices: one queued, for the used code only, saying refunded', Notice::count() == 1 && $notice->order_id == $bump->id
    && $notice->member == '12345' && $notice->status == 'refunded');
send_notices();
check('notices: with no address for the member site, they wait', count($member_calls) == 0 && $notice->fresh()->attempts == 0);
\Option::set(Module::MEMBER['url'], 'https://member.example/notices');
send_notices();
$notice = $notice->fresh();
check('notices: the member site fails: kept, to try again later', count($member_calls) == 1 && !$notice->sent_at && $notice->attempts == 1
    && $notice->last_error == 'HTTP 500' && $notice->next_attempt_at > now());
send_notices();
check('notices: not tried again before its time', count($member_calls) == 1);
Notice::where('id', $notice->id)->update(['next_attempt_at' => now()->subMinute()]);
$member_answer = 200;
send_notices();
$call = end($member_calls);
$told = Signed::open($call['body'], $call['headers'][Signed::HEADER], Module::ownKeys()['public']);
check('notices: the member site answers 200: sent', count($member_calls) == 2 && $notice->fresh()->sent_at !== null);
check('notices: what it was told, signed by this help desk, timed now', $call['url'] == 'https://member.example/notices'
    && $told['code'] == Codes::format($bump->code) && $told['member'] == '12345' && $told['status'] == 'refunded' && abs($told['time'] - time()) < 60);
send_notices();
check('notices: a sent notice is not sent again', count($member_calls) == 2);
$event = ['source' => 'clickbank', 'test' => false, 'receipt' => 'CWOGBZLN', 'parent_receipt' => null, 'item' => '2', 'title' => 'Example Bump',
    'email' => 'ana.muller@example.com', 'name' => 'Ana Müller', 'price' => 10, 'currency' => 'USD', 'affiliate' => null, 'variables' => []];
Order::record($event + ['type' => 'UNCANCEL-REBILL', 'happened_at' => '2026-11-13 00:00:00']);
Order::record($event + ['type' => 'CGBK', 'happened_at' => '2026-11-14 00:00:00']);
$member_answer = 500;
send_notices();
check('notices: of two for one order, the second waits while the first is unsent', count($member_calls) == 3
    && json_decode(end($member_calls)['body'], true)['status'] == 'paid');
Notice::whereNull('sent_at')->update(['next_attempt_at' => now()->subMinute()]);
$member_answer = 200;
send_notices();
check('notices: then both go, in the order they happened', count($member_calls) == 5
    && json_decode($member_calls[3]['body'], true)['status'] == 'paid' && json_decode($member_calls[4]['body'], true)['status'] == 'charged back');
// FreeScout's own start-up has run its schedule hook; every minute is written "* * * * *" or, here, "* * * * * *"
$ours = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->filter(function ($e) {
    return strpos($e->command, 'clickbankjvzoo:send-notices') !== false;
});
check('notices: sent from FreeScout\'s scheduler, once, every minute', $ours->count() == 1
    && preg_match('/^\* \* \* \* \*( \*)?$/', trim($ours->first()->expression)));
check('beside a ticket: what the member site has been told', strpos(sidebar($ana), 'member site: told') !== false);
Notice::$transport = null;

// the settings page
$sections = \Eventy::filter('settings.sections', []);
check('settings: the ClickBank & JVZoo page is listed', isset($sections[Module::SECTION]));
check('settings: its view', \Eventy::filter('settings.view', 'settings/x', Module::SECTION) == 'clickbankjvzoo::settings');
$params = \Eventy::filter('settings.section_params', [], Module::SECTION);
// rendered with what FreeScout's settings page passes it
$html = view('clickbankjvzoo::settings', array_merge($params['template_vars'], [
    'settings' => \Eventy::filter('settings.section_settings', [], Module::SECTION),
    'errors'   => new Illuminate\Support\ViewErrorBag(),
]))->render();
check('settings: shows the redeem address and this help desk\'s public key, never its secret',
    strpos($html, rtrim(config('app.url'), '/').'/codes/redeem') !== false && strpos($html, Module::ownKeys()['public']) !== false
    && strpos($html, Module::ownKeys()['secret']) === false);
check('settings: shows both notice addresses', strpos($html, rtrim(config('app.url'), '/').'/clickbank/notice') !== false
    && strpos($html, rtrim(config('app.url'), '/').'/jvzoo/notice') !== false);
check('settings: shows each saved key as one star per character, never the key',
    strpos($html, $cb['secret']) === false && strpos($html, $jv['secret']) === false
    && strpos($html, 'value="'.str_repeat('*', strlen($cb['secret'])).'"') !== false
    && strpos($html, 'value="'.str_repeat('*', strlen($jv['secret'])).'"') !== false);
check('settings: all four boxes are text boxes marked as no password, so no login is filled into them',
    substr_count($html, 'type="text"') == 4 && strpos($html, 'type="password"') === false && substr_count($html, 'data-lpignore="true"') == 4);

// FreeScout's own Save, as the page posts it
function save_settings(array $values)
{
    $request = Illuminate\Http\Request::create(rtrim(config('app.url'), '/').'/settings/'.Module::SECTION, 'POST', ['settings' => $values]);
    $request->setLaravelSession(app('session')->driver('array'));
    app()->instance('request', $request);
    app(App\Http\Controllers\SettingsController::class)->save(Module::SECTION);
    // FreeScout flashes the errors into its own session store
    $errors = app('session.store')->get('errors');
    app('session.store')->forget('errors');

    return $errors ? implode(' ', $errors->all()) : '';
}
$cbo = Module::SECRETS['clickbank'];
$jvo = Module::SECRETS['jvzoo'];
check('save: a new ClickBank key, the JVZoo box left empty', save_settings([$cbo => 'NEWKEY123', $jvo => '']) === ''
    && Module::secret('clickbank') == 'NEWKEY123' && Module::secret('jvzoo') == '');
check('save: an empty key shows an empty box', \Eventy::filter('settings.section_settings', [], Module::SECTION)[$jvo] === '');
check('save: stars keep the saved key; a new JVZoo key is kept', save_settings([$cbo => '*********', $jvo => 'jv-key']) === ''
    && Module::secret('clickbank') == 'NEWKEY123' && Module::secret('jvzoo') == 'jv-key');
check('save: spaces around a pasted key are dropped', save_settings([$cbo => ' PASTED42 ', $jvo => '******']) === ''
    && Module::secret('clickbank') == 'PASTED42');
save_settings([$cbo => 'NEWKEY123', $jvo => '******']);
$msg = save_settings([$cbo => '*********NEWKEY456', $jvo => '******']);
check('save: a key typed after the stars is refused, saying to clear the field first, and nothing changes',
    strpos($msg, 'clear the field first') !== false && Module::secret('clickbank') == 'NEWKEY123');
$mpo = Module::MEMBER['public'];
$muo = Module::MEMBER['url'];
$msg = save_settings([$cbo => '*********', $jvo => '******', $mpo => 'not-a-key', $muo => '']);
check('save: a member key that is not one is refused, saying what one looks like', strpos($msg, '44 characters of base64') !== false);
$msg = save_settings([$cbo => '*********', $jvo => '******', $mpo => $member_public, $muo => 'http://example.com/revoke']);
check('save: an address that is not https is refused, saying so', strpos($msg, 'starts with https://') !== false);
check('save: the member key and address are kept', save_settings([$cbo => '*********', $jvo => '******', $mpo => ' '.$member_public.' ', $muo => 'https://example.com/revoke']) === ''
    && Module::memberKey() == $member_public && Module::memberUrl() == 'https://example.com/revoke');
$msg = save_settings([$cbo => 'lowercase1', $jvo => '******']);
check('save: a ClickBank key that is not capitals and digits is refused, saying so',
    strpos($msg, 'up to 16 capital letters and digits') !== false && Module::secret('clickbank') == 'NEWKEY123');

clean();
echo $failed ? "$failed failed\n" : "all passed\n";
exit($failed ? 1 : 0);
