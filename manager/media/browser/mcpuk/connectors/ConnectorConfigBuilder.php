<?php

class ConnectorConfigBuilder
{
    /** @var DocumentParser */
    private $modx;

    public function __construct(DocumentParser $modx)
    {
        $this->modx = $modx;
    }

    public function build()
    {
        $this->assertBrowserEnabled();

        $baseUrl = $this->resolveResourceBaseUrl();

        $config = array();
        $config['prot'] = $this->resolveProtocol();
        $config['basedir'] = $this->resolveBaseDir();
        $config['urlprefix'] = $this->resolveUrlPrefix($baseUrl);
        $config['UserFilesPath'] = '';
        $config['auth'] = $this->buildAuthConfig();
        $config['ResourceAreas'] = $this->buildResourceAreas();
        $config['DiskQuota'] = array('Global' => -1);
        $config['MaxDirNameLength'] = 25;
        $config['DirNameAllowedChars'] = $this->buildDirectoryCharacters();
        $config['FileNameAllowedChars'] = $this->buildFileCharacters($config['DirNameAllowedChars']);
        $config = array_merge($config, $this->buildDebugConfig());
        $config['ResourceTypes'] = array('files', 'images', 'media');
        $config['Commands'] = $this->buildCommandList();

        return $config;
    }

    private function assertBrowserEnabled()
    {
        if ($this->modx->config('use_browser') != 1) {
            exit('<b>PERMISSION DENIED</b><br /><br />You do not have permission to access this file!');
        }
    }

    private function resolveProtocol()
    {
        return serverv('HTTPS') === 'on' ? 'https://' : 'http://';
    }

    private function resolveBaseDir()
    {
        return rtrim($this->modx->config('rb_base_dir'), '/') . '/';
    }

    private function resolveResourceBaseUrl()
    {
        $resourceBaseUrl = $this->modx->config('rb_base_url');
        $parsed = parse_url($resourceBaseUrl);

        if (!is_array($parsed) || empty($parsed['host'])) {
            $rbBaseUrl = $resourceBaseUrl;
            $baseUrlParse = parse_url($this->modx->config('base_url'));

            if (
                $rbBaseUrl !== '/'
                && is_array($baseUrlParse)
                && !empty($baseUrlParse['path'])
                && $baseUrlParse['path'] !== '/'
            ) {
                $rbBaseUrl = str_replace($baseUrlParse['path'], '', $rbBaseUrl);
            }

            $rbBaseUrl = ltrim($rbBaseUrl, '/');
            $editor = getv('editor');
            $stripImagePaths = (int) $this->modx->config('strip_image_paths');

            if ($editor === 'fckeditor2' && $stripImagePaths === 1) {
                return $this->modx->config('base_url') . $rbBaseUrl;
            }

            if (
                ($editor === 'tinymce3' || $editor === 'tinymce')
                && $stripImagePaths !== 1
            ) {
                return $this->modx->config('site_url') . $rbBaseUrl;
            }
        }

        return $resourceBaseUrl;
    }

    private function resolveUrlPrefix($baseUrl)
    {
        if ((int) $this->modx->config('strip_image_paths') === 1) {
            if (substr($baseUrl, -1) === '/') {
                return str_replace(
                    $this->modx->config('site_url'),
                    '',
                    substr($baseUrl, 0, -1)
                );
            }

            return $baseUrl;
        }

        return rtrim($baseUrl, '/');
    }

    private function buildAuthConfig()
    {
        return array(
            'Req' => false,
            'HandlerClass' => 'Default',
            'Handler' => array(
                'SharedKey' => "->Shared_K3y-F0R*5enD1NG^auth3nt1caT10n'Info/To\\FILE,Brow5er--!",
            ),
        );
    }

    private function buildResourceAreas()
    {
        $maxUploadSize = $this->resolveUploadMaxSize();

        return array(
            'files' => array(
                'AllowedExtensions' => $this->explodeExtensions('upload_files'),
                'AllowedMIME' => array(),
                'MaxSize' => $maxUploadSize,
                'DiskQuota' => -1,
                'HideFolders' => array('^\.'),
                'HideFiles' => array('^\.'),
                'AllowImageEditing' => false,
            ),
            'images' => array(
                'AllowedExtensions' => $this->explodeExtensions('upload_images'),
                'AllowedMIME' => array(),
                'MaxSize' => $maxUploadSize,
                'DiskQuota' => -1,
                'HideFolders' => array('^\.'),
                'HideFiles' => array('^\.'),
                'AllowImageEditing' => true,
            ),
            'media' => array(
                'AllowedExtensions' => $this->explodeExtensions('upload_media'),
                'AllowedMIME' => array(),
                'MaxSize' => $maxUploadSize,
                'DiskQuota' => -1,
                'HideFolders' => array('^\.'),
                'HideFiles' => array('^\.'),
                'AllowImageEditing' => false,
            ),
        );
    }

    private function resolveUploadMaxSize()
    {
        $size = $this->modx->config('upload_maxsize');

        if (empty($size)) {
            return 5000000;
        }

        return $size;
    }

    private function explodeExtensions($key)
    {
        $value = strtolower((string) $this->modx->config($key));

        return explode(',', $value);
    }

    private function buildDirectoryCharacters()
    {
        $chars = array();

        for ($i = 48; $i < 58; $i++) {
            $chars[] = chr($i);
        }

        for ($i = 97; $i < 123; $i++) {
            $chars[] = chr($i);
        }

        for ($i = 65; $i < 91; $i++) {
            $chars[] = chr($i);
        }

        array_push($chars, ' ', '-', '_', '.');

        return $chars;
    }

    private function buildFileCharacters(array $directoryCharacters)
    {
        $chars = $directoryCharacters;
        array_push($chars, ')', '(', '[', ']', '~');

        return $chars;
    }

    private function buildDebugConfig()
    {
        return array(
            'Debug' => false,
            'DebugOutput' => 'fck_conn_dbg',
            'Debug_Errors' => false,
            'Debug_Trace' => false,
            'Debug_Output' => false,
            'Debug_GET' => false,
            'Debug_POST' => false,
            'Debug_SERVER' => false,
            'Debug_SESSIONS' => false,
        );
    }

    private function buildCommandList()
    {
        return array(
            'CreateFolder',
            'GetFolders',
            'GetFoldersAndFiles',
            'FileUpload',
            'Thumbnail',
            'DeleteFile',
            'DeleteFolder',
            'GetUploadProgress',
            'RenameFile',
            'RenameFolder',
        );
    }
}
