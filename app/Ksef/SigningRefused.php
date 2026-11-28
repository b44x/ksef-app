<?php

namespace App\Ksef;

use RuntimeException;

/** KSeF (or the check before it) did not accept the signed login. The reason is in words, ready to show. */
final class SigningRefused extends RuntimeException {}
