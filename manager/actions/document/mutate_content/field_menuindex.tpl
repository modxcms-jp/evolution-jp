<table class="menuindex-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="menuindex-label" data-field-part="input">
            <div class="field-inline">
                [+menuindex+]
                <input type="button" value="&lt;"
                       onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();"/>
                <input type="button" value="&gt;"
                       onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();"/>
                [+resource_opt_menu_index_help+]
            </div>
        </td>
        <td class="menuindex-options" data-field="hidemenu">
            <span class="mutate-field-title" data-field-part="title">[+resource_opt_show_menu+]</span>&nbsp;
            <span data-field-part="value">[+hidemenu+]
            [+hidemenu_hidden+]</span>
            <span data-field-part="help">[+resource_opt_show_menu_help+]</span>
        </td>
    </tr>
</table>
