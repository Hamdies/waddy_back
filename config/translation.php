<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-write missing translation keys
    |--------------------------------------------------------------------------
    |
    | When translate() meets a key that is not in the language file, it can
    | append it so the key gets picked up for translation later.
    |
    | This must stay OFF in production. The write rewrites the whole language
    | file — every key, via var_export — on any request that hits a missing key,
    | and two concurrent requests can interleave and truncate it. That file is
    | include()d on every request, so a truncated write takes the whole site
    | down, not just the one request.
    |
    | Turning it off changes nothing a visitor sees: translate() already returns
    | the humanised key in this branch, and the write only persists it.
    |
    | Developers can set TRANSLATION_AUTOWRITE=true locally to collect new keys,
    | then commit the language file.
    |
    */

    'autowrite' => (bool) env('TRANSLATION_AUTOWRITE', false),

];
