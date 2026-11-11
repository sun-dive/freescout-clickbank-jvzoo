<?php

namespace Modules\ClickBankJVZoo\Misc;

/**
 * The code each order gets: 12 characters in three groups (K7QM-3XDP-9RWT), from an alphabet with no characters
 * easily confused when read or typed: no 0, O, 1, I, L or U. About 59 bits, kept without the dashes.
 */
class Codes
{
    const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
    const LENGTH = 12;

    public static function generate()
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * A code as typed, the way it is kept: capitals, dashes and spaces dropped. '' if it cannot be a code.
     */
    public static function normalize($typed)
    {
        $code = strtoupper(preg_replace('/[\s-]+/', '', (string)$typed));

        return (strlen($code) == self::LENGTH && strspn($code, self::ALPHABET) == self::LENGTH) ? $code : '';
    }

    /**
     * A kept code as it is shown: in three groups of four.
     */
    public static function format($code)
    {
        return implode('-', str_split((string)$code, 4));
    }
}
