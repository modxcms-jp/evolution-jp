<?php
return [
    // BASIC authentication credentials to protect the installer. Leave both empty to disable BASIC authentication.
    'basic_auth' => [
        'user' => 'installer',
        'password' => 'change-me',
    ],
    // IP addresses allowed to access the installer. Leave empty to allow access from any address.
    'allowed_ips' => [
        '127.0.0.1',
    ],
];
