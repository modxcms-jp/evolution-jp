# モダンなDB抽象化レイヤー設計書

## 概要

インストーラでモダンなDB抽象化レイヤーを実装し、コア全体への展開を見据えた設計とします。

## 目標

### 1. セキュリティ
- **SQLインジェクション対策の徹底** - プリペアドステートメント100%使用
- **パラメータバインディング** - すべての値をバインド

### 2. モダンなPHPの活用
- **strict_types** - 型安全性の確保
- **タイプヒンティング** - 引数と戻り値の型宣言
- **名前空間** - PSR-4準拠
- **依存性注入** - テスト可能な設計

### 3. 開発体験の向上
- **直感的なAPI** - 読みやすいコード
- **IDE補完** - タイプヒントによる補完
- **エラー検出** - 早期の型エラー検出

### 4. 保守性
- **テスト可能** - ユニットテスト、統合テスト
- **疎結合** - インターフェース分離
- **拡張性** - 将来的な機能追加が容易

## 基本アーキテクチャ

```
src/Database/
├── Connection.php          # PDO接続管理
├── QueryBuilder.php        # クエリビルダー本体
├── Grammar.php             # SQL文法生成（MySQL特化）
├── Expression.php          # Raw SQL式
└── DBFacade.php            # グローバルファサード（DB::table()）
```

## 1. Connection.php

PDO接続の管理と基本操作：

```php
<?php
declare(strict_types=1);

namespace EvolutionCMS\Install\Database;

use PDO;
use PDOException;

class Connection
{
    private PDO $pdo;
    private string $tablePrefix;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        $this->pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->tablePrefix = $config['prefix'] ?? '';
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function select(string $query, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public function selectOne(string $query, array $bindings = []): ?array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function statement(string $query, array $bindings = []): bool
    {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($bindings);
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
```

## 2. QueryBuilder.php

メソッドチェーンによるクエリ構築：

```php
<?php
declare(strict_types=1);

namespace EvolutionCMS\Install\Database;

class QueryBuilder
{
    protected Connection $connection;
    protected string $table;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $columns = ['*'];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $orders = [];
    protected array $joins = [];

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * SELECT句
     */
    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * WHERE句
     */
    public function where($column, $operator = null, $value = null): self
    {
        // where('column', 'value') の形式をサポート
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * JOIN句
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * ORDER BY句
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc'
        ];

        return $this;
    }

    /**
     * LIMIT/OFFSET
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * クエリ実行
     */
    public function get(): array
    {
        $sql = $this->toSql();
        return $this->connection->select($sql, $this->bindings);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function count(): int
    {
        $original = $this->columns;
        $this->columns = ['COUNT(*) as aggregate'];

        $result = $this->first();

        $this->columns = $original;

        return (int)($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * INSERT
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        // 複数行挿入をサポート
        if (!isset($values[0])) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($values), $placeholders));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->prefixTable($this->table),
            implode(', ', $columns),
            $allPlaceholders
        );

        $bindings = [];
        foreach ($values as $row) {
            $bindings = array_merge($bindings, array_values($row));
        }

        return $this->connection->statement($sql, $bindings);
    }

    public function insertGetId(array $values): int
    {
        $this->insert($values);
        return $this->connection->lastInsertId();
    }

    /**
     * UPDATE
     */
    public function update(array $values): int
    {
        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = "$column = ?";
            $bindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->prefixTable($this->table),
            implode(', ', $sets),
            $this->buildWheres()
        );

        $bindings = array_merge($bindings, $this->bindings);

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * DELETE
     */
    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->prefixTable($this->table),
            $this->buildWheres()
        );

        return $this->connection->affectingStatement($sql, $this->bindings);
    }

    public function truncate(): bool
    {
        $sql = sprintf('TRUNCATE TABLE %s', $this->prefixTable($this->table));
        return $this->connection->statement($sql);
    }

    /**
     * SQL生成
     */
    public function toSql(): string
    {
        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->columns),
            $this->prefixTable($this->table)
        );

        // JOINs
        if (!empty($this->joins)) {
            $sql .= ' ' . $this->buildJoins();
        }

        // WHEREs
        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        // ORDER BY
        if (!empty($this->orders)) {
            $sql .= ' ' . $this->buildOrders();
        }

        // LIMIT/OFFSET
        if ($this->limit !== null) {
            $sql .= sprintf(' LIMIT %d', $this->limit);
        }

        if ($this->offset !== null) {
            $sql .= sprintf(' OFFSET %d', $this->offset);
        }

        return $sql;
    }

    protected function buildWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? 'WHERE' : strtoupper($where['boolean']);

            switch ($where['type']) {
                case 'basic':
                    $sql[] = sprintf(
                        '%s %s %s ?',
                        $boolean,
                        $where['column'],
                        $where['operator']
                    );
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $sql[] = sprintf(
                        '%s %s IN (%s)',
                        $boolean,
                        $where['column'],
                        $placeholders
                    );
                    break;

                case 'null':
                    $sql[] = sprintf('%s %s IS NULL', $boolean, $where['column']);
                    break;

                case 'not_null':
                    $sql[] = sprintf('%s %s IS NOT NULL', $boolean, $where['column']);
                    break;
            }
        }

        return ' ' . implode(' ', $sql);
    }

    protected function buildJoins(): string
    {
        $sql = [];

        foreach ($this->joins as $join) {
            $sql[] = sprintf(
                '%s JOIN %s ON %s %s %s',
                strtoupper($join['type']),
                $this->prefixTable($join['table']),
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return implode(' ', $sql);
    }

    protected function buildOrders(): string
    {
        $orders = [];

        foreach ($this->orders as $order) {
            $orders[] = sprintf('%s %s', $order['column'], strtoupper($order['direction']));
        }

        return 'ORDER BY ' . implode(', ', $orders);
    }

    protected function prefixTable(string $table): string
    {
        $prefix = $this->connection->getTablePrefix();

        // エイリアスを含む場合
        if (strpos($table, ' as ') !== false) {
            [$table, $alias] = preg_split('/\s+as\s+/i', $table);
            return $prefix . $table . ' AS ' . $alias;
        }

        return $prefix . $table;
    }
}
```

## 3. DBファサード

グローバルアクセス用のファサード：

```php
<?php
declare(strict_types=1);

namespace EvolutionCMS\Install\Database;

class DB
{
    private static ?Connection $connection = null;

    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    public static function table(string $table): QueryBuilder
    {
        return self::$connection->table($table);
    }

    public static function select(string $query, array $bindings = []): array
    {
        return self::$connection->select($query, $bindings);
    }

    public static function selectOne(string $query, array $bindings = []): ?array
    {
        return self::$connection->selectOne($query, $bindings);
    }

    public static function statement(string $query, array $bindings = []): bool
    {
        return self::$connection->statement($query, $bindings);
    }
}
```

## 使用例

### 基本的な使い方

```php
// 初期化
$connection = new Connection([
    'host' => 'localhost',
    'database' => 'evolution',
    'username' => 'root',
    'password' => 'secret',
    'prefix' => 'modx_',
    'charset' => 'utf8mb4'
]);

DB::setConnection($connection);

// SELECT
$users = DB::table('manager_users')
    ->where('role', 1)
    ->orderBy('username')
    ->get();

// 単一レコード取得
$admin = DB::table('manager_users')
    ->where('username', 'admin')
    ->first();

// カウント
$count = DB::table('site_content')
    ->where('published', 1)
    ->count();

// 存在チェック
if (DB::table('system_settings')->where('setting_name', 'site_name')->exists()) {
    // ...
}
```

### INSERT/UPDATE/DELETE

```php
// INSERT
DB::table('site_templates')->insert([
    'templatename' => 'MyTemplate',
    'content' => '<html>...</html>',
    'category' => 1
]);

// INSERT（IDを取得）
$id = DB::table('categories')->insertGetId([
    'category' => 'New Category'
]);

// UPDATE
DB::table('system_settings')
    ->where('setting_name', 'site_name')
    ->update(['setting_value' => 'New Site Name']);

// DELETE
DB::table('active_users')->delete();

// 条件付きDELETE
DB::table('site_content')
    ->where('published', 0)
    ->where('deleted', 1)
    ->delete();
```

### 複雑なクエリ

```php
// JOIN
$results = DB::table('site_content as c')
    ->leftJoin('document_groups as dg', 'dg.document', '=', 'c.id')
    ->leftJoin('webgroup_access as wga', 'wga.documentgroup', '=', 'dg.document_group')
    ->where('c.published', 1)
    ->whereNotNull('wga.id')
    ->select(['c.*', 'dg.document_group'])
    ->get();

// WHERE IN
$templates = DB::table('site_templates')
    ->whereIn('id', [1, 2, 3])
    ->get();

// NULL チェック
$orphans = DB::table('site_content')
    ->whereNull('parent')
    ->get();
```

### インストーラでの実装例

```php
// src/Asset/TemplateInstaller.php
class TemplateInstaller extends AssetInstaller
{
    protected function install(array $assetData): bool
    {
        $content = file_get_contents($assetData['tpl_file_path']);

        // テンプレートが既存かチェック
        $exists = DB::table('site_templates')
            ->where('templatename', $assetData['templatename'])
            ->exists();

        $data = [
            'templatename' => $assetData['templatename'],
            'content' => preg_replace("@^.*?/\*\*.*?\*/\s+@s", '', $content, 1),
            'description' => $assetData['description'],
            'category' => $this->getCategoryId($assetData['category']),
            'locked' => $assetData['locked']
        ];

        if ($exists) {
            // UPDATE
            return DB::table('site_templates')
                ->where('templatename', $assetData['templatename'])
                ->update($data) > 0;
        } else {
            // INSERT
            return DB::table('site_templates')->insert($data);
        }
    }
}
```

## 将来の拡張

### 1. トランザクション

```php
DB::transaction(function() {
    DB::table('site_content')->insert([...]);
    DB::table('document_groups')->insert([...]);
});
```

### 2. Eloquent ORM風のモデル

```php
class Template extends Model
{
    protected $table = 'site_templates';
    protected $fillable = ['templatename', 'content', 'description'];
}

$template = Template::where('templatename', 'MyTemplate')->first();
$template->update(['content' => 'New content']);
```

### 3. リレーション

```php
class SiteContent extends Model
{
    public function documentGroups()
    {
        return $this->hasMany(DocumentGroup::class, 'document');
    }
}
```

## コア全体への展開

インストーラでの成功後、以下の手順でコア全体に展開：

1. **Phase 1**: `manager/includes/database/` に同じクラスを配置
2. **Phase 2**: 新規コードでQueryBuilderを使用
3. **Phase 3**: 既存コードを段階的に移行
4. **Phase 4**: 旧db()ヘルパーを非推奨化
5. **Phase 5**: 完全移行完了

## テスト

```php
// tests/Unit/Database/QueryBuilderTest.php
class QueryBuilderTest extends TestCase
{
    public function testBasicSelect()
    {
        $qb = new QueryBuilder($this->connection, 'users');
        $sql = $qb->where('id', 1)->toSql();

        $this->assertEquals(
            'SELECT * FROM modx_users WHERE id = ?',
            $sql
        );
    }

    public function testWhereIn()
    {
        $qb = new QueryBuilder($this->connection, 'users');
        $sql = $qb->whereIn('id', [1, 2, 3])->toSql();

        $this->assertEquals(
            'SELECT * FROM modx_users WHERE id IN (?, ?, ?)',
            $sql
        );
    }
}
```

## モダンな設計の特徴

### 1. 型安全性

```php
declare(strict_types=1);

class QueryBuilder
{
    // 引数と戻り値の型を明示
    public function where(string $column, $operator = null, $value = null): self
    public function get(): array
    public function first(): ?array
    public function count(): int
}
```

### 2. プリペアドステートメント100%

```php
// ❌ 従来（安全でない）
$sql = "SELECT * FROM users WHERE id = {$id}";

// ✅ モダン（安全）
$users = DB::table('users')->where('id', $id)->get();
// 内部: SELECT * FROM users WHERE id = ? [bindings: [$id]]
```

### 3. 依存性注入

```php
// コンストラクタインジェクション
class TemplateInstaller
{
    public function __construct(
        private Connection $db,
        private DocBlockParser $parser
    ) {}
}
```

### 4. インターフェース分離

```php
interface ConnectionInterface
{
    public function select(string $query, array $bindings): array;
    public function statement(string $query, array $bindings): bool;
}

// 実装の切り替えが容易（MySQL, PostgreSQL, SQLite等）
```

### 5. テスタビリティ

```php
// モックやスタブが容易
$mockConnection = $this->createMock(Connection::class);
$mockConnection->expects($this->once())
    ->method('select')
    ->willReturn([...]);

$installer = new TemplateInstaller($mockConnection);
```

## 従来の問題点との対比

| 従来 | モダン | 効果 |
|------|--------|------|
| `db()->escape($value)` | プリペアドステートメント | SQLインジェクション対策 |
| グローバル関数 | 依存性注入 | テスト可能性 |
| 型宣言なし | strict_types | 型安全性 |
| 手動SQL文字列生成 | クエリビルダー | 可読性・保守性 |
| エラー抑制（@） | 例外処理 | デバッグ容易性 |

## まとめ

- **セキュリティ重視** - プリペアドステートメント徹底
- **型安全** - strict_types、タイプヒンティング
- **テスト可能** - 依存性注入、インターフェース
- **段階的移行可能** - インストーラ→コア全体へ
- **将来の拡張性** - ORM、リレーション等への発展可能
- **モダンPHPのベストプラクティス** - PSR準拠、PHP 8.x対応
