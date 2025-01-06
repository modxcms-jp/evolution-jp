<!-- Site Settings -->
<div class="tab-page" id="tabPage2">
    <h2 class="tab"><?= lang('settings_site') ?></h2>
    <table class="settings">
        <tr>
            <th><?= lang('sitestatus_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('online'),
                    form_radio(
                        'site_status',
                        1,
                        config('site_status') == 1
                    )
                ) ?><br />
                <?= wrap_label(
                    lang('offline'),
                    form_radio(
                        'site_status',
                        0,
                        config('site_status') == 0
                    )
                ) ?><br />
                <?= lang('sitestatus_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('sitename_title') ?></th>
            <td>
                <?= form_text_tag('site_name', config('site_name')) ?><br />
                <?= lang('sitename_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('site_slogan_title') ?></th>
            <td>
                <textarea
                    name="site_slogan"
                    id="site_slogan"
                    style="display:block;width:300px;height:4em;"><?= config('site_slogan') ?></textarea>
                <?= lang('site_slogan_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('site_url_title') ?></th>
            <td>
                <?= form_text('site_url') ?><br />
                <?= evo()->parseText(
                    lang('site_url_message'),
                    array('MODX_SITE_URL' => MODX_SITE_URL)
                )
                ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('base_url_title') ?></th>
            <td>
                <?= form_text('base_url') ?><br />
                <?php
                echo evo()->parseText(
                    lang('base_url_message'),
                    array('MODX_BASE_URL' => MODX_BASE_URL)
                )
                ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('sitestart_title') ?></th>
            <td>
                <?= form_text('site_start', 10) ?><br />
                <?= lang('sitestart_message') ?></td>
        </tr>
        <tr>
            <th><?= lang('errorpage_title') ?></th>
            <td>
                <?= form_text('error_page', 10) ?><br />
                <?= lang('errorpage_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('unauthorizedpage_title') ?></th>
            <td>
                <?= form_text('unauthorized_page', 10) ?><br />
                <?= lang('unauthorizedpage_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('siteunavailable_page_title') ?></th>
            <td>
                <?= form_text('site_unavailable_page', 10) ?><br />
                <?= lang('siteunavailable_page_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('siteunavailable_title') ?><br />
                <p>
                    <?= lang('update_settings_from_language') ?>
                </p>
                <select
                    name="reload_site_unavailable"
                    id="reload_site_unavailable_select"
                    onchange="confirmLangChange(this, 'siteunavailable_message_default', 'site_unavailable_message_textarea');">
                    <?= get_lang_options('siteunavailable_message_default') ?>
                </select>
            </th>
            <td>
                <textarea
                    name="site_unavailable_message"
                    id="site_unavailable_message_textarea"
                    style="width:100%; height: 120px;display:block;"><?php
                                                                        echo config('site_unavailable_message', lang('siteunavailable_message_default'));
                                                                        ?></textarea>
                <input
                    type="hidden"
                    name="siteunavailable_message_default"
                    id="siteunavailable_message_default_hidden"
                    value="<?= addslashes(lang('siteunavailable_message_default')) ?>" />
                <?= lang('siteunavailable_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('custom_contenttype_title') ?></th>
            <td>
                <?= form_text('txt_custom_contenttype', 100, 'style="width:200px;"') ?>
                <input
                    type="button"
                    value="<?= lang('add') ?>"
                    onclick='addContentType()' /><br />
                <table>
                    <tr>
                        <td valign="top">
                            <select
                                name="lst_custom_contenttype"
                                style="width:200px;"
                                size="5">
                                <?php
                                foreach (explode(',', config('custom_contenttype')) as $v) {
                                    echo '<option value="' . $v . '">' . $v . "</option>\n";
                                }
                                ?>
                            </select>
                            <input
                                name="custom_contenttype"
                                type="hidden"
                                value="<?= config('custom_contenttype') ?>" />
                        </td>
                        <td valign="top">
                            &nbsp;<input
                                name="removecontenttype"
                                type="button"
                                value="<?= lang('remove') ?>"
                                onclick='removeContentType()' />
                        </td>
                    </tr>
                </table>
                <br />
                <?= lang('custom_contenttype_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('xhtml_urls_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('yes'),
                    form_radio(
                        'xhtml_urls',
                        1,
                        config('xhtml_urls')
                    )
                ); ?><br />
                <?= wrap_label(
                    lang('no'),
                    form_radio(
                        'xhtml_urls',
                        0,
                        !config('xhtml_urls')
                    )
                ); ?><br />
                <?= lang('xhtml_urls_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('charset_title') ?></th>
            <td>
                <select
                    name="modx_charset"
                    size="1"
                    class="inputBox"
                    style="display:block;width:250px;">
                    <?php include(MODX_CORE_PATH . 'charsets.php'); ?>
                </select>
                <?= lang('charset_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('serveroffset_title') ?></th>
            <td>
                <select name="server_offset_time" size="1" class="inputBox">
                    <?php
                    for ($i = -24; $i < 25; $i++) {
                        $seconds = $i * 60 * 60;
                        echo sprintf(
                            '<option value="%s" %s>%s</option>',
                            $seconds,
                            $seconds == config('server_offset_time') ? "selected='selected'" : '',
                            $i
                        );
                    }
                    ?>
                </select><br />
                <?php printf(
                    lang('serveroffset_message'),
                    strftime('%H:%M:%S', time()),
                    strftime(
                        '%H:%M:%S',
                        time() + config('server_offset_time')
                    )
                ); ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('server_protocol_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('server_protocol_http'),
                    form_radio(
                        'server_protocol',
                        'http',
                        config('server_protocol') === 'http'
                    )
                ) ?><br />
                <?= wrap_label(
                    lang('server_protocol_https'),
                    form_radio(
                        'server_protocol',
                        'https',
                        config('server_protocol') === 'https'
                    )
                ) ?><br />
                <?= lang('server_protocol_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('track_visitors_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('yes'),
                    form_radio(
                        'track_visitors',
                        1,
                        config('track_visitors') == 1
                    )
                ) ?><br />
                <?= wrap_label(
                    lang('no'),
                    form_radio(
                        'track_visitors',
                        0,
                        config('track_visitors') == 0
                    )
                ) ?><br />
                <?= lang('track_visitors_message') ?>
            </td>
        </tr>
        <tr class="row1" style="border-bottom:none;">
            <td colspan="2" style="padding:0;">
                <?php
                // invoke OnSiteSettingsRender event
                $evtOut = evo()->invokeEvent('OnSiteSettingsRender');
                if (is_array($evtOut)) {
                    echo implode('', $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>