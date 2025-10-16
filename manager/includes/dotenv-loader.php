<?php

class Dotenv
{
    protected string $path;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('%s file not found', $path));
        }
        $this->path = $path;
    }

    public function load(): void
    {
        if (!is_readable($this->path)) {
            throw new RuntimeException(sprintf('%s file is not readable', $this->path));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // コメント行を無視
            if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0) {
                continue;
            }

            // "=" が含まれない行は不正なのでスキップ
            if (strpos($line, '=') === false) {
                continue;
            }

            // KEY=VALUE の形式をパース
            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            // 文字列の囲み（""または'')を削除
            if ($value !== '' && in_array($value[0], ["\"", "'"], true)) {
                $quote = $value[0];
                if (substr($value, -1) === $quote) {
                    $value = substr($value, 1, -1);
                } else {
                    $value = substr($value, 1);
                }
            }

            // 環境変数にセット
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
