<?php

namespace App\Invoicing;

/** The currencies an invoice can be written in: złoty, and the ones Polish businesses invoice in most. */
final class Currencies
{
    public const CODES = ['PLN', 'EUR', 'USD', 'GBP', 'CHF', 'CZK', 'SEK', 'NOK', 'DKK', 'HUF', 'CAD', 'AUD', 'JPY'];
}
