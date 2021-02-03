<!-- Site Settings -->
<div class="tab-page" id="tabPageCache">
    <h2 class="tab">キャッシュ設定</h2>
    <table class="settings">
        <tr>
            <th><?php echo lang('setting_cache_type'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('mutate_settings.dynamic.php1')
                    , form_radio(
                        'cache_type'
                        , 1
                        , config('cache_type') == 1
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('mutate_settings.dynamic.php2')
                    , form_radio(
                        'cache_type'
                        , 2
                        , config('cache_type') == 2
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('mutate_settings.dynamic.php3')
                    , form_radio(
                        'cache_type'
                        , 0
                        , config('cache_type') == 0
                    )
                ); ?><br/>
                <?php echo lang('setting_cache_type_desc'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('setting_disable_cache_at_login'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('enabled')
                    , form_radio(
                        'disable_cache_at_login'
                        , 0
                        , config('disable_cache_at_login') == 0
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('disabled')
                    , form_radio(
                        'disable_cache_at_login'
                        , 1
                        , config('disable_cache_at_login') == 1
                    )
                ); ?><br/>
                <?php echo lang('setting_disable_cache_at_login_desc'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('setting_individual_cache'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('enabled')
                    , form_radio(
                        'individual_cache'
                        , 1
                        , config('individual_cache') == 1
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('disabled')
                    , form_radio(
                        'individual_cache'
                        , 0
                        , config('individual_cache') == 0
                    )
                ); ?><br/>
                <?php echo lang('setting_individual_cache_desc'); ?>
            </td>
        </tr>
        <tr>
            <th><?php echo lang('setting_conditional_get'); ?></th>
            <td>
                <?php echo wrap_label(
                    lang('enabled')
                    , form_radio(
                        'conditional_get'
                        , 1
                        , config('conditional_get') == 1
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('disabled')
                    , form_radio(
                        'conditional_get'
                        , 0
                        , config('conditional_get') == 0
                    )
                ); ?><br/>
                <?php echo lang('setting_conditional_get_desc'); ?>
            </td>
        </tr>
        <tr>
            <th>旧式のキャッシュ機構</th>
            <td>
                <?php echo wrap_label(
                    lang('enabled')
                    , form_radio(
                        'legacy_cache'
                        , 1
                        , config('legacy_cache') == 1
                    )
                ); ?><br/>
                <?php echo wrap_label(
                    lang('disabled')
                    , form_radio(
                        'legacy_cache'
                        , 0
                        , config('legacy_cache') == 0
                    )
                ); ?><br/>
                古いスニペット・プラグインは<a
                    href="https://www.google.co.jp/search?q=modx+aliasListing+ddocumentMap+ocumentListing"
                    target="_blank">旧式のキャッシュ機構</a>が有効でないと動作しないことがあります。その場合はこの設定を有効にしてください。このキャッシュ機構はサイトの規模が大きくなると負荷が高くなるため注意が必要です。
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
