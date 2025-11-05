<?php

/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 * http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 * http://www.fckeditor.net/
 *
 * File Name: CreateFolder.php
 * Implements the CreateFolder command to make a new folder
 * in the current directory. Output is in XML.
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once __DIR__ . '/helpers/ConnectorXmlBuilder.php';

class Base
{
    public $fckphp_config;
    public $type;
    public $raw_cwd;
    public $resource_cwd;
    public $actual_cwd;
    public $real_cwd;
    public $filename;
    public $foldername;
    public $refreshURL;
    public $newname;

    public function __construct(array $fckphp_config, $type, $cwd)
    {
        $this->fckphp_config = $fckphp_config;
        $this->type = $type;
        $this->raw_cwd = (string)$cwd;
        $this->resource_cwd = $this->buildResourcePath($this->raw_cwd);
        $this->actual_cwd = $this->buildActualPath($this->resource_cwd);
        $this->real_cwd = $this->buildRealPath($this->resource_cwd);
    }

    protected function buildResourcePath($cwd)
    {
        $cwd = $this->normalizeSlashes((string)$cwd);
        $folder = ltrim($cwd, '/');
        $path = trim($this->type, '/');

        if ($path !== '' && $folder !== '') {
            $path .= '/' . $folder;
        } elseif ($path === '') {
            $path = $folder;
        }

        if ($path !== '' && substr($path, -1) !== '/') {
            if ($folder === '' || substr($cwd, -1) === '/') {
                $path .= '/';
            }
        }


        return $path;
    }

    protected function buildActualPath($resourcePath)
    {
        $path = $resourcePath;
        $prefix = trim($this->fckphp_config['UserFilesPath'], '/');

        if ($prefix !== '') {
            $path = $prefix . '/' . ltrim($resourcePath, '/');
        }

        $path = $this->normalizeSlashes($path);

        if ($path === '') {
            $path = '/';
        } elseif ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($resourcePath !== '' && substr($resourcePath, -1) === '/' && substr($path, -1) !== '/') {
            $path .= '/';
        }

        return $path;
    }

    protected function buildRealPath($resourcePath)
    {
        $base = rtrim($this->fckphp_config['basedir'], '/');
        $path = $base . '/' . ltrim($resourcePath, '/');
        $path = $this->normalizeSlashes($path);

        if ($resourcePath !== '' && substr($resourcePath, -1) === '/' && substr($path, -1) !== '/') {
            $path .= '/';
        }

        return $path;
    }

    protected function normalizeSlashes($path)
    {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $path));
    }

    protected function sanitizeSegment($value)
    {
        $value = (string)$value;
        $value = str_replace('\\', '/', $value);
        $value = str_replace(['../', './'], '', $value);

        while (strpos($value, '..') !== false) {
            $value = str_replace('..', '', $value);
        }

        $value = str_replace('/', '', $value);

        return trim($value);
    }

    public function sanitizeFolderName($value)
    {
        return $this->sanitizeSegment($value);
    }

    public function sanitizeFileName($value)
    {
        return $this->sanitizeSegment($value);
    }

    protected function newXmlResponse($command, array $options = [])
    {
        return new ConnectorXmlBuilder($command, $this->type, $options);
    }

    protected function outputXml(ConnectorXmlBuilder $builder, $contentType = 'Content-Type: text/xml')
    {
        header($contentType);
        echo $builder->render();
    }
}
