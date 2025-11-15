# モダンPHPアーキテクチャ設計書

## 概要

インストーラでモダンなPHPのベストプラクティスを先行実装し、成功後にコア全体への展開を見据えます。

## 基本方針

**「インストーラをモダンPHPの実験場とする」**

- コア本体の大規模変更は困難
- インストーラは比較的独立している
- インストーラで成功した手法をコアに展開

## モダンPHPの構成要素

### 1. 依存関係管理（Composer）

**現状の問題:**
- 手動でクラスファイルをrequire/include
- バージョン管理が困難
- サードパーティライブラリの導入が困難

**改善:**

```json
// install/composer.json
{
    "name": "evolution-cms/installer",
    "type": "project",
    "require": {
        "php": ">=7.4",
        "ext-pdo": "*",
        "ext-json": "*",
        "ext-mbstring": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "phpstan/phpstan": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "EvolutionCMS\\Install\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "EvolutionCMS\\Install\\Tests\\": "tests/"
        }
    }
}
```

**メリット:**
- 自動オートロード
- 依存関係の明示化
- 開発ツールの統一

### 2. PSR標準の準拠

#### PSR-4: オートローディング

```php
// 既に実装済み（install/autoload.php）
namespace EvolutionCMS\Install\Http\Controller;

class ModeController extends AbstractController
{
    // 自動ロード: src/Http/Controller/ModeController.php
}
```

#### PSR-3: ロガーインターフェース

```php
namespace EvolutionCMS\Install\Logger;

use Psr\Log\LoggerInterface;

class InstallLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void { ... }
    public function alert($message, array $context = []): void { ... }
    public function critical($message, array $context = []): void { ... }
    public function error($message, array $context = []): void { ... }
    public function warning($message, array $context = []): void { ... }
    public function notice($message, array $context = []): void { ... }
    public function info($message, array $context = []): void { ... }
    public function debug($message, array $context = []): void { ... }
    public function log($level, $message, array $context = []): void { ... }
}
```

**使用:**

```php
$logger->info('Starting installation', ['version' => $version]);
$logger->error('Database connection failed', ['error' => $e->getMessage()]);
```

#### PSR-7: HTTPメッセージインターフェース

```php
namespace EvolutionCMS\Install\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RequestInterface extends ServerRequestInterface
{
    public function input(string $key, $default = null);
    public function all(): array;
    public function only(array $keys): array;
}

interface ResponseInterface extends ResponseInterface
{
    public function json(array $data, int $status = 200): self;
    public function redirect(string $url, int $status = 302): self;
}
```

#### PSR-11: 依存性注入コンテナ

```php
namespace EvolutionCMS\Install\Core;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->instances[$abstract] = null;
    }

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!$this->has($id)) {
            throw new NotFoundException("Service {$id} not found");
        }

        $concrete = $this->bindings[$id];

        $instance = is_callable($concrete)
            ? $concrete($this)
            : $this->resolve($concrete);

        if ($this->isSingleton($id)) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    private function resolve(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class;
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
```

**使用:**

```php
// config/container.php
return function(Container $container) {
    $container->singleton(Connection::class, function($c) {
        return new Connection(config('database'));
    });

    $container->bind(InstallerInterface::class, function($c) {
        if (sessionv('is_upgradeable')) {
            return new Upgrader($c->get(Connection::class));
        }
        return new NewInstaller($c->get(Connection::class));
    });
};

// 使用
$installer = $container->get(InstallerInterface::class);
$installer->install();
```

#### PSR-12: コーディングスタイル

```php
<?php
declare(strict_types=1);

namespace EvolutionCMS\Install\Installer;

use EvolutionCMS\Install\Database\Connection;
use EvolutionCMS\Install\Session\InstallSession;

class NewInstaller extends AbstractInstaller
{
    public function __construct(
        private Connection $connection,
        private InstallSession $session
    ) {
        parent::__construct();
    }

    public function install(): bool
    {
        // インデント: 4スペース
        // 開き中括弧: 同じ行
        // 型宣言: 必須
    }
}
```

### 3. strict_types と型安全性

```php
<?php
declare(strict_types=1);

namespace EvolutionCMS\Install\Database;

class QueryBuilder
{
    // プロパティの型宣言
    private Connection $connection;
    private string $table;
    private array $wheres = [];

    // 引数の型宣言
    public function where(string $column, $operator = null, $value = null): self
    {
        // ...
        return $this;
    }

    // 戻り値の型宣言
    public function get(): array
    {
        // ...
    }

    public function first(): ?array
    {
        // Nullable型
    }

    public function count(): int
    {
        // スカラー型
    }
}
```

**メリット:**
- 実行時型エラーの早期発見
- IDEの補完精度向上
- リファクタリングの安全性

### 4. インターフェースと抽象化

```php
// インターフェース定義
namespace EvolutionCMS\Install\Contracts;

interface InstallerInterface
{
    public function validate(): array;
    public function install(): bool;
    public function getErrors(): array;
}

interface ConnectionInterface
{
    public function select(string $query, array $bindings = []): array;
    public function statement(string $query, array $bindings = []): bool;
}

interface SessionInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;
    public function has(string $key): bool;
}
```

**実装:**

```php
class NewInstaller implements InstallerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private SessionInterface $session
    ) {}
    // インターフェースに依存、実装には依存しない
}
```

**メリット:**
- 依存関係の逆転
- テスト時のモック作成が容易
- 実装の切り替えが容易

### 5. イベントシステム

```php
namespace EvolutionCMS\Install\Event;

class EventDispatcher
{
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(string $event, $data = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            $listener($data);
        }
    }
}
```

**使用:**

```php
// イベントリスナー登録
$events->listen('install.started', function($data) {
    $logger->info('Installation started', $data);
});

$events->listen('install.completed', function($data) {
    $logger->info('Installation completed', $data);
    $this->sendNotification($data);
});

// イベント発火
$events->dispatch('install.started', ['version' => $version]);
// ... インストール処理 ...
$events->dispatch('install.completed', ['duration' => $duration]);
```

**拡張性:**
- プラグインシステムの基盤
- フックポイントの提供
- 疎結合なコードの実現

### 6. ミドルウェアパターン

```php
namespace EvolutionCMS\Install\Http\Middleware;

interface MiddlewareInterface
{
    public function handle($request, callable $next);
}

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle($request, callable $next)
    {
        if ($request->isMethod('POST')) {
            $this->validateCsrfToken($request);
        }

        return $next($request);
    }
}

class AuthMiddleware implements MiddlewareInterface
{
    public function handle($request, callable $next)
    {
        if (!$this->isAuthenticated()) {
            return redirect('/auth');
        }

        return $next($request);
    }
}
```

**ミドルウェアスタック:**

```php
class Application
{
    private array $middleware = [];

    public function middleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function handle($request)
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function($next, $middleware) {
                return function($request) use ($next, $middleware) {
                    return $middleware->handle($request, $next);
                };
            },
            function($request) {
                return $this->processRequest($request);
            }
        );

        return $pipeline($request);
    }
}
```

### 7. バリューオブジェクト

```php
namespace EvolutionCMS\Install\ValueObject;

readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public string $database,
        public string $username,
        public string $password,
        public string $prefix = '',
        public string $charset = 'utf8mb4'
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['host'] ?? 'localhost',
            $data['database'] ?? '',
            $data['username'] ?? '',
            $data['password'] ?? '',
            $data['prefix'] ?? '',
            $data['charset'] ?? 'utf8mb4'
        );
    }

    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'prefix' => $this->prefix,
            'charset' => $this->charset
        ];
    }
}
```

**使用:**

```php
$config = DatabaseConfig::fromArray($_POST);
$connection = new Connection($config);
```

### 8. エラーハンドリング

```php
namespace EvolutionCMS\Install\Exception;

// 基底例外
class InstallException extends \Exception {}

// 具体的な例外
class DatabaseConnectionException extends InstallException {}
class ValidationException extends InstallException {}
class FilePermissionException extends InstallException {}

// 使用
try {
    $connection = new Connection($config);
} catch (DatabaseConnectionException $e) {
    $logger->error('Database connection failed', [
        'error' => $e->getMessage(),
        'config' => $config->toArray()
    ]);

    return $this->view->render('error/database', [
        'error' => $e,
        'solution' => '接続情報を確認してください'
    ]);
}
```

### 9. 環境設定管理

```php
namespace EvolutionCMS\Install\Support;

class Env
{
    private static array $variables = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            self::$variables[trim($key)] = trim($value);
        }
    }

    public static function get(string $key, $default = null)
    {
        return self::$variables[$key] ?? $default;
    }
}
```

```.env
# install/.env
DB_HOST=localhost
DB_DATABASE=evolution
DB_USERNAME=root
DB_PASSWORD=secret
DB_PREFIX=modx_

APP_DEBUG=true
LOG_LEVEL=debug
```

### 10. テスト構造

```php
// tests/Unit/Database/QueryBuilderTest.php
namespace EvolutionCMS\Install\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use EvolutionCMS\Install\Database\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    private $connection;
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->builder = new QueryBuilder($this->connection, 'users');
    }

    public function testBasicWhere(): void
    {
        $sql = $this->builder->where('id', 1)->toSql();

        $this->assertEquals(
            'SELECT * FROM modx_users WHERE id = ?',
            $sql
        );
    }

    public function testWhereChaining(): void
    {
        $sql = $this->builder
            ->where('role', 1)
            ->where('blocked', 0)
            ->toSql();

        $this->assertStringContainsString('WHERE role = ?', $sql);
        $this->assertStringContainsString('AND blocked = ?', $sql);
    }
}
```

## ディレクトリ構造（最終形）

```
install/
├── composer.json               # 依存関係管理
├── .env.example                # 環境設定サンプル
├── phpunit.xml                 # テスト設定
├── phpstan.neon                # 静的解析設定
│
├── src/
│   ├── Contracts/              # インターフェース
│   │   ├── InstallerInterface.php
│   │   ├── ConnectionInterface.php
│   │   └── SessionInterface.php
│   │
│   ├── Core/
│   │   ├── Application.php
│   │   ├── Container.php       # PSR-11 DI Container
│   │   └── ServiceProvider.php # サービス登録
│   │
│   ├── Database/
│   │   ├── Connection.php
│   │   ├── QueryBuilder.php
│   │   └── Grammar.php
│   │
│   ├── Http/
│   │   ├── Request.php         # PSR-7互換
│   │   ├── Response.php        # PSR-7互換
│   │   ├── Middleware/
│   │   │   ├── CsrfMiddleware.php
│   │   │   └── AuthMiddleware.php
│   │   └── Controller/
│   │
│   ├── Event/
│   │   ├── EventDispatcher.php
│   │   └── Listener/
│   │
│   ├── Logger/
│   │   └── InstallLogger.php   # PSR-3互換
│   │
│   ├── ValueObject/
│   │   ├── DatabaseConfig.php
│   │   └── SystemRequirements.php
│   │
│   └── Exception/
│       ├── InstallException.php
│       ├── DatabaseConnectionException.php
│       └── ValidationException.php
│
├── config/
│   ├── container.php           # DI設定
│   ├── middleware.php          # ミドルウェア設定
│   └── events.php              # イベントリスナー設定
│
└── tests/
    ├── Unit/
    ├── Integration/
    └── Feature/
```

## 段階的導入計画

### Phase 1-2: 基礎（実施済み+追加）
- [x] PSR-4オートローディング
- [ ] Composer導入
- [ ] strict_types
- [ ] DIコンテナ

### Phase 2-3: DB層
- [ ] モダンQueryBuilder
- [ ] プリペアドステートメント100%
- [ ] インターフェース分離

### Phase 4-5: アプリケーション層
- [ ] ミドルウェアパターン
- [ ] イベントシステム
- [ ] バリューオブジェクト

### Phase 6-8: 品質向上
- [ ] PSR-3ロガー
- [ ] 包括的な例外処理
- [ ] ユニットテスト
- [ ] PHPStan静的解析

### Phase 9-10: 完成
- [ ] 統合テスト
- [ ] ドキュメント
- [ ] コアへの展開計画

## コア全体への展開

インストーラでの成功事例を元に：

1. **Phase 1**: 新規機能でモダン構成を採用
2. **Phase 2**: 既存機能を段階的に移行
3. **Phase 3**: レガシーコードの削除
4. **Phase 4**: 完全移行

## まとめ

- **PSR標準準拠** - 業界標準のベストプラクティス
- **型安全性** - strict_types、型宣言
- **依存性注入** - テスト可能、疎結合
- **イベント駆動** - 拡張性の確保
- **包括的エラーハンドリング** - 安定性向上
- **テスト駆動** - 品質保証

→ **インストーラをモダンPHPの実験場とし、成功後にコア全体に展開**
