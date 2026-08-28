<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed image hosts
    |--------------------------------------------------------------------------
    |
    | Comma-separated hostnames the image proxy may fetch from. Subdomains of a
    | listed host are allowed too.
    |
    | Empty (the default) falls back to blocking private and reserved IP ranges,
    | which stops the obvious SSRF but still permits any public host. Naming the
    | handful of CDNs actually in use is stricter and also closes the DNS
    | rebinding window — prefer it once you know what the apps request.
    |
    */

    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IMAGE_PROXY_ALLOWED_HOSTS', ''))
    ))),

];
