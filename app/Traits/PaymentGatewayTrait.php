<?php

namespace App\Traits;


trait PaymentGatewayTrait
{
    public static function getPaymentGatewaySupportedCurrencies($key = null): array
    {
        $paymentGateway = [
            "noon" => [
                "EGP" => "Egyptian Pound",
                "SAR" => "Saudi Riyal",
                "AED" => "United Arab Emirates Dirham"
            ],
        ];

        if ($key) {
            return array_key_exists($key,$paymentGateway) ?  $paymentGateway[$key] : [];
        }
        return $paymentGateway;
    }

}
