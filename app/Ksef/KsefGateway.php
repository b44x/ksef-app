<?php

namespace App\Ksef;

use App\Models\Company;

/**
 * Everything the application asks of KSeF. The rest of the app depends on this interface, never on a particular SDK:
 * {@see SdkKsefGateway} is the adapter for b44x/ksef-php, and the only place that knows about it.
 */
interface KsefGateway
{
    /**
     * Checks, without changing anything in KSeF, that the company can work with it: the credentials, the login, the
     * limits and the permissions.
     */
    public function check(Company $company): ConnectionReport;
}
