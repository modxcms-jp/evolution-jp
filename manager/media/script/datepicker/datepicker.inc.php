<?php

class DATEPICKER
{
    public function getDP()
    {
        global $modx_lang_attribute;

        return parseText(
            file_get_contents(__DIR__ . '/datepicker.tpl'),
            [
                'format' => js_literal(evo()->config('datetime_format', 'YYYY/mm/dd')),
                'lang' => js_literal($modx_lang_attribute === 'ja' ? 'ja' : 'en'),
            ]
        );
    }
}
