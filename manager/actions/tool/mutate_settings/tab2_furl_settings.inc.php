<script>
    Evo.ready(function () {
        // フレンドリー URL を使うときだけ関連の行を出す
        const setRows = function (show) {
            document.querySelectorAll('tr.furlRow').forEach(function (row) {
                row.style.display = show ? '' : 'none';
            });
        };
        const checked = document.querySelector('input[name="friendly_urls"]:checked');
        if (checked && checked.value == 0) {
            setRows(false);
        }
        document.querySelectorAll('input[name="friendly_urls"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                setRows(radio.value == 1);
            });
        });
    });
</script>
<div class="tab-page" id="tabPage3">
    <h2 class="tab"><?= $_lang["settings_furls"] ?></h2>
    <table class="settings">
        <tr>
            <th><?= $_lang["friendlyurls_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('friendly_urls', '1', $friendly_urls == '1')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('friendly_urls', '0', $friendly_urls == '0')); ?><br/>
                <?= $_lang["friendlyurls_message"] ?>
            </td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang["friendlyurlsprefix_title"] ?></th>
            <td>
                <?= form_text('friendly_url_prefix', 50) ?><br/>
                <?= $_lang["friendlyurlsprefix_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang["friendlyurlsuffix_title"] ?></th>
            <td>
                <?= form_text('friendly_url_suffix', 50) ?><br/>
                <?= $_lang["friendlyurlsuffix_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang['make_folders_title'] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('make_folders', '1', $make_folders == '1')) ?><br/>
                <?= wrap_label($_lang["no"], form_radio('make_folders', '0', $make_folders == '0')) ?><br/>
                <?= $_lang["make_folders_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang['suffix_mode_title'] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('suffix_mode', '1', $suffix_mode == '1')) ?><br/>
                <?= wrap_label($_lang["no"], form_radio('suffix_mode', '0', $suffix_mode == '0')) ?><br/>
                <?= $_lang['suffix_mode_message'] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang["friendly_alias_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('friendly_alias_urls', '1', $friendly_alias_urls == '1')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('friendly_alias_urls', '0', $friendly_alias_urls == '0')); ?><br/>
                <?= $_lang["friendly_alias_message"] ?></td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang["use_alias_path_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('use_alias_path', '1', $use_alias_path == '1')) ?><br/>
                <?= wrap_label($_lang["no"], form_radio('use_alias_path', '0', $use_alias_path == '0')) ?><br/>
                <?= $_lang["use_alias_path_message"] ?>
            </td>
        </tr>
        <tr class='furlRow'>
            <th><?= $_lang["duplicate_alias_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('allow_duplicate_alias', '1', $allow_duplicate_alias == '1')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('allow_duplicate_alias', '0', $allow_duplicate_alias == '0')); ?><br/>
                <?= $_lang["duplicate_alias_message"] ?>
            </td>
        </tr>
        <tr class="furlRow">
            <th><?= $_lang["automatic_alias_title"] ?></th>
            <td>
                <?= wrap_label('pagetitle', form_radio('automatic_alias', '1', $automatic_alias == '1')) ?><br/>
                <?= wrap_label('numbering in each folder',
                    form_radio('automatic_alias', '2', $automatic_alias == '2')); ?><br/>
                <?= wrap_label($_lang["disabled"],
                    form_radio('automatic_alias', '0', $automatic_alias == '0')); ?><br/>
                <?= $_lang["automatic_alias_message"] ?>
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
