<?php

    return getSummary($content,$limit,$delim);

    function getSummary($content='', $limit=100, $delim='')
    {
        global $modx;
        if($delim==='') $delim = $modx->config['manager_language']==='japanese-utf8' ? '。' : '.';
        $limit = intval($limit);
        
        if($content==='' && isset($modx->documentObject['content']))
            $content = $modx->documentObject['content'];
        
        $content = $modx->filter->parseDocumentSource($content);
        $content = strip_tags($content);
        $content = str_replace(array("\r\n","\r","\n","\t",'&nbsp;'),' ',$content);
        if(preg_match('/\s+/',$content))
            $content = preg_replace('/\s+/',' ',$content);
        $content = trim($content);
        
        $pos = $modx->filter->strpos($content, $delim);
        
        if($pos!==false && $pos<$limit)
        {
            $_ = explode($delim, $content);
            $text = '';
            foreach($_ as $value)
            {
                if($limit <= $modx->filter->strlen($text.$value.$delim)) break;
                $text .= $value.$delim;
            }
        }
        else $text = $content;
        
        if($limit<$modx->filter->strlen($text) && strpos($text,' ')!==false)
        {
            $_ = explode(' ', $text);
            $text = '';
            foreach($_ as $value)
            {
                if($limit <= $modx->filter->strlen($text.$value.' ')) break;
                $text .= $value . ' ';
            }
            if($text==='') $text = $content;
        }
        
        if($limit < $modx->filter->strlen($text)) $text = $modx->filter->substr($text, 0, $limit);
        
        return $text;
    }
