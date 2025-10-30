<?php

class ConnectorRequest
{
    private $command;
    private $type;
    private $currentFolder;
    private $extraParams;
    private $query;
    private $post;
    private $files;
    private $server;
    private $cookies;

    private function __construct(
        $command,
        $type,
        $currentFolder,
        $extraParams,
        array $query,
        array $post,
        array $files,
        array $server,
        array $cookies
    ) {
        $this->command = $command;
        $this->type = $type;
        $this->currentFolder = $currentFolder;
        $this->extraParams = $extraParams;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
    }

    public static function fromGlobals(
        array $query,
        array $post,
        array $files,
        array $server,
        array $cookies
    ) {
        $command = self::valueFrom($query, $post, 'Command', '');
        $type = self::valueFrom($query, $post, 'Type', 'files');
        $type = strtolower($type);

        $currentFolderValue = self::valueFrom($query, $post, 'CurrentFolder', null);
        if ($currentFolderValue) {
            $currentFolder = self::stripTraversal(self::unescape($currentFolderValue));
        } else {
            $currentFolder = '/';
        }

        $extraParams = self::valueFrom($query, $post, 'ExtraParams', '');

        return new self(
            $command,
            $type,
            $currentFolder,
            $extraParams,
            $query,
            $post,
            $files,
            $server,
            $cookies
        );
    }

    private static function valueFrom(array $query, array $post, $key, $default)
    {
        if (array_key_exists($key, $query)) {
            return self::scalarValue($query[$key], $default);
        }

        if (array_key_exists($key, $post)) {
            return self::scalarValue($post[$key], $default);
        }

        return $default;
    }

    private static function scalarValue($value, $default)
    {
        if (is_array($value)) {
            return $default;
        }

        return (string)$value;
    }

    private static function stripTraversal($value)
    {
        return str_replace('..', '', $value);
    }

    public static function unescape($source, $iconv_to = 'UTF-8')
    {
        $decodedStr = '';
        $pos = 0;
        $len = strlen($source);
        while ($pos < $len) {
            $charAt = substr($source, $pos, 1);
            if ($charAt !== '%') {
                $decodedStr .= $charAt;
                $pos++;
                continue;
            }
            $pos++;
            $charAt = substr($source, $pos, 1);
            if ($charAt !== 'u') {
                $decodedStr .= chr(
                    hexdec(
                        substr($source, $pos, 2)
                    )
                );
                $pos += 2;
                continue;
            }
            $pos++;
            $decodedStr .= self::code2utf(
                hexdec(
                    substr($source, $pos, 4)
                )
            );
            $pos += 4;
        }

        if ($iconv_to !== 'UTF-8') {
            return iconv('UTF-8', $iconv_to, $decodedStr);
        }

        return $decodedStr;
    }

    private static function code2utf($num)
    {
        if ($num < 128) {
            return chr($num);
        }
        if ($num < 2048) {
            return chr(($num >> 6) + 192)
                . chr(($num & 63) + 128);
        }
        if ($num < 65536) {
            return chr(($num >> 12) + 224)
                . chr((($num >> 6) & 63) + 128)
                . chr(($num & 63) + 128);
        }
        if ($num < 2097152) {
            return chr(($num >> 18) + 240)
                . chr((($num >> 12) & 63) + 128)
                . chr((($num >> 6) & 63) + 128)
                . chr(($num & 63) + 128);
        }
        return '';
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getCurrentFolder()
    {
        return $this->currentFolder;
    }

    public function getExtraParams()
    {
        return $this->extraParams;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getPost()
    {
        return $this->post;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getServer()
    {
        return $this->server;
    }

    public function getCookies()
    {
        return $this->cookies;
    }
}
