<?php
if (!isset($modx)) {
    define('MODX_API_MODE', true);
    $self = 'manager/media/captcha/veriword.php';
    $base_path = str_replace(['\\', $self], ['/', ''], __FILE__);
    include_once('manager/includes/document.parser.class.inc.php');
    $modx = new DocumentParser;
    $modx->getSettings();
}
$vword = new VeriWord();
$word = $vword->pick_word($modx->config['captcha_words']);
$vword->set_veriword($word);
$vword->output_image($word, 135, 43);
exit;

/*
 Author: Huda M Elmatsani
 Email : justhuda at netrada.co.id

 25/07/2004
 Copyright (c) 2004 Huda M Elmatsani All rights reserved.
 This program is free for any purpose use.
*/

class VeriWord
{
    /* path to font directory*/
    public $font_path;

    function __construct()
    {
        $vword_base_path = str_replace('\\', '/', __DIR__) . '/';
        $this->font_path = $vword_base_path . 'ftb_____.ttf';
        $this->bg_image = $vword_base_path . 'noise.jpg';
    }

    function set_veriword($word)
    {
        $_SESSION['veriword'] = $word;
    }

    function output_image($word, $img_width = 200, $img_height = 80)
    {
        $img = $this->draw_image($word, $img_width, $img_height);
        header('Content-type: image/jpeg');
        imagejpeg($img);
    }

    function pick_word($words = 'abc,def')
    {
        $arr_words = explode(',', $words);
        return (string)$arr_words[array_rand($arr_words)] . mt_rand(10, 999);
    }

    function draw_text($word, $img_width = 200, $img_height = 80)
    {
        $text_angle = mt_rand(-9, 9);
        /* calculate text width and height */
        $box = imagettfbbox(30, $text_angle, $this->font_path, $word);
        $text_width = $box[2] - $box[0]; //text width
        $text_height = $box[5] - $box[3]; //text height

        /* adjust text size */
        $text_size = round((20 * $img_width) / $text_width);

        /* recalculate text width and height */
        $box = imagettfbbox($text_size, $text_angle, $this->font_path, $word);
        $text_width = $box[2] - $box[0]; //text width
        $text_height = $box[5] - $box[3]; //text height

        /* calculate center position of text */
        $text_x = ($img_width - $text_width) / 2;
        $text_y = ($img_height - $text_height) / 2;

        /* create canvas for text drawing */
        $im_text = imagecreate($img_width, $img_height);
        $bg_color = imagecolorallocate($im_text, 255, 255, 255);

        /* pick color for text */
        $text_color = imagecolorallocate($im_text, 10, 10, 10);

        /* draw text into canvas */
        imagettftext(
            $im_text,
            $text_size,
            $text_angle,
            $text_x,
            $text_y,
            $text_color,
            $this->font_path,
            $word);

        /* remove background color */
        imagecolortransparent($im_text, $bg_color);
        return $im_text;
    }

    function draw_image($word, $img_width = 200, $img_height = 80)
    {
        /* create "noise" background image from your image stock*/
        $noise_img = @imagecreatefromjpeg($this->bg_image);
        $noise_width = imagesx($noise_img);
        $noise_height = imagesy($noise_img);

        /* resize the background image to fit the size of image output */
        $image = imagecreatetruecolor($img_width, $img_height);
        imagecopyresampled(
            $image,
            $noise_img,
            0, 0, 0, 0,
            $img_width,
            $img_height,
            $noise_width,
            $noise_height
        );
        /* put text image into background image */
        imagecopymerge(
            $image,
            $this->draw_text($word, $img_width, $img_height),
            0, 0, 0, 0,
            $img_width,
            $img_height,
            50
        );
        return $image;
    }
}
