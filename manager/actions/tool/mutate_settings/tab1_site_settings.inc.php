<!-- Site Settings -->
<div class="tab-page" id="tabPage2">
    <h2 class="tab"><?php echo lang('settings_site') ?></h2>
    <table class="settings">
        <tr>
            <th><?php echo lang('sitestatus_title') ?></th>
            <td>
                <?php echo wrap_label(
                    lang('online')
                    , form_radio(
                        'site_status'
                        , 1
                        , config('site_status') == 1
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('offline')
                    , form_radio(
                        'site_status'
                        , 0
                        , config('site_status') == 0
                    )
                ); ?><br/>
                <?php echo lang('sitestatus_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('sitename_title'); ?></th>
            <td>
                <?php echo form_text_tag('site_name', config('site_name')); ?><br/>
                <?php echo lang('sitename_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('site_slogan_title'); ?></th>
            <td>
        <textarea
            name="site_slogan"
            id="site_slogan"
            style="display:block;width:300px;height:4em;"
        ><?php echo config('site_slogan'); ?></textarea>
                <?php echo lang('site_slogan_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('site_url_title'); ?></th>
            <td>
                <?php echo form_text('site_url'); ?><br/>
                <?php echo evo()->parseText(
                    lang('site_url_message')
                    , array('MODX_SITE_URL' => MODX_SITE_URL))
                ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('base_url_title'); ?></th>
            <td>
                <?php echo form_text('base_url'); ?><br/>
                <?php
                echo evo()->parseText(
                    lang('base_url_message')
                    , array('MODX_BASE_URL' => MODX_BASE_URL)
                )
                ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('sitestart_title'); ?></th>
            <td>
                <?php echo form_text('site_start', 10); ?><br/>
                <?php echo lang('sitestart_message'); ?></td>
        </tr>
        <tr>
            <th><?php echo lang('errorpage_title'); ?></th>
            <td>
                <?php echo form_text('error_page', 10); ?><br/>
                <?php echo lang('errorpage_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('unauthorizedpage_title'); ?></th>
            <td>
                <?php echo form_text('unauthorized_page', 10); ?><br/>
                <?php echo lang('unauthorizedpage_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('siteunavailable_page_title'); ?></th>
            <td>
                <?php echo form_text('site_unavailable_page', 10); ?><br/>
                <?php echo lang('siteunavailable_page_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('siteunavailable_title'); ?><br/>
                <p>
                    <?php echo lang('update_settings_from_language'); ?>
                </p>
                <select
                    name="reload_site_unavailable"
                    id="reload_site_unavailable_select"
                    onchange="confirmLangChange(this, 'siteunavailable_message_default', 'site_unavailable_message_textarea');"
                >
                    <?php echo get_lang_options('siteunavailable_message_default'); ?>
                </select>
            </th>
            <td>
        <textarea
            name="site_unavailable_message"
            id="site_unavailable_message_textarea"
            style="width:100%; height: 120px;display:block;"
        ><?php
            echo config('site_unavailable_message', lang('siteunavailable_message_default'));
            ?></textarea>
                <input
                    type="hidden"
                    name="siteunavailable_message_default"
                    id="siteunavailable_message_default_hidden"
                    value="<?php echo addslashes(lang('siteunavailable_message_default')); ?>"
                />
                <?php echo lang('siteunavailable_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('custom_contenttype_title'); ?></th>
            <td>
                <?php echo form_text('txt_custom_contenttype', 100, 'style="width:200px;"'); ?>
                <input
                    type="button"
                    value="<?php echo lang('add'); ?>"
                    onclick='addContentType()'
                /><br/>
                <table>
                    <tr>
                        <td valign="top">
                            <select
                                name="lst_custom_contenttype"
                                style="width:200px;"
                                size="5"
                            >
                                <?php
                                foreach (explode(',', config('custom_contenttype')) as $v) {
                                    echo '<option value="' . $v . '">' . $v . "</option>\n";
                                }
                                ?>
                            </select>
                            <input
                                name="custom_contenttype"
                                type="hidden"
                                value="<?php echo config('custom_contenttype'); ?>"
                            />
                        </td>
                        <td valign="top">
                            &nbsp;<input
                                name="removecontenttype"
                                type="button"
                                value="<?php echo lang('remove'); ?>"
                                onclick='removeContentType()'
                            />
                        </td>
                    </tr>
                </table>
                <br/>
                <?php echo lang('custom_contenttype_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('xhtml_urls_title'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('yes')
                    , form_radio(
                        'xhtml_urls'
                        , 1
                        , config('xhtml_urls')
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('no')
                    , form_radio(
                        'xhtml_urls'
                        , 0
                        , !config('xhtml_urls')
                    )
                ); ?><br/>
                <?php echo lang('xhtml_urls_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('charset_title'); ?></th>
            <td>
                <select
                    name="modx_charset"
                    size="1"
                    class="inputBox"
                    style="display:block;width:250px;"
                >
                    <?php include(MODX_CORE_PATH . 'charsets.php'); ?>
                </select>
                <?php echo lang('charset_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('serveroffset_title'); ?></th>
            <td>
                <select name="server_offset_time" size="1" class="inputBox">
                    <?php
                    for ($i = -24; $i < 25; $i++) {
                        $seconds = $i * 60 * 60;
                        echo sprintf(
                            '<option value="%s" %s>%s</option>'
                            , $seconds
                            , $seconds == config('server_offset_time') ? "selected='selected'" : ''
                            , $i
                        );
                    }
                    ?>
                </select><br/>
                <?php printf(
                    lang('serveroffset_message')
                    , strftime('%H:%M:%S', time())
                    , strftime('%H:%M:%S', time() + config('server_offset_time')
                    )
                ); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('server_protocol_title'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('server_protocol_http')
                    , form_radio(
                        'server_protocol'
                        , 'http'
                        , config('server_protocol') === 'http'
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('server_protocol_https')
                    , form_radio(
                        'server_protocol'
                        , 'https'
                        , config('server_protocol') === 'https'
                    )
                ); ?><br/>
                <?php echo lang('server_protocol_message'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('track_visitors_title'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('yes')
                    , form_radio(
                        'track_visitors'
                        , 1
                        , config('track_visitors') == 1
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('no')
                    , form_radio(
                    'track_visitors'
                    , 0
                    , config('track_visitors') == 0)); ?><br/>
                <?php echo lang('track_visitors_message'); ?>
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
