<?php

// setup Template template files - array : name, description, type - 0:file or 1:content, parameters, category
$tplTemplates = [];
$templatePath = MODX_BASE_PATH . 'assets/templates/';
if (!sessionv('is_upgradeable') && is_dir($templatePath) && is_readable($templatePath)) {
    $files = collectTpls($templatePath);
    foreach ($files as $tplfile) {
        $params = parse_docblock($tplfile);
        if (!is_array($params) || !$params) {
            continue;
        }
        if ($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplTemplates[] = [
            'templatename'  => $params['name'],
            'description'   => $params['description'],
            'type'          => $params['type'],
            'tpl_file_path' => $tplfile,
            'category'      => $params['modx_category'],
            'locked'        => $params['lock_template'],
            'installset'    => get_installset($params)
        ];
    }
}

// setup Template Variable template files
$tplTVs = [];
$tvPath = MODX_BASE_PATH . 'assets/tvs/';
if (!sessionv('is_upgradeable') && is_dir($tvPath) && is_readable($tvPath)) {
    $files = collectTpls($tvPath);
    foreach ($files as $tplfile) {
        $params = parse_docblock($tplfile);
        if (!is_array($params) || !$params) {
            continue;
        }
        if ($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplTVs[] = [
            'name'                 => $params['name'],
            'caption'              => $params['caption'],
            'description'          => $params['description'],
            'input_type'           => $params['input_type'],
            'elements'             => $params['input_options'],
            'default_text'         => $params['input_default'],
            'display'              => $params['output_widget'],
            'display_params'       => $params['output_widget_params'],
            'tpl_file_path'        => $tplfile /* not currently used */,
            'category'             => $params['modx_category'],
            'locked'               => $params['lock_tv']  /* value should be 1 or 0 */,
            'installset'           => get_installset($params),
            'template_assignments' => $params['template_assignments']
        ];
    }
}

// setup chunks template files - array : name, description, type - 0:file or 1:content, file or content
$tplChunks = [];
$chunkPath = MODX_BASE_PATH . 'assets/chunks/';
if (is_dir($chunkPath) && is_readable($chunkPath)) {
    $files = collectTpls($chunkPath);
    foreach ($files as $tpl_file_path) {
        $params = parse_docblock($tpl_file_path);
        if (!is_array($params) || !$params) {
            continue;
        }
        if (sessionv('is_upgradeable') && array_get($params, 'overwrite') === 'false') {
            continue;
        }
        $tplChunks[] = [
            'name'          => $params['name'],
            'description'   => $params['description'],
            'tpl_file_path' => $tpl_file_path,
            'category'      => $params['modx_category'],
            'overwrite'     => $params['overwrite']
                ? $params['overwrite']
                : 'true', 'installset' => get_installset($params)
            ];
    }
}

// setup snippets template files - array : name, description, type - 0:file or 1:content, file or content,properties
$tplSnippets = [];
$snippetPath = MODX_BASE_PATH . 'assets/snippets/';
if (is_dir($snippetPath) && is_readable($snippetPath)) {
    $files = collectTpls($snippetPath);
    foreach ($files as $tplfile) {
        $params = parse_docblock($tplfile);
        if (!is_array($params) || !$params) {
            continue;
        }
        $params['category'] = $params['category'] ?? 'snippet';
        if (sessionv('is_upgradeable') && compare_check($params) === 'same') {
            continue;
        }
        if ($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplSnippets[] = [
            'name'          => $params['name'],
            'description'   => $params['description'],
            'tpl_file_path' => $tplfile,
            'properties'    => $params['properties'],
            'category'      => $params['modx_category'],
            'installset'    => get_installset($params)
        ];
    }
}

// setup plugins template files - array : name, description, type - 0:file or 1:content, file or content,properties
$tplPlugins = [];
$pluginPath = MODX_BASE_PATH . 'assets/plugins/';
if (is_dir($pluginPath) && is_readable($pluginPath)) {
    $pluginParams = [];
    foreach (collectTpls($pluginPath) as $tplfile) {
        if (strpos($tplfile, '/mgr_custom/') !== false) {
            continue;
        } //Ignore

        $params = parse_docblock($tplfile);
        if (!is_array($params) || !$params) {
            continue;
        }
        $pluginParams[$tplfile] = $params;
    }

    // 後継のプラグインが旧名（@legacy_names）として持つプラグインは候補に出さない。
    // 古いファイルが残っていると、後継が止めるはずの旧プラグインを入れ直してしまう。
    // 後継が「同じ版」で候補に出ない場合も含めるため、版の比較より前に集める
    // （管理画面で旧プラグインを有効にするのは利用者の自由なので、インストーラーが入れないだけにする）
    $supersededNames = [];
    foreach ($pluginParams as $params) {
        foreach (legacy_names($params['legacy_names'] ?? '') as $legacyName) {
            $supersededNames[$legacyName] = true;
        }
    }

    foreach ($pluginParams as $tplfile => $params) {
        if (isset($supersededNames[$params['name']])) {
            continue;
        }
        $params['category'] = $params['category'] ?? 'plugin';
        $compare = sessionv('is_upgradeable') ? compare_check($params) : '';
        if ($compare === 'same') {
            continue;
        }
        if ($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplPlugins[] = [
            'name'          => $params['name'],
            'description'   => $params['description'],
            'tpl_file_path' => $tplfile,
            'properties'    => $params['properties'],
            'events'        => $params['events'],
            'guid'          => $params['guid'],
            'category'      => $params['modx_category'],
            'legacy_names'  => $params['legacy_names'],
            'user_disabled' => $compare === 'disabled',
            'disabled'      => isset($params['disabled'])
                ? $params['disabled']
                : '0', 'installset' => get_installset($params)
        ];
    }
}

// setup modules - array : name, description, type - 0:file or 1:content, file or content,properties, guid,enable_sharedparams
$tplModules = [];
$modulePath = MODX_BASE_PATH . 'assets/modules/';
if (is_dir($modulePath) && is_readable($modulePath)) {
    $files = collectTpls($modulePath);
    foreach ($files as $tplfile) {
        $params = parse_docblock($tplfile);
        if (!is_array($params) || !$params) {
            continue;
        }
        $params['category'] = $params['category'] ?? 'module';
        if (sessionv('is_upgradeable') && compare_check($params) === 'same') {
            continue;
        }
        if ($params['version']) {
            $params['description'] = add_version_strings($params);
        }
        $tplModules[] = [
            'name'          => $params['name'],
            'description'   => $params['description'],
            'tpl_file_path' => $tplfile,
            'properties'    => $params['properties'],
            'guid'          => $params['guid'],
            'shareparams'   => (int)$params['shareparams'],
            'category'      => $params['modx_category'],
            'installset'    => get_installset($params)
        ];
    }
}

function get_installset($params)
{
    if (array_key_exists('installset', $params)) {
        return preg_split("/\s*,\s*/", $params['installset']);
    }
    return [];
}

function add_version_strings($params)
{
    return sprintf(
        '<strong>%s</strong> %s', $params['version'], $params['description']
    );
}
