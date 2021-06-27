<?php
if (anyv('quickmanager') !== 'logout') {
    return;
}
// Redirect to document id
if ($this->logout != 'manager') {
    evo()->sendRedirect(
        evo()->makeUrl(anyv('logoutid'), '', '', 'full'),
        0,
        'REDIRECT_HEADER',
        'HTTP/1.1 301 Moved Permanently'
    );
}
