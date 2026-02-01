<?php

$chkagree = postv('chkagree', sessionv('chkagree'));
$isUpgrade = sessionv('is_upgradeable');
if ($isUpgrade) {
    $chkagree = 1;
    $_SESSION['chkagree'] = 1;
}

if (sessionv('prevAction') === 'options') {
    $_SESSION['installdata'] = postv('installdata', '');
    $_SESSION['template']    = postv('template', []);
    $_SESSION['tv']          = postv('tv', []);
    $_SESSION['chunk']       = postv('chunk', []);
    $_SESSION['snippet']     = postv('snippet', []);
    $_SESSION['plugin']      = postv('plugin', []);
    $_SESSION['module']      = postv('module', []);
    if (sessionv('is_upgradeable')) {
        $_SESSION['convert_to_utf8mb4'] = postv('convert_to_utf8mb4', '1') === '0' ? 0 : 1;
    }
}

$preinstallLangKey = $isUpgrade ? 'preupdate_validation' : 'preinstall_validation';
$summaryCheckLangKey = $isUpgrade ? 'summary_update_check' : 'summary_setup_check';
echo '<h2>' . lang($preinstallLangKey) . '</h2>';
echo '<h3>' . lang($summaryCheckLangKey) . '</h3>';
echo p(lang('summary_path_config_note'));
$errors = 0;

// check PHP version

if (version_compare(phpversion(), '5.3.0') < 0) {
    $_ = echo_failed() . lang('you_running_php') . phpversion() . lang('modx_requires_php');
    $errors += 1;
} else {
    $_ = echo_ok();
}
echo p($_ . lang('checking_php_version'));

// check sessions
if (sessionv('test') != 1) {
    echo p(echo_failed() . lang('checking_sessions'));
    $errors += 1;
}

// check directories
// cache exists?
if (!is_dir(rtrim(MODX_CACHE_PATH, '/')) && !mkd(MODX_CACHE_PATH)) {
    echo p(echo_failed() . lang('checking_if_cache_exist'));
    $errors += 1;
}

// cache writable?
if (!is_writable(rtrim(MODX_CACHE_PATH, '/'))) {
    $_ = echo_failed();
    $errors += 1;
} else {
    $_ = echo_ok();
    mkd(MODX_CACHE_PATH . 'rss');
}
echo p($_ . lang('checking_if_cache_writable'));

if (is_writable(rtrim(MODX_CACHE_PATH, '/'))) {
    // cache files writable?
    if (!is_file(MODX_CACHE_PATH . 'siteCache.idx.php')) {
        // make an attempt to create the file
        file_put_contents(MODX_CACHE_PATH . 'siteCache.idx.php', '<?php //MODX site cache file ?>');
    }
    if (!is_writable(MODX_CACHE_PATH . 'siteCache.idx.php')) {
        $_ =  echo_failed();
        $errors += 1;
    } else $_ =  echo_ok();
    echo p($_ . lang('checking_if_cache_file_writable'));

    file_put_contents(MODX_CACHE_PATH . 'basicConfig.php', '<?php $cacheRefreshTime=0; ?>');

    if (!is_writable(MODX_CACHE_PATH . 'basicConfig.php')) {
        $_ = echo_failed();
        $errors += 1;
    } else $_ = echo_ok();
    echo p($_ . lang('checking_if_cache_file2_writable'));
}

if (!is_dir(MODX_BASE_PATH . 'assets/images')) {
    if (!is_dir(MODX_BASE_PATH . 'content')) {
        echo p(echo_failed() . lang('checking_if_content_exists'));
        $errors += 1;
    }

    // cache writable?
    $dir_images = MODX_BASE_PATH . 'content/images';
    $dir_files  = MODX_BASE_PATH . 'content/files';
    $dir_media  = MODX_BASE_PATH . 'content/media';

    if (!is_writable(MODX_BASE_PATH . 'content')) {
        $_ = echo_failed();
        $errors += 1;
    } else {
        $_ = echo_ok();
        mkd($dir_images);
        mkd($dir_files);
        mkd($dir_media);
    }
    echo p($_ . lang('checking_if_content_writable'));

    if (is_writable(MODX_BASE_PATH . 'content')) {
        if (!is_dir($dir_images) || !is_dir($dir_files) || !is_dir($dir_media)) {
            echo p(echo_failed() . lang('checking_if_images_exist'));
            $errors += 1;
        } else {
            // File Browser directories writable?
            if (!is_writable($dir_images) || !is_writable($dir_files) || !is_writable($dir_media)) {
                $_ = echo_failed();
                $errors += 1;
            } else {
                $_ = echo_ok();
            }
            echo p($_ . lang('checking_if_images_writable'));
        }
    }
}

if (!is_dir(MODX_BASE_PATH . 'temp')) {
    echo p(echo_failed() . lang('checking_if_temp_exists'));
    $errors += 1;
}

// cache writable?

if (!is_writable(MODX_BASE_PATH . 'temp')) {
    $_ = echo_failed();
    $errors += 1;
} else {
    $_ = echo_ok();
    mkd(MODX_BASE_PATH . 'temp/export');
    mkd(MODX_BASE_PATH . 'temp/backup');
    if (is_dir(MODX_BASE_PATH . 'temp/backup')) {
        @file_put_contents(MODX_BASE_PATH . 'temp/backup/.htaccess', "order deny,allow\ndeny from all");
    }
}
echo p($_ . lang('checking_if_temp_writable'));

if (is_writable(MODX_BASE_PATH . 'temp')) {
    if (!is_dir(MODX_BASE_PATH . 'temp/export')) {
        echo p(echo_failed() . lang('checking_if_export_exists'));
        $errors += 1;
    }

    // export writable?
    if (!is_writable(MODX_BASE_PATH . 'temp/export')) {
        $_ = echo_failed();
        $errors += 1;
    } else $_ = echo_ok();
    echo p($_ . lang('checking_if_export_writable'));

    // backup exists?
    if (!is_dir(MODX_BASE_PATH . 'temp/backup')) {
        $errors += 1;
        echo p(echo_failed() . lang('checking_if_backup_exists'));
    }

    // backup writable?
    if (!is_writable(MODX_BASE_PATH . 'temp/backup')) {
        $_ = echo_failed();
        $errors += 1;
    } else $_ = echo_ok();
    echo p($_ . lang('checking_if_backup_writable'));
}

// config.inc.php writable?
$config_path = MODX_BASE_PATH . 'manager/includes/config.inc.php';

if (!is_file($config_path)) {
    // make an attempt to create the file
    file_put_contents($config_path, '<?php //MODX configuration file ?>');
}


@chmod($config_path, 0666);
$isWriteable = is_writable($config_path);
if (!$isWriteable) {
    if ($_SESSION['is_upgradeable'] == 0) {
        $_ = echo_failed() . '</p><p><strong>' . lang('config_permissions_note') . '</strong>';
    } else {
        $_ = echo_failed() . '</p><p><strong>' . lang('config_permissions_upg_note') . '</strong>';
    }
    $errors += 1;
} else  $_ = echo_ok();
echo p($_ . lang('checking_if_config_exist_and_writable'));

echo sprintf(
    '<p>%s %s <strong>%s%s </strong></p>',
    echo_ok(),
    lang('checking_sql_version'),
    lang('sql_version_is'),
    $modx->db->getVersion()
);

// Version and strict mode check end

// andrazk 20070416 - add install flag and disable manager login

if (is_writable(MODX_CACHE_PATH)) {
    // make an attempt to create the file
    file_put_contents(MODX_CACHE_PATH . 'installProc.inc.php', '<?php $installStartTime = ' . time() . '; ?>');
}

if ($errors > 0) {
?>
    <p>
        <?php
        echo '<strong>' . lang('setup_cannot_continue') . '</strong>';
        if ($errors > 1) {
            echo $errors . ' ';
            echo lang('errors');
            echo lang('please_correct_errors');
            echo lang('and_try_again_plural');
        } else {
            echo lang('error');
            echo lang('please_correct_error');
            echo lang('and_try_again');
        }
        echo lang('visit_forum');
        ?>
    </p>
<?php
}

echo p('&nbsp;');

$nextAction = $errors ? 'summary' : 'install';
$nextButton = $errors ? lang('retry') : ($isUpgrade ? lang('upgrade') : lang('install'));
$nextVisibility = $errors > 0 || $chkagree ? 'visible' : 'hidden';
$agreeToggle = $errors > 0 ? '' : " onclick=\"if(document.getElementById('chkagree').checked){document.getElementById('nextbutton').style.visibility='visible';}else{document.getElementById('nextbutton').style.visibility='hidden';}\"";
$nextButtonProgress = $nextAction === 'install'
    ? ($isUpgrade ? lang('upgrade_in_progress') : lang('install_in_progress'))
    : '';
?>
<form id="install" action="index.php" method="POST">
    <div>
        <input type="hidden" value="<?= $nextAction ?>" name="action" />
        <input type="hidden" value="1" name="options_selected" />
        <input type="hidden" name="prev_action" value="summary" />
    </div>

    <?php if (!$isUpgrade): ?>
        <h2><?= lang('agree_to_terms') ?></h2>
        <p>
            <input type="checkbox" value="1" id="chkagree" name="chkagree"
                   style="line-height:18px" <?= $chkagree ? 'checked="checked" ' : "" ?><?= $agreeToggle ?>/><label
                for="chkagree"
                style="display:inline;float:none;line-height:18px;"> <?= lang('iagree_box') ?> </label>
        </p>
    <?php endif; ?>
        <p class="buttonlinks">
            <a href="javascript:void(0);" class="prev"
               title="<?= lang('btnback_value') ?>"><span><?= lang('btnback_value') ?></span></a>
            <a href="javascript:void(0);" class="next" id="nextbutton" title="<?= $nextButton ?>"
               style="visibility:<?= $nextVisibility ?>"><span><?= $nextButton ?></span></a>
        </p>
    </form>
    <script type="text/javascript">
        (function ($) {
            var $form = $('#install');
            var $prevButton = $('a.prev');
            var $nextButton = $('a.next');
            var nextButtonProgress = <?= json_encode($nextButtonProgress) ?>;

            $prevButton.on('click', function (evt) {
                evt.preventDefault();
                $form.find('input[name=action]').val('options');
                $form.submit();
            });

            $nextButton.on('click', function (evt) {
                evt.preventDefault();
                if ($nextButton.data('submitting')) {
                    return;
                }

                $nextButton.data('submitting', true).addClass('disabled');
                $prevButton.addClass('disabled');
                if (nextButtonProgress) {
                    $nextButton.find('span').text(nextButtonProgress);
                }

                $form.submit();
            });
        }(jQuery));
    </script>

<?php
function echo_ok()
{
    return '<span class="ok">' . lang('ok') . '</span>';
}

function echo_failed($msg = NULL)
{
    if ($msg === null) {
        $msg = lang('failed');
    }
    return '<span class="notok">' . $msg . '</span>';
}

function mkd($path)
{
    $rs = false;
    if (!is_dir($path)) {
        $rs = mkdir($path, 0777, true);
        if ($rs) {
            chmod($path, 0777);
            clearstatcache(); // ディレクトリ作成後のキャッシュをクリア
        }
    }

    if (!is_file($path . '/index.html')) {
        $rs = file_put_contents($path . '/index.html', '');
        if ($rs) {
            chmod($path . '/index.html', 0666);
            clearstatcache(); // ファイル作成後のキャッシュをクリア
        }
        if (!is_writable($path . '/index.html')) {
            echo echo_failed($path); // エラーメッセージを改善するために、エラー抑制を削除
        }
    }

    return $rs;
}

function p($str)
{
    return '<p>' . $str . '</p>';
}
