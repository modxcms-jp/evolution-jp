<?php

$chkagree = postv('chkagree', sessionv('chkagree'));

if (sessionv('prevAction') === 'options') {
    $_SESSION['installdata'] = postv('installdata', '');
    $_SESSION['template'] = postv('template', array());
    $_SESSION['tv'] = postv('tv', array());
    $_SESSION['chunk'] = postv('chunk', array());
    $_SESSION['snippet'] = postv('snippet', array());
    $_SESSION['plugin'] = postv('plugin', array());
    $_SESSION['module'] = postv('module', array());
}

echo '<h2>' . lang('preinstall_validation') . '</h2>';
echo '<h3>' . lang('summary_setup_check') . '</h3>';
$errors = 0;

// check PHP version

if (version_compare(phpversion(), '5.3.0') < 0) {
    $_ = echo_failed() . lang('you_running_php') . phpversion() . lang('modx_requires_php');
    $errors += 1;
} else {
    $_ = echo_ok();
}
echo p($_ . lang('checking_php_version'));

// check php register globals off
$register_globals = (int)ini_get('register_globals');
if ($register_globals == '1') {
    echo p(echo_failed() . lang('checking_registerglobals'));
    echo p('<strong>' . lang('checking_registerglobals_note') . '</strong>');
}

// check sessions
if (sessionv('test') != 1) {
    echo p(echo_failed() . lang('checking_sessions'));
    $errors += 1;
}

// check directories
// cache exists?
if (!is_dir(MODX_BASE_PATH . 'temp/cache')) {
    echo p(echo_failed() . lang('checking_if_cache_exist'));
    $errors += 1;
}

// cache writable?
if (!is_writable(MODX_BASE_PATH . 'temp/cache')) {
    $_ = echo_failed();
    $errors += 1;
} else {
    $_ = echo_ok();
    mkd(MODX_BASE_PATH . 'temp/cache/rss');
}
echo p($_ . lang('checking_if_cache_writable'));

if (is_writable(MODX_BASE_PATH . 'temp/cache')) {
    // cache files writable?
    if (!is_file(MODX_BASE_PATH . 'temp/cache/siteCache.idx.php')) {
        // make an attempt to create the file
        file_put_contents(MODX_BASE_PATH . 'temp/cache/siteCache.idx.php', '<?php //MODX site cache file ?>');
    }
    if (!is_writable(MODX_BASE_PATH . 'temp/cache/siteCache.idx.php')) {
        $_ = echo_failed();
        $errors += 1;
    } else $_ = echo_ok();
    echo p($_ . lang('checking_if_cache_file_writable'));

    file_put_contents(MODX_BASE_PATH . 'temp/cache/basicConfig.php', '<?php $cacheRefreshTime=0; ?>');

    if (!is_writable(MODX_BASE_PATH . 'temp/cache/basicConfig.php')) {
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
    $dir_files = MODX_BASE_PATH . 'content/files';
    $dir_media = MODX_BASE_PATH . 'content/media';

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
    '<p>%s %s <strong>%s%s </strong></p>'
    , echo_ok()
    , lang('checking_sql_version')
    , lang('sql_version_is')
    , $modx->db->getVersion()
);

// Version and strict mode check end

// andrazk 20070416 - add install flag and disable manager login
// temp/cache writable?

if (is_writable('../temp/cache')) {
    // make an attempt to create the file
    file_put_contents(MODX_BASE_PATH . 'temp/cache/installProc.inc.php', '<?php $installStartTime = ' . time() . '; ?>');
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
$nextButton = $errors ? lang('retry') : lang('install');
$nextVisibility = $errors > 0 || $chkagree ? 'visible' : 'hidden';
$agreeToggle = $errors > 0 ? '' : " onclick=\"if(document.getElementById('chkagree').checked){document.getElementById('nextbutton').style.visibility='visible';}else{document.getElementById('nextbutton').style.visibility='hidden';}\"";
?>
    <form id="install" action="index.php" method="POST">
        <div>
            <input type="hidden" value="<?php echo $nextAction; ?>" name="action"/>
            <input type="hidden" value="1" name="options_selected"/>
            <input type="hidden" name="prev_action" value="summary"/>
        </div>

        <h2><?php echo lang('agree_to_terms'); ?></h2>
        <p>
            <input type="checkbox" value="1" id="chkagree" name="chkagree"
                   style="line-height:18px" <?php echo $chkagree ? 'checked="checked" ' : ""; ?><?php echo $agreeToggle; ?>/><label
                for="chkagree"
                style="display:inline;float:none;line-height:18px;"> <?php echo lang('iagree_box') ?> </label>
        </p>
        <p class="buttonlinks">
            <a href="javascript:void(0);" class="prev"
               title="<?php echo lang('btnback_value') ?>"><span><?php echo lang('btnback_value') ?></span></a>
            <a href="javascript:void(0);" class="next" id="nextbutton" title="<?php echo $nextButton ?>"
               style="visibility:<?php echo $nextVisibility; ?>"><span><?php echo $nextButton ?></span></a>
        </p>
    </form>
    <script type="text/javascript">
        jQuery('a.prev').click(function () {
            jQuery('#install input[name=action]').val('options');
            jQuery('#install').submit();
        });
        jQuery('a.next').click(function () {
            jQuery('#install').submit();
        });
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
    if (!is_dir($path)) {
        $rs = @mkdir($path, 0777, true);
        if ($rs) {
            $rs = @chmod($path, 0777);
        }
    }

    if (!is_file($path . '/index.html')) {
        $rs = @file_put_contents($path . '/index.html', '');
        if ($rs) @chmod($path . '/index.html', 0666);
        if (!is_writable($path . '/index.html')) echo echo_failed($path);
    }

    return $rs;
}

function p($str)
{
    return '<p>' . $str . '</p>';
}
