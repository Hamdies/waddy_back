<?php

return [
    /*
     * A coupon code named in the birthday push ("Use code PETBDAY for a
     * birthday treat"). Empty: the push sends wishes only. Create the coupon
     * in Admin › Coupons first; this only mentions it.
     */
    'birthday_coupon_code' => env('PETS_BIRTHDAY_COUPON', null),
];
