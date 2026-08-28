<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Require a guest token
    |--------------------------------------------------------------------------
    |
    | `guest_id` is a sequential integer and therefore trivially guessable. The
    | token issued by POST /api/v1/auth/guest/request is the real credential.
    |
    | Leave this false while older mobile clients that only know about
    | `guest_id` are still in the wild. Once every shipped client sends the
    | `guest-token` header, set GUEST_REQUIRE_TOKEN=true to reject the legacy
    | id-only path outright.
    |
    */

    'require_token' => (bool) env('GUEST_REQUIRE_TOKEN', false),

];
