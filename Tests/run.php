<?php
// The notice readers against notices made outside PHP (vectors/make.sh). Plain PHP, no FreeScout needed:
//   php Tests/run.php
// Exits 1 if any check fails.

require __DIR__.'/../Misc/ClickBankNotice.php';
require __DIR__.'/../Misc/JVZooNotice.php';
require __DIR__.'/../Misc/Codes.php';
require __DIR__.'/../Misc/Signed.php';

use Modules\ClickBankJVZoo\Misc\ClickBankNotice;
use Modules\ClickBankJVZoo\Misc\Codes;
use Modules\ClickBankJVZoo\Misc\JVZooNotice;
use Modules\ClickBankJVZoo\Misc\Signed;

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

// codes
$codes = [];
for ($i = 0; $i < 2000; $i++) {
    $codes[] = Codes::generate();
}
check('codes: 12 characters, none of 0 O 1 I L U', count(array_filter($codes, function ($c) {
    return strlen($c) == 12 && !preg_match('/[0O1ILU]/', $c) && strspn($c, Codes::ALPHABET) == 12;
})) == 2000);
check('codes: 2000 made, all different', count(array_unique($codes)) == 2000);
check('codes: shown in three groups', Codes::format('K7QM3XDP9RWT') == 'K7QM-3XDP-9RWT');
check('codes: typed in lower case, with dashes or spaces, still found',
    Codes::normalize('k7qm-3xdp-9rwt') == 'K7QM3XDP9RWT' && Codes::normalize(' K7QM 3XDP 9RWT ') == 'K7QM3XDP9RWT');
check('codes: a letter not in the alphabet, or a wrong length, is no code',
    Codes::normalize('K7QM-3XDP-9RWO') === '' && Codes::normalize('K7QM-3XDP') === '' && Codes::normalize('') === '');

// a member site's signed request, signed by the openssl tool
$v = vector('member-request');
$fields = Signed::open($v['body'], $v['signature'], $v['public'], $v['time']);
check('signed: a request signed by openssl opens', $fields['code'] == 'K7QM-3XDP-9RWT' && $fields['member'] == '12345');
refused('signed: a changed request is refused', \UnexpectedValueException::class, function () use ($v) {
    Signed::open(str_replace('12345', '12346', $v['body']), $v['signature'], $v['public'], $v['time']);
});
refused('signed: another key is refused', \UnexpectedValueException::class, function () use ($v) {
    Signed::open($v['body'], $v['signature'], Signed::keypair()[1], $v['time']);
});
refused('signed: a request over five minutes old is refused', \UnexpectedValueException::class, function () use ($v) {
    Signed::open($v['body'], $v['signature'], $v['public'], $v['time'] + 301);
});
check('signed: one within five minutes opens', is_array(Signed::open($v['body'], $v['signature'], $v['public'], $v['time'] + 299)));
list($secret, $public) = Signed::keypair();
$body = json_encode(['ok' => true, 'time' => 1794390000]);
check('signed: what this side signs, the other opens', Signed::open($body, Signed::sign($body, $secret), $public, 1794390000)['ok'] === true);

echo $failed ? "$failed failed\n" : "all passed\n";
exit($failed ? 1 : 0);
