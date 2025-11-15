# セキュリティ改善提案

## 概要

インストーラのセキュリティを強化し、本番環境での安全性を向上させます。

## 1. アップグレード時の認証（最優先）

### 現状の問題

- **誰でもアップグレードを実行できる**
- `install/`ディレクトリにアクセスできれば、認証なしでアップグレード可能
- 悪意のある第三者による不正アップグレードのリスク

### 改善案

#### 1-1. 管理者ログイン認証

アップグレード実行前に管理画面へのログインを要求：

```php
// src/Security/UpgradeAuthenticator.php
class UpgradeAuthenticator
{
    public function requireAuth(): bool
    {
        // 管理者としてログイン済みかチェック
        if ($this->isManagerLoggedIn()) {
            return true;
        }

        // アップグレードトークンが有効か
        if ($this->hasValidUpgradeToken()) {
            return true;
        }

        // 認証失敗 - ログインフォームを表示
        $this->showAuthForm();
        exit;
    }

    private function isManagerLoggedIn(): bool
    {
        if (!isset($_SESSION['mgrInternalKey'])) {
            return false;
        }

        // DBで有効なセッションか確認
        $userId = $_SESSION['mgrInternalKey'];
        $user = $this->connection->query(
            "SELECT role FROM [+prefix+]user_attributes WHERE internalKey = ?",
            [$userId]
        )->fetch();

        // 管理者権限（role=1）のみ許可
        return $user && (int)$user['role'] === 1;
    }
}
```

#### 1-2. CLI/自動化用トークン認証

サーバーコンソールからのアップグレード実行時用：

```php
private function hasValidUpgradeToken(): bool
{
    $token = $_GET['upgrade_token'] ?? '';

    if (!$token) {
        return false;
    }

    // config.inc.phpまたは一時ファイルに保存されたトークンと照合
    $validToken = $this->getStoredUpgradeToken();

    return hash_equals($validToken, $token);
}

public function generateUpgradeToken(): string
{
    $token = bin2hex(random_bytes(32));

    // 一時ファイルに保存（有効期限付き）
    file_put_contents(
        MODX_BASE_PATH . 'temp/upgrade_token.php',
        '<?php return ' . var_export([
            'token' => $token,
            'expires' => time() + 3600 // 1時間有効
        ], true) . ';'
    );

    return $token;
}
```

#### 1-3. UI改善

認証方法を選択できるフォームを提供：

```html
<h2>アップグレード認証が必要です</h2>

<div class="auth-methods">
    <div class="auth-method">
        <h3>方法1: 管理画面からログイン</h3>
        <p>管理画面にログインしてからアップグレードを実行してください。</p>
        <a href="../manager/" class="btn">管理画面へ</a>
    </div>

    <div class="auth-method">
        <h3>方法2: コマンドライン（上級者向け）</h3>
        <p>サーバーコンソールから以下を実行してトークンを生成：</p>
        <code>php -r "echo bin2hex(random_bytes(32));"</code>
        <p>生成されたトークンを使用：</p>
        <code>?upgrade_token=YOUR_TOKEN_HERE</code>
    </div>
</div>
```

### 実装優先度

**最優先** - 本番環境での不正アップグレードを防ぐため

---

## 2. CSRF保護

### 現状の問題

- フォーム送信にCSRF保護がない
- 悪意のあるサイトから攻撃される可能性

### 改善案

```php
// src/Security/CsrfProtection.php
class CsrfProtection
{
    private $session;

    public function generateToken(): string
    {
        if (!$this->session->has('csrf_token')) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('csrf_token', $token);
        }

        return $this->session->get('csrf_token');
    }

    public function validateToken(string $token): bool
    {
        $sessionToken = $this->session->get('csrf_token');

        if (!$sessionToken || !$token) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public function getTokenField(): string
    {
        $token = $this->generateToken();
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token)
        );
    }
}
```

**全てのフォームに追加：**

```php
<form method="POST">
    <?= $csrf->getTokenField() ?>
    <!-- フォームフィールド -->
</form>
```

### 実装優先度

**高** - CSRF攻撃を防ぐため

---

## 3. SQLインジェクション対策の強化

### 現状の問題

- `db()->escape()`に頼った方法
- プリペアドステートメントが一部でしか使われていない

### 改善案

すべてのDB操作でプリペアドステートメントを使用：

```php
// src/Database/Connection.php
public function insert(string $table, array $data): int
{
    $table = $this->replacePrefix($table);

    $columns = array_keys($data);
    $placeholders = array_fill(0, count($data), '?');

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_values($data));

    return (int)$this->pdo->lastInsertId();
}

public function update(string $table, array $data, string $where, array $whereParams = []): bool
{
    $table = $this->replacePrefix($table);

    $sets = array_map(function($col) {
        return "$col = ?";
    }, array_keys($data));

    $sql = sprintf(
        'UPDATE %s SET %s WHERE %s',
        $table,
        implode(', ', $sets),
        $where
    );

    $params = array_merge(array_values($data), $whereParams);
    $stmt = $this->pdo->prepare($sql);

    return $stmt->execute($params);
}
```

### 実装優先度

**高** - SQLインジェクションを防ぐため

---

## 4. パスワードハッシュのphpass移行

### 現状の問題

- インストーラはMD5でパスワードをハッシュ化（`sqlParser.class.php:62`）
- MD5は安全でない

### 改善案

既存のphpassライブラリを活用：

```php
// src/Security/PasswordHasher.php
class PasswordHasher
{
    private $phpass;

    public function __construct()
    {
        // phpassクラスを直接読み込む（DocumentParser不要）
        require_once MODX_BASE_PATH . 'manager/includes/extenders/phpass/phpass.class.inc.php';
        $this->phpass = new \PasswordHash();
    }

    public function hash(string $password): string
    {
        return $this->phpass->HashPassword($password);
    }

    public function verify(string $password, string $hash): bool
    {
        return $this->phpass->CheckPassword($password, $hash);
    }
}
```

**sqlParser.class.phpの修正：**

```php
public function __construct()
{
    // phpassを読み込む
    require_once MODX_BASE_PATH . 'manager/includes/extenders/phpass/phpass.class.inc.php';
    $this->passwordHasher = new PasswordHash();
}

public function intoDB($filename)
{
    // ...
    $sql_array = preg_split(
        '@;[ \t]*\n@',
        evo()->parseText(
            str_replace(...),
            array(
                'PREFIX' => $this->prefix,
                'ADMINNAME' => $this->adminname,
                'ADMINPASS' => $this->passwordHasher->HashPassword($this->adminpass), // phpass使用
                'ADMINEMAIL' => $this->adminemail,
                // ...
            )
        )
    );
}
```

**後方互換性：**

```php
// アップグレード認証でMD5/V1ハッシュもサポート
private function verifyPassword(string $password, string $hash, int $userId): bool
{
    // 1. phpassで検証（推奨）
    if ($this->passwordHasher->verify($password, $hash)) {
        return true;
    }

    // 2. V1ハッシュで検証
    if ($this->verifyV1Hash($password, $hash, $userId)) {
        // phpassに自動移行
        $this->upgradeToPhpass($userId, $password);
        return true;
    }

    // 3. MD5で検証（後方互換性）
    if ($hash === md5($password)) {
        // phpassに自動移行
        $this->upgradeToPhpass($userId, $password);
        return true;
    }

    return false;
}
```

### 実装優先度

**高** - 安全なパスワードハッシュのため

---

## 5. パスワード強度チェック

### 改善案

```php
// src/Validator/PasswordValidator.php
class PasswordValidator
{
    public function validate(string $password, string $confirmPassword): bool
    {
        $this->errors = [];

        // 確認パスワードと一致するか
        if ($password !== $confirmPassword) {
            $this->errors[] = 'Password and confirmation do not match';
        }

        // 最低8文字
        if (strlen($password) < 8) {
            $this->errors[] = 'Password must be at least 8 characters long';
        }

        // 大文字、小文字、数字を含む
        if (!preg_match('/[A-Z]/', $password)) {
            $this->errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $this->errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $this->errors[] = 'Password must contain at least one number';
        }

        // 一般的なパスワードチェック
        $commonPasswords = ['password', '12345678', 'admin', 'qwerty'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $this->errors[] = 'Password is too common. Please choose a stronger password';
        }

        return empty($this->errors);
    }
}
```

**UI改善：**

```javascript
// リアルタイムパスワード強度表示
document.getElementById('adminpass').addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthEl = document.getElementById('password-strength');

    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    const levels = ['weak', 'fair', 'good', 'strong', 'very-strong'];
    const labels = ['弱い', '普通', '良い', '強い', 'とても強い'];

    strengthEl.className = 'password-strength ' + levels[strength - 1];
    strengthEl.textContent = labels[strength - 1] || '';
});
```

### 実装優先度

**中** - ユーザビリティとセキュリティの向上

---

## 6. インストールディレクトリの自動削除

### 現状の問題

- 手動削除が必要
- 削除忘れでセキュリティリスク

### 改善案

```php
// src/Installer/InstallDirectoryManager.php
class InstallDirectoryManager
{
    public function removeInstallDirectory(): bool
    {
        $installDir = MODX_SETUP_PATH;

        // 確認ファイルを作成
        file_put_contents(
            MODX_BASE_PATH . 'install_completed.lock',
            json_encode([
                'completed_at' => date('Y-m-d H:i:s'),
                'version' => $this->getInstalledVersion()
            ])
        );

        try {
            $this->deleteDirectory($installDir);
            return true;
        } catch (\Exception $e) {
            // 削除失敗時は警告を表示
            return false;
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
```

**使用：**

```php
// インストール完了時
$dirManager = new InstallDirectoryManager();
if ($dirManager->removeInstallDirectory()) {
    echo "<p>インストールディレクトリを自動削除しました。</p>";
} else {
    echo "<p class='warning'>インストールディレクトリを手動で削除してください：</p>";
    echo "<p><code>rm -rf " . MODX_SETUP_PATH . "</code></p>";
}
```

### 実装優先度

**高** - インストール後のセキュリティリスクを軽減

---

## 7. その他のセキュリティ改善

### 7-1. セッションセキュリティ

```php
// セッション開始時
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

// セッション固定攻撃対策
session_regenerate_id(true);
```

### 7-2. X-Frame-Options ヘッダー

```php
// Clickjacking対策
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
```

### 7-3. ファイルアップロードの検証（将来的に）

```php
// ファイルタイプの検証
$allowedTypes = ['image/jpeg', 'image/png'];
if (!in_array($_FILES['file']['type'], $allowedTypes)) {
    throw new SecurityException('Invalid file type');
}
```

---

## 実装スケジュール

| 改善項目 | 優先度 | 実装時期 |
|---------|--------|---------|
| アップグレード時の認証 | 最優先 | Phase 3 |
| CSRF保護 | 高 | Phase 5 |
| SQLインジェクション対策 | 高 | Phase 2 |
| パスワードハッシュphpass移行 | 高 | Phase 2 |
| パスワード強度チェック | 中 | Phase 5 |
| インストールディレクトリ自動削除 | 高 | Phase 3 |
| セッションセキュリティ | 中 | Phase 6 |
| セキュリティヘッダー | 低 | Phase 10 |

---

## まとめ

これらのセキュリティ改善により：

1. **不正アップグレードを防止**
2. **CSRF攻撃を防止**
3. **SQLインジェクションを防止**
4. **安全なパスワードハッシュ**
5. **インストール後のセキュリティリスク軽減**

本番環境での安全性が大幅に向上します。
