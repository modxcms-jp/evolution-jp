<script>
    jQuery(function () {
        if (jQuery('input[name="friendly_urls"]:checked').val() == 0) {
            jQuery('.furlRow').hide();
        }
        jQuery('input[name="friendly_urls"]').change(function () {
            if (jQuery(this).val() == 1) {
                jQuery('tr.furlRow').fadeIn();
            } else {
                jQuery('tr.furlRow').fadeOut();
            }
        });
    });
</script>
<div class="tab-page" id="tabPage3">
    <h2 class="tab"><?php echo $_lang["settings_furls"] ?></h2>
    <table class="settings">
        <tr>
            <th><?php echo $_lang["friendlyurls_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('friendly_urls', '1', $friendly_urls == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('friendly_urls', '0', $friendly_urls == '0')); ?><br/>
                <?php echo $_lang["friendlyurls_message"] ?>
            </td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang["friendlyurlsprefix_title"] ?></th>
            <td>
                <?php echo form_text('friendly_url_prefix', 50); ?><br/>
                <?php echo $_lang["friendlyurlsprefix_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang["friendlyurlsuffix_title"] ?></th>
            <td>
                <?php echo form_text('friendly_url_suffix', 50); ?><br/>
                <?php echo $_lang["friendlyurlsuffix_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang['make_folders_title'] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('make_folders', '1', $make_folders == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('make_folders', '0', $make_folders == '0')); ?><br/>
                <?php echo $_lang["make_folders_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang['mutate_settings.dynamic.php4']; ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('suffix_mode', '1', $suffix_mode == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('suffix_mode', '0', $suffix_mode == '0')); ?><br/>
                <?php echo $_lang['mutate_settings.dynamic.php5']; ?></td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang["friendly_alias_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('friendly_alias_urls', '1', $friendly_alias_urls == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('friendly_alias_urls', '0', $friendly_alias_urls == '0')); ?><br/>
                <?php echo $_lang["friendly_alias_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang["use_alias_path_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('use_alias_path', '1', $use_alias_path == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('use_alias_path', '0', $use_alias_path == '0')); ?><br/>
                <?php echo $_lang["use_alias_path_message"] ?>
            </td>
        </tr>
        <tr class='furlRow'>
            <th><?php echo $_lang["duplicate_alias_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('allow_duplicate_alias', '1', $allow_duplicate_alias == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('allow_duplicate_alias', '0', $allow_duplicate_alias == '0')); ?><br/>
                <?php echo $_lang["duplicate_alias_message"] ?>
            </td>
        </tr>
        <tr class="furlRow">
            <th><?php echo $_lang["automatic_alias_title"] ?></th>
            <td>
                <?php echo wrap_label('pagetitle', form_radio('automatic_alias', '1', $automatic_alias == '1')); ?><br/>
                <?php echo wrap_label('numbering in each folder',
                    form_radio('automatic_alias', '2', $automatic_alias == '2')); ?><br/>
                <?php echo wrap_label($_lang["disabled"],
                    form_radio('automatic_alias', '0', $automatic_alias == '0')); ?><br/>
                <?php echo $_lang["automatic_alias_message"] ?>
            </td>
        </tr>
        <tr class="row1" style="border-bottom:none;">
            <td colspan="2">
                <?php
                // invoke OnFriendlyURLSettingsRender event
                $evtOut = evo()->invokeEvent("OnFriendlyURLSettingsRender");
                if (is_array($evtOut)) {
                    echo implode("", $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>
