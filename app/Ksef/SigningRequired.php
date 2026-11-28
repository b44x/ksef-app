<?php

namespace App\Ksef;

use RuntimeException;

/** The company signs in to KSeF by hand and there is no valid session: the person has to sign in first. */
final class SigningRequired extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('Sign in to KSeF first: the signed login has expired or was never made.'));
    }
}
