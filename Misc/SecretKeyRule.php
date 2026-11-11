<?php

namespace Modules\ClickBankJVZoo\Misc;

use Illuminate\Contracts\Validation\Rule;

/**
 * A secret key typed into the settings page. Empty, or stars only (the saved key, unchanged), or a key; never a key
 * typed after the stars of a saved one. ClickBank's keys are up to 16 capitals and digits.
 */
class SecretKeyRule implements Rule
{
    protected $seller;
    protected $message = '';

    public function __construct($seller)
    {
        $this->seller = $seller;
    }

    public function passes($attribute, $value)
    {
        // spaces around a pasted key are dropped when it is saved (the module's settings.before_save)
        $value = trim((string)$value);
        if ($value === '' || preg_match('/^\*+$/', $value)) {
            return true;
        }
        if (strpos($value, '*') !== false) {
            $this->message = __('To change a saved key, clear the field first, then type the new key.');

            return false;
        }
        if ($this->seller == 'clickbank' && !preg_match('/^[A-Z0-9]{1,16}$/', $value)) {
            $this->message = __("ClickBank's Secret Key is up to 16 capital letters and digits.");

            return false;
        }
        if (mb_strlen($value) > 255) {
            $this->message = __('The key is longer than 255 characters.');

            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message;
    }
}
