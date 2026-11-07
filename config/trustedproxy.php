<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Laravel's TrustProxies middleware reads this value. Behind a TLS-terminating
    | proxy (for example Render) set TRUSTED_PROXIES to "*" or to a comma-separated
    | list of proxy IPs/CIDR ranges so the original HTTPS scheme is detected.
    | When it is not set, no proxy is trusted. Which forwarded headers are read
    | is configured in bootstrap/app.php.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
