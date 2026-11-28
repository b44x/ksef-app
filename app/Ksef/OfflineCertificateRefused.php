<?php

namespace App\Ksef;

use RuntimeException;

/** KSeF would not issue an Offline certificate (login by token, limit reached, ...). The reason is ready to show. */
final class OfflineCertificateRefused extends RuntimeException {}
