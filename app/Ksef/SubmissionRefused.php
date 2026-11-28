<?php

namespace App\Ksef;

use RuntimeException;

/** Nothing was stored in KSeF: it refused the request (credentials, limits, the document). Sending again is safe. */
final class SubmissionRefused extends RuntimeException {}
