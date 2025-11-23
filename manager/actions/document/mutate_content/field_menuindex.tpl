<table class="menuindex-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="menuindex-label">
            [+menuindex+]
            <input type="button" value="&lt;"
                   onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();"/>
            <input type="button" value="&gt;"
                   onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();"/>
            [+resource_opt_menu_index_help+]
        </td>
        <td class="menuindex-options">
            <span class="mutate-field-title">[+resource_opt_show_menu+]</span>&nbsp;
            [+hidemenu+]
            [+hidemenu_hidden+]
            [+resource_opt_show_menu_help+]
        </td>
    </tr>
</table>
