<?php

/**
 * Database connection result class
 *
 * Represents the result of a database connection attempt with detailed error information
 */
class DBConnectionResult
{
    public readonly bool $success;
    public readonly ?string $errorMessage;
    public readonly ?int $errorCode;
    public readonly string $errorType;

    /**
     * Error types
     */
    public const ERROR_TYPE_NONE = 'none';
    public const ERROR_TYPE_DNS = 'dns';
    public const ERROR_TYPE_AUTH = 'auth';
    public const ERROR_TYPE_TIMEOUT = 'timeout';
    public const ERROR_TYPE_NETWORK = 'network';
    public const ERROR_TYPE_DATABASE = 'database';
    public const ERROR_TYPE_CHARSET = 'charset';
    public const ERROR_TYPE_UNKNOWN = 'unknown';

    /**
     * Create a new connection result
     *
     * @param bool $success Whether the connection was successful
     * @param string|null $errorMessage Error message (null if successful)
     * @param int|null $errorCode MySQL error code (null if successful)
     * @param string $errorType Type of error (one of ERROR_TYPE_* constants)
     */
    public function __construct(
        bool $success,
        ?string $errorMessage = null,
        ?int $errorCode = null,
        string $errorType = self::ERROR_TYPE_UNKNOWN
    ) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->errorType = $errorType;
    }

    /**
     * Create a successful connection result
     *
     * @return self
     */
    public static function success(): self
    {
        return new self(true, null, null, self::ERROR_TYPE_NONE);
    }

    /**
     * Create a failed connection result
     *
     * @param string $message Error message
     * @param int $code MySQL error code
     * @param string $type Error type
     * @return self
     */
    public static function failure(string $message, int $code = 0, string $type = self::ERROR_TYPE_UNKNOWN): self
    {
        return new self(false, $message, $code, $type);
    }

    /**
     * Get a user-friendly error message in Japanese
     *
     * @return string
     */
    public function getUserMessage(): string
    {
        if ($this->success) {
            return '接続に成功しました';
        }

        return match($this->errorType) {
            self::ERROR_TYPE_DNS => sprintf(
                'ホスト名が見つかりません。ホスト名を確認してください。'
            ),
            self::ERROR_TYPE_AUTH => 'ユーザー名またはパスワードが間違っています。',
            self::ERROR_TYPE_TIMEOUT => 'データベースサーバーへの接続がタイムアウトしました。ホスト名とポート番号を確認してください。',
            self::ERROR_TYPE_NETWORK => 'ネットワークエラーが発生しました。データベースサーバーが起動しているか確認してください。',
            self::ERROR_TYPE_DATABASE => sprintf('データベースの選択に失敗しました: %s', $this->errorMessage),
            self::ERROR_TYPE_CHARSET => sprintf('文字セットの設定に失敗しました: %s', $this->errorMessage),
            default => sprintf('接続に失敗しました: %s (エラーコード: %d)', $this->errorMessage, $this->errorCode),
        };
    }

    /**
     * Get a user-friendly error message in English
     *
     * @return string
     */
    public function getUserMessageEn(): string
    {
        if ($this->success) {
            return 'Connection successful';
        }

        return match($this->errorType) {
            self::ERROR_TYPE_DNS => 'Hostname not found. Please check the hostname.',
            self::ERROR_TYPE_AUTH => 'Invalid username or password.',
            self::ERROR_TYPE_TIMEOUT => 'Connection timeout. Please check the hostname and port number.',
            self::ERROR_TYPE_NETWORK => 'Network error. Please check if the database server is running.',
            self::ERROR_TYPE_DATABASE => sprintf('Failed to select database: %s', $this->errorMessage),
            self::ERROR_TYPE_CHARSET => sprintf('Failed to set charset: %s', $this->errorMessage),
            default => sprintf('Connection failed: %s (Error code: %d)', $this->errorMessage, $this->errorCode),
        };
    }

    /**
     * Convert to array for debugging
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'error_type' => $this->errorType,
        ];
    }
}
