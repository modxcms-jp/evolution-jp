<option value="euc-jp" <?php echo $modx_charset=="euc-jp" ? "selected='selected'" : "" ; ?> >Japanese (EUC) - euc-jp</option>
<option value="iso-2022-jp" <?php echo $modx_charset=="iso-2022-jp" ? "selected='selected'" : "" ; ?> >Japanese (JIS) - iso-2022-jp</option>
<option value="shift_jis" <?php echo $modx_charset=="shift_jis" ? "selected='selected'" : "" ; ?> >Japanese (Shift-JIS) - shift_jis</option>
<option value="unicode" <?php echo $modx_charset=="unicode" ? "selected='selected'" : "" ; ?> >Unicode - unicode</option>
<option value="UTF-8" <?php echo $modx_charset=="UTF-8"? "selected='selected'" : "" ; ?> >Unicode (UTF-8) - utf-8</option>
<option value="us-ascii" <?php echo $modx_charset=="us-ascii" ? "selected='selected'" : "" ; ?> >US-ASCII - us-ascii</option>
<option value="iso-8859-1" <?php echo ($modx_charset=="iso-8859-1"  || !isset($modx_charset) /* sets default */) ? "selected='selected'" : "" ; ?> >Western European (ISO) - iso-8859-1</option>
