<?php

namespace Modules\ClickBankJVZoo\Misc;

use Illuminate\Contracts\Validation\Rule;

/**
 * A settings field that is empty or matches a pattern, refused in plain words when it does not.
 */
class MatchRule implements Rule
{
    protected $pattern;
    protected $message;

    public function __construct($pattern, $message)
    {
        $this->pattern = $pattern;
        $this->message = $message;
    }

    public function passes($attribute, $value)
    {
        $value = trim((string)$value);

        return $value === '' || preg_match($this->pattern, $value);
    }

    public function message()
    {
        return $this->message;
    }
}
