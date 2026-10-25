<?php

return [
    /*
    | Comma-separated client IP addresses or CIDR ranges allowed to access
    | the Filament admin panel. An empty list leaves access unrestricted.
    */
    'admin_allowed_ips' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('ADMIN_ALLOWED_IPS', '')),
    ))),
];
