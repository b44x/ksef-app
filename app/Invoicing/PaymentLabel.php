<?php

namespace App\Invoicing;

use B4x\Ksef\Invoice\PaymentMethod;

/** How a payment method is called. The text is a translation key: translate it where it is shown. */
final class PaymentLabel
{
    public static function of(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Cash => 'Cash',
            PaymentMethod::Card => 'Card',
            PaymentMethod::Voucher => 'Voucher',
            PaymentMethod::Cheque => 'Cheque',
            PaymentMethod::Credit => 'Credit',
            PaymentMethod::BankTransfer => 'Bank transfer',
            PaymentMethod::Mobile => 'Mobile payment',
        };
    }
}
