<?php

namespace App\Invoicing;

use RuntimeException;

/** The draft cannot be issued yet; the reasons are in words, ready to show. */
final class InvoiceNotReady extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
