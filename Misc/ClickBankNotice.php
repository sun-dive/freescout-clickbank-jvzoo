<?php

namespace Modules\ClickBankJVZoo\Misc;

/**
 * ClickBank's Instant Notification (INS, versions 6.0 to 8.0): a JSON body {"notification", "iv"}, the notification
 * encrypted with AES-256-CBC under a key made from the seller's secret key: the first 32 hex characters of its SHA-1,
 * used as they are. Decrypted, it is one transaction; read() turns it into one event per product line, in the shape
 * Order::record() keeps. Plain PHP, so it is tested without FreeScout.
 */
class ClickBankNotice
{
    // The transactions kept, and the status each leaves an order in (Order::STATUS). Others, such as TEST (the
    // seller's URL check), abandoned carts or a changed card, carry no order to keep.
    const KEPT = ['SALE', 'BILL', 'RFND', 'CGBK', 'INSF', 'CANCEL-REBILL', 'UNCANCEL-REBILL'];

    // Test purchases arrive under their own names; they are kept as the live type, marked as tests.
    const TESTS = [
        'TEST_SALE'            => 'SALE',
        'TEST_BILL'            => 'BILL',
        'TEST_RFND'            => 'RFND',
        'CANCEL-TEST-REBILL'   => 'CANCEL-REBILL',
        'UNCANCEL-TEST-REBILL' => 'UNCANCEL-REBILL',
    ];

    /**
     * The events in a notice's body. Throws if the body is not a notice, or does not decrypt under this secret key.
     */
    public static function read($body, $secret)
    {
        return self::events(self::decrypt($body, $secret));
    }

    /**
     * The notice's transaction, decrypted.
     */
    public static function decrypt($body, $secret)
    {
        $envelope = json_decode((string)$body, true);
        if (!is_array($envelope) || !is_string($envelope['notification'] ?? null) || !is_string($envelope['iv'] ?? null)) {
            throw new \InvalidArgumentException('not a ClickBank notice');
        }
        $cipher = base64_decode($envelope['notification'], true);
        $iv = base64_decode($envelope['iv'], true);
        if ($cipher === false || $iv === false || strlen($iv) != 16) {
            throw new \InvalidArgumentException('not a ClickBank notice');
        }
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', substr(sha1((string)$secret), 0, 32), OPENSSL_RAW_DATA, $iv);
        // ClickBank's own sample trims what can follow the JSON: padding and control characters
        $transaction = $plain === false ? null : json_decode(trim($plain, "\x00..\x20"), true);
        if (!is_array($transaction) || empty($transaction['transactionType'])) {
            throw new \UnexpectedValueException('the notice does not decrypt under this secret key');
        }

        return $transaction;
    }

    /**
     * One event per product line of a decrypted transaction; none for a transaction with no order to keep.
     */
    public static function events(array $t)
    {
        $type = (string)$t['transactionType'];
        $test = ($t['paymentMethod'] ?? '') === 'TEST';
        if (isset(self::TESTS[$type])) {
            $type = self::TESTS[$type];
            $test = true;
        }
        if (!in_array($type, self::KEPT)) {
            return [];
        }
        $billing = $t['customer']['billing'] ?? [];
        $email = $billing['email'] ?? ($t['customer']['shipping']['email'] ?? '');
        $name = $billing['fullName'] ?? trim(($billing['firstName'] ?? '').' '.($billing['lastName'] ?? ''));
        $receipt = (string)($t['receipt'] ?? '');
        $first = (string)($t['upsell']['upsellOriginalReceipt'] ?? '');

        $events = [];
        foreach ($t['lineItems'] ?? [] as $line) {
            $events[] = [
                'source'         => 'clickbank',
                'type'           => $type,
                'test'           => $test,
                'receipt'        => $receipt,
                // an upsell names the receipt its buyer started with
                'parent_receipt' => ($first !== '' && $first !== $receipt) ? $first : null,
                'item'           => (string)($line['itemNo'] ?? ''),
                'title'          => (string)($line['productTitle'] ?? ''),
                'email'          => strtolower(trim((string)$email)),
                'name'           => trim((string)$name),
                // what the buyer paid for the line, before tax and shipping
                'price'          => isset($line['productPrice'])
                    ? round((float)$line['productPrice'] - (float)($line['productDiscount'] ?? 0), 2) : null,
                'currency'       => (string)($t['currency'] ?? ''),
                'affiliate'      => ($t['affiliate'] ?? '') !== '' ? (string)$t['affiliate'] : null,
                // anything the seller added to the payment link comes back here
                'variables'      => is_array($t['vendorVariables'] ?? null) ? $t['vendorVariables'] : [],
                'happened_at'    => self::utc($t['transactionTime'] ?? null),
            ];
        }

        return $events;
    }

    /**
     * An ISO 8601 time as UTC, "Y-m-d H:i:s"; null if there is none.
     */
    public static function utc($time)
    {
        if (!$time) {
            return null;
        }
        $t = strtotime((string)$time);

        return $t === false ? null : gmdate('Y-m-d H:i:s', $t);
    }
}
