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

use Modules\ClickBankJVZoo\Entities\Order;
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
    \DB::table('clickbankjvzoo_events')->delete();
    \DB::table('clickbankjvzoo_orders')->delete();
    foreach (Module::SECRETS as $option) {
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

// the settings page
$sections = \Eventy::filter('settings.sections', []);
check('settings: the ClickBank & JVZoo page is listed', isset($sections[Module::SECTION]));
check('settings: its view', \Eventy::filter('settings.view', 'settings/x', Module::SECTION) == 'clickbankjvzoo::settings');
$params = \Eventy::filter('settings.section_params', [], Module::SECTION);
$html = view('clickbankjvzoo::settings', [
    'settings'    => \Eventy::filter('settings.section_settings', [], Module::SECTION),
    'notice_urls' => $params['template_vars']['notice_urls'],
    'errors'      => new Illuminate\Support\ViewErrorBag(),
])->render();
check('settings: shows both notice addresses', strpos($html, rtrim(config('app.url'), '/').'/clickbank/notice') !== false
    && strpos($html, rtrim(config('app.url'), '/').'/jvzoo/notice') !== false);
check('settings: shows each saved key as one star per character, never the key',
    strpos($html, $cb['secret']) === false && strpos($html, $jv['secret']) === false
    && strpos($html, 'value="'.str_repeat('*', strlen($cb['secret'])).'"') !== false
    && strpos($html, 'value="'.str_repeat('*', strlen($jv['secret'])).'"') !== false);
check('settings: the key boxes are text boxes marked as no password, so no login is filled into them',
    substr_count($html, 'type="text"') == 2 && strpos($html, 'type="password"') === false && substr_count($html, 'data-lpignore="true"') == 2);

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
$msg = save_settings([$cbo => 'lowercase1', $jvo => '******']);
check('save: a ClickBank key that is not capitals and digits is refused, saying so',
    strpos($msg, 'up to 16 capital letters and digits') !== false && Module::secret('clickbank') == 'NEWKEY123');

clean();
echo $failed ? "$failed failed\n" : "all passed\n";
exit($failed ? 1 : 0);
