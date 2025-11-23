<?php
$microdata = '<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href="[+url+]"><span itemprop="name">[+title+]</span></a></li>';
return [
    'list' => [
        'Outer' => '<ul class="topicpath">[+topics+]</ul>',
        'HomeTopic' => '<li class="home"><a href="[+url+]">[+title+]</a></li>',
        'CurrentTopic' => '<li class="current">[+title+]</li>',
        'ReferenceTopic' => '<li>[+title+]</li>',
        'OtherTopic' => '<li><a href="[+url+]">[+title+]</a></li>',
        'Separator' => "\n"
    ],
    'bootstrap5' => [
        'Outer' => '<nav aria-label="breadcrumb"><ol class="breadcrumb">[+topics+]</ol></nav>',
        'HomeTopic' => '<li class="breadcrumb-item"><a href="[+url+]">[+title+]</a></li>',
        'CurrentTopic' => '<li class="breadcrumb-item active" aria-current="page">[+title+]</li>',
        'ReferenceTopic' => '<li class="breadcrumb-item"><a href="[+url+]">[+title+]</a></li>',
        'OtherTopic' => '<li class="breadcrumb-item"><a href="[+url+]">[+title+]</a></li>',
        'Separator' => "\n"
    ],
    'bootstrap' => [
        'Outer' => '<ol class="breadcrumb">[+topics+]</ol>',
        'HomeTopic' => '<li class="home"><a href="[+url+]">[+title+]</a></li>',
        'CurrentTopic' => '<li class="active">[+title+]</li>',
        'ReferenceTopic' => '<li>[+title+]</li>',
        'OtherTopic' => '<li><a href="[+url+]">[+title+]</a></li>',
        'Separator' => "\n"
    ],
    'microdata' => [
        'Outer' => '<ul itemscope itemtype="http://schema.org/BreadcrumbList">[+topics+]</ul>',
        'HomeTopic' => $microdata,
        'CurrentTopic' => '<li>[+title+]</li>',
        'ReferenceTopic' => $microdata,
        'OtherTopic' => $microdata,
        'Separator' => "\n"
    ],
    'raw' => [
        'Outer' => '[+topics+]',
        'HomeTopic' => '[+title+]',
        'CurrentTopic' => '[+title+]',
        'ReferenceTopic' => '[+title+]',
        'OtherTopic' => '[+title+]',
        'Separator' => ' > '
    ],
    'simple' => [
        'Outer' => '[+topics+]',
        'HomeTopic' => '<a href="[+url+]" class="home">[+title+]</a>',
        'CurrentTopic' => '[+title+]',
        'ReferenceTopic' => '[+title+]',
        'OtherTopic' => '<a href="[+url+]">[+title+]</a>',
        'Separator' => ' &gt; '
    ]
];
