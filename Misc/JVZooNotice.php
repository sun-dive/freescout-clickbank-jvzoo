<?php

namespace Modules\ClickBankJVZoo\Misc;

/**
 * JVZoo's notice (JVZIPN): a form POST signed by cverify, the first 8 hex characters, in capitals, of the SHA-1 of
 * every other field's value in the order of the fields' names, each followed by "|", then the seller's secret key.
 * read() checks that and turns the notice into one event, in the shape Order::record() keeps. Plain PHP, so it is
 * tested without FreeScout.
 */
class JVZooNotice
{
    // The transactions kept (Order::STATUS gives the status each leaves an order in).
    const KEPT = ['SALE', 'BILL', 'RFND', 'CGBK', 'INSF', 'CANCEL-REBILL', 'UNCANCEL-REBILL'];

    /**
     * The events in a notice's fields, as posted. Throws if the signature does not match this secret key.
     */
    public static function read(array $fields, $secret)
    {
        if (!is_string($fields['cverify'] ?? null) || !hash_equals(self::cverify($fields, $secret), strtoupper($fields['cverify']))) {
            throw new \UnexpectedValueException('the notice is not signed with this secret key');
        }
        $type = (string)($fields['ctransaction'] ?? '');
        if (!in_array($type, self::KEPT)) {
            return [];
        }
        // anything the seller added to the buy link comes back here, as a query string
        parse_str((string)($fields['cvendthru'] ?? ''), $variables);

        return [[
            'source'         => 'jvzoo',
            'type'           => $type,
            'test'           => false,
            'receipt'        => (string)($fields['ctransreceipt'] ?? ''),
            // an upsell names the receipt its buyer started with
            'parent_receipt' => ($fields['cupsellreceipt'] ?? '') !== '' ? (string)$fields['cupsellreceipt'] : null,
            'item'           => (string)($fields['cproditem'] ?? ''),
            'title'          => (string)($fields['cprodtitle'] ?? ''),
            'email'          => strtolower(trim((string)($fields['ccustemail'] ?? ''))),
            'name'           => trim((string)($fields['ccustname'] ?? '')),
            'price'          => is_numeric($fields['ctransamount'] ?? null) ? round((float)$fields['ctransamount'], 2) : null,
            // JVZoo's notice names no currency
            'currency'       => '',
            'affiliate'      => ($fields['ctransaffiliate'] ?? '') !== '' ? (string)$fields['ctransaffiliate'] : null,
            'variables'      => $variables,
            'happened_at'    => is_numeric($fields['ctranstime'] ?? null) ? gmdate('Y-m-d H:i:s', (int)$fields['ctranstime']) : null,
        ]];
    }

    /**
     * The signature JVZoo puts in cverify, made the way JVZoo's own sample makes it.
     */
    public static function cverify(array $fields, $secret)
    {
        unset($fields['cverify']);
        ksort($fields);
        $pop = '';
        foreach ($fields as $value) {
            $pop .= $value.'|';
        }
        $pop .= $secret;
        // JVZoo's sample converts the string to UTF-8 when it is not already
        if (!mb_check_encoding($pop, 'UTF-8')) {
            $pop = mb_convert_encoding($pop, 'UTF-8');
        }

        return strtoupper(substr(sha1($pop), 0, 8));
    }
}
