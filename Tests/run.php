<?php
// The notice readers against notices made outside PHP (vectors/make.sh). Plain PHP, no FreeScout needed:
//   php Tests/run.php
// Exits 1 if any check fails.

require __DIR__.'/../Misc/ClickBankNotice.php';
require __DIR__.'/../Misc/JVZooNotice.php';

use Modules\ClickBankJVZoo\Misc\ClickBankNotice;
use Modules\ClickBankJVZoo\Misc\JVZooNotice;

$failed = 0;
function check($what, $ok)
{
    global $failed;
    echo ($ok ? 'ok   ' : 'FAIL ').$what."\n";
    $failed += $ok ? 0 : 1;
}
function refused($what, $class, callable $f)
{
    try {
        $f();
        check($what.' (not refused)', false);
    } catch (\Exception $e) {
        check($what, $e instanceof $class);
    }
}
function vector($name)
{
    return json_decode(file_get_contents(__DIR__.'/vectors/'.$name.'.json'), true);
}

// ClickBank: a sale of two products, the second a bump at a discount
$v = vector('clickbank-sale');
check('clickbank: decrypts to the transaction it was made from', ClickBankNotice::decrypt($v['body'], $v['secret']) == $v['transaction']);
$e = ClickBankNotice::read($v['body'], $v['secret']);
check('clickbank: one event per product line', count($e) == 2);
check('clickbank: the sale', $e[0]['source'] == 'clickbank' && $e[0]['type'] == 'SALE' && $e[0]['test'] === false
    && $e[0]['receipt'] == 'CWOGBZLN' && $e[0]['item'] == '1' && $e[0]['title'] == 'Example Product');
check('clickbank: the buyer, email in lower case', $e[0]['email'] == 'ana.muller@example.com' && $e[0]['name'] == 'Ana Müller');
check('clickbank: the price paid per line, after its discount', $e[0]['price'] == 27.0 && $e[1]['price'] == 10.0 && $e[0]['currency'] == 'USD');
check('clickbank: an original receipt names no parent', $e[0]['parent_receipt'] === null);
check('clickbank: the payment link\'s variables come back', $e[0]['variables'] == ['member' => '12345']);
check('clickbank: the time in UTC', $e[0]['happened_at'] == '2026-11-11 16:30:15');
check('clickbank: the affiliate', $e[0]['affiliate'] == 'someaffiliate');

refused('clickbank: another secret key is refused', \UnexpectedValueException::class,
    function () use ($v) { ClickBankNotice::read($v['body'], 'OTHERKEY0123ABC'); });
$body = json_decode($v['body'], true);
$body['notification'][40] = $body['notification'][40] == 'A' ? 'B' : 'A';
refused('clickbank: a changed notice is refused', \UnexpectedValueException::class,
    function () use ($body, $v) { ClickBankNotice::read(json_encode($body), $v['secret']); });
refused('clickbank: a body that is not a notice is refused', \InvalidArgumentException::class,
    function () use ($v) { ClickBankNotice::read('{"hello": 1}', $v['secret']); });

$v = vector('clickbank-test-sale');
$e = ClickBankNotice::read($v['body'], $v['secret']);
check('clickbank: a test purchase is kept as a sale, marked as a test', count($e) == 2 && $e[0]['type'] == 'SALE' && $e[0]['test'] === true);

$v = vector('clickbank-url-test');
check('clickbank: the URL check decrypts and carries no order', ClickBankNotice::read($v['body'], $v['secret']) === []);

// JVZoo: a sale with an accented name and the buy link's variables
$v = vector('jvzoo-sale');
$e = JVZooNotice::read($v['fields'], $v['secret']);
check('jvzoo: the signature made by Python is accepted', count($e) == 1);
check('jvzoo: the sale', $e[0]['source'] == 'jvzoo' && $e[0]['type'] == 'SALE' && $e[0]['receipt'] == 'AP-1A2B3C4D5E'
    && $e[0]['item'] == '412345' && $e[0]['title'] == 'Example Product' && $e[0]['parent_receipt'] === null);
check('jvzoo: the buyer, email in lower case', $e[0]['email'] == 'jose@example.com' && $e[0]['name'] == 'José Müller');
check('jvzoo: the price, the affiliate', $e[0]['price'] == 27.0 && $e[0]['affiliate'] == '98765');
check('jvzoo: the buy link\'s variables come back', $e[0]['variables'] == ['member' => '12345', 'src' => 'example']);
check('jvzoo: the time in UTC', $e[0]['happened_at'] == '2026-11-11 08:30:15');

refused('jvzoo: another secret key is refused', \UnexpectedValueException::class,
    function () use ($v) { JVZooNotice::read($v['fields'], 'another-secret'); });
$changed = $v['fields'];
$changed['ctransamount'] = '0.01';
refused('jvzoo: a changed amount is refused', \UnexpectedValueException::class,
    function () use ($changed, $v) { JVZooNotice::read($changed, $v['secret']); });
$unsigned = $v['fields'];
unset($unsigned['cverify']);
refused('jvzoo: an unsigned notice is refused', \UnexpectedValueException::class,
    function () use ($unsigned, $v) { JVZooNotice::read($unsigned, $v['secret']); });

echo $failed ? "$failed failed\n" : "all passed\n";
exit($failed ? 1 : 0);
