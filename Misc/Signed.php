<?php

namespace Modules\ClickBankJVZoo\Misc;

/**
 * Messages between this help desk and a member site, each signed by its sender: the body is JSON carrying a "time"
 * (Unix seconds), and the X-Signature header is the base64 Ed25519 signature of the body's exact bytes. Keys are
 * base64: a public key 32 bytes, a secret key 64. A message more than five minutes from the receiver's clock is
 * refused, so an old one cannot be sent again later.
 */
class Signed
{
    const HEADER = 'X-Signature';
    const WINDOW = 300;

    /**
     * A new key pair: [secret, public], base64.
     */
    public static function keypair()
    {
        $pair = sodium_crypto_sign_keypair();

        return [base64_encode(sodium_crypto_sign_secretkey($pair)), base64_encode(sodium_crypto_sign_publickey($pair))];
    }

    public static function sign($body, $secret)
    {
        return base64_encode(sodium_crypto_sign_detached((string)$body, base64_decode($secret, true)));
    }

    /**
     * The message's fields, if the signature is the given public key's and its time is near now. Throws otherwise.
     */
    public static function open($body, $signature, $public, $now = null)
    {
        $key = base64_decode((string)$public, true);
        $sig = base64_decode((string)$signature, true);
        if ($key === false || strlen($key) != SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || $sig === false || strlen($sig) != SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached($sig, (string)$body, $key)) {
            throw new \UnexpectedValueException('not signed by the member site');
        }
        $fields = json_decode((string)$body, true);
        if (!is_array($fields) || !is_int($fields['time'] ?? null)) {
            throw new \UnexpectedValueException('not a message');
        }
        if (abs(($now ?? time()) - $fields['time']) > self::WINDOW) {
            throw new \UnexpectedValueException('the message is too old or too new');
        }

        return $fields;
    }
}
