<?php

namespace App\Ksef;

use RuntimeException;

/**
 * The connection failed while the invoice was on its way: KSeF may or may not have it. Sending the same document again
 * is safe, because KSeF recognises a duplicate (same seller, type and number) and answers with the original's number.
 */
final class SubmissionUncertain extends RuntimeException {}
