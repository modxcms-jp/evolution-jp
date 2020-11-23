<?php
$microdata = '<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href="[+url+]"><span itemprop="name">[+title+]</span></a></li>';
return array(
    'list'=> array(
        'Outer'          => '<ul class="topicpath">[+topics+]</ul>',
        'HomeTopic'      => '<li class="home"><a href="[+url+]">[+title+]</a></li>',
        'CurrentTopic'   => '<li class="current">[+title+]</li>',
        'ReferenceTopic' => '<li>[+title+]</li>',
        'OtherTopic'     => '<li><a href="[+url+]">[+title+]</a></li>',
        'Separator'      => "\n"
    ),
    'bootstrap'=> array(
        'Outer'          => '<ol class="breadcrumb">[+topics+]</ol>',
        'HomeTopic'      => '<li class="home"><a href="[+url+]">[+title+]</a></li>',
        'CurrentTopic'   => '<li class="active">[+title+]</li>',
        'ReferenceTopic' => '<li>[+title+]</li>',
        'OtherTopic'     => '<li><a href="[+url+]">[+title+]</a></li>',
        'Separator'      => "\n"
    ),
    'microdata'=> array(
        'Outer'          => '<ul itemscope itemtype="http://schema.org/BreadcrumbList">[+topics+]</ul>',
        'HomeTopic'      => $microdata,
        'CurrentTopic'   => '<li>[+title+]</li>',
        'ReferenceTopic' => $microdata,
        'OtherTopic'     => $microdata,
        'Separator'      => "\n"
    ),
    'simple' => array(
        'Outer'          => '[+topics+]',
        'HomeTopic'      => '<a href="[+url+]" class="home">[+title+]</a>',
        'CurrentTopic'   => '[+title+]',
        'ReferenceTopic' => '[+title+]',
        'OtherTopic'     => '<a href="[+url+]">[+title+]</a>',
        'Separator'      => ' &gt; '
    )
);
