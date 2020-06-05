<table cellpadding="0" cellspacing="0" style="width:333px;">
    <tr>
        <td style="white-space:nowrap;">
            [+menuindex+]
            <input type="button" value="&lt;"
                   onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();"/>
            <input type="button" value="&gt;"
                   onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();"/>
            [+resource_opt_menu_index_help+]
        </td>
        <td style="text-align:right;">
            <span class="warning">[+resource_opt_show_menu+]</span>&nbsp;
            [+hidemenu+]
            [+hidemenu_hidden+]
            [+resource_opt_show_menu_help+]
        </td>
    </tr>
</table>
