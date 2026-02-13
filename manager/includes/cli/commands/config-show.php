<?php

if (!$args) {
    $all = config();
    if (!is_array($all)) {
        cli_usage('No config available.');
    }
    ksort($all);
    foreach ($all as $key => $value) {
        cli_kv($key, $value);
    }
    exit(0);
}

foreach ($args as $key) {
    $value = config($key, null);
    cli_kv($key, $value);
}
