# 関数の整理計画

## 現状の問題：関数定義の散在

インストーラ内で関数定義が複数のファイルに散在しており、以下の問題が発生しています：

### 1. 重複する機能

同じような機能を持つ関数が複数のファイルで定義されている：

**エラー/成功表示関数:**
```php
// instprocessor.php:238-246
function ok($name, $msg) { return sprintf('<p>&nbsp;&nbsp;%s: <span class="ok">%s</span></p>', ...); }
function ng($name, $msg) { return sprintf('<p>&nbsp;&nbsp;%s: <span class="notok">%s</span></p>', ...); }

// actions/summary.php:257-268
function echo_ok() { return '<span class="ok">' . lang('ok') . '</span>'; }
function echo_failed($msg = NULL) { return '<span class="notok">' . $msg . '</span>'; }

// functions.php:415-430
function result($status = 'ok', $ph = array()) { /* 別の実装 */ }
```

**HTML生成関数:**
```php
// actions/summary.php:295-298
function p($str) { return '<p>' . $str . '</p>'; }

// functions.php内には類似の機能が埋め込まれている
```

### 2. ファイルごとの関数定義

| ファイル | 行数 | 定義されている関数 |
|---------|------|-------------------|
| **functions.php** | 559 | 30+ 関数（グローバルヘルパー） |
| **actions/summary.php** | 299 | 3関数（echo_ok, echo_failed, mkd, p） |
| **actions/options.php** | 225 | 10関数（block_*, is_demo, is_check） |
| **instprocessor.php** | 251 | 4関数（deleteCacheDirectory, ok, ng, showError） |
| **setup.info.php** | 192 | 2関数（get_installset, add_version_strings） |

### 3. 関数のスコープ問題

**グローバル関数（functions.php）:**
```php
function setOption($fieldName, $value = '') { ... }
function getOption($fieldName) { ... }
function browser_lang() { ... }
function includeLang($lang_name, $dir = 'langs/') { ... }
function key_field($category = '') { ... }
function table_name($category = '') { ... }
function mode($category) { ... }
function compare_check($params) { ... }
function parse_docblock($fullpath) { ... }
function clean_up($sqlParser) { ... }
function propUpdate($new, $old) { ... }
function getCreateDbCategory($category) { ... }
function is_webmatrix() { ... }
function is_iis() { ... }
function isUpGradeable() { ... }
function parseProperties($propertyString) { ... }
function result($status = 'ok', $ph = array()) { ... }
function get_langs() { ... }
function get_lang_options($lang_name) { ... }
function collectTpls($path) { ... }
function ph() { ... }
function install_sessionCheck() { ... }
function getLast($array = array()) { ... }
function lang_name() { ... }
function withSample($installset) { ... }
function convert2utf8mb4() { ... }
```

**ファイルローカル関数（actions/summary.php）:**
```php
function echo_ok() { ... }
function echo_failed($msg = NULL) { ... }
function mkd($path) { ... }
function p($str) { ... }
```

**ファイルローカル関数（actions/options.php）:**
```php
function show_object_list($ph) { ... }
function block_install_sample_site($ph) { ... }
function block_templates($tplTemplates, $ph) { ... }
function block_tvs($tplTVs, $ph) { ... }
function block_chunks($tplChunks, $ph) { ... }
function block_modules($tplModules, $ph) { ... }
function block_plugins($tplPlugins, $ph) { ... }
function block_snippets($tplSnippets, $ph) { ... }
function is_demo($option) { ... }
function is_check($elements, $num) { ... }
```

**ファイルローカル関数（instprocessor.php）:**
```php
function deleteCacheDirectory($cachePath) { ... }
function ok($name, $msg) { ... }
function ng($name, $msg) { ... }
function showError() { ... }
```

**ファイルローカル関数（setup.info.php）:**
```php
function get_installset($params) { ... }
function add_version_strings($params) { ... }
```

### 4. 問題点

1. **名前の衝突リスク**
   - グローバル名前空間に30以上の関数
   - 他のコードとの衝突可能性

2. **重複と不統一**
   - `ok()` vs `echo_ok()` vs `result()` - 同じ目的で異なる実装
   - HTML生成が散在

3. **再利用性の低さ**
   - ファイルローカル関数は他で使えない
   - 共通処理の重複

4. **テスト困難**
   - グローバル関数の単体テスト困難
   - 依存関係が不明瞭

5. **可読性の低下**
   - どのファイルにどの関数があるか不明
   - 機能の発見が困難

---

## 解決策：関数の整理とクラス化

### Phase 8での実装計画

すべての関数を機能ごとにクラスに整理：

### 1. セッション関連

**現状（functions.php）:**
```php
function setOption($fieldName, $value = '') { ... }
function getOption($fieldName) { ... }
function install_sessionCheck() { ... }
```

**移行後（src/Session/InstallSession.php）:**
```php
namespace EvolutionCMS\Install\Session;

class InstallSession
{
    public function set(string $key, $value): void { ... }
    public function get(string $key, $default = null) { ... }
    public function check(): bool { ... }
    public function has(string $key): bool { ... }
    public function all(): array { ... }
    public function clear(): void { ... }
}
```

### 2. 言語関連

**現状（functions.php）:**
```php
function browser_lang() { ... }
function includeLang($lang_name, $dir = 'langs/') { ... }
function get_langs() { ... }
function get_lang_options($lang_name) { ... }
function lang_name() { ... }
```

**移行後（src/Support/LanguageHelper.php）:**
```php
namespace EvolutionCMS\Install\Support;

class LanguageHelper
{
    public function detectBrowserLanguage(): string { ... }
    public function loadLanguage(string $name): array { ... }
    public function getAvailableLanguages(): array { ... }
    public function getLanguageOptions(string $selected): string { ... }
    public function getCurrentLanguage(): string { ... }
}
```

### 3. アセット関連

**現状（functions.php + setup.info.php）:**
```php
function key_field($category = '') { ... }
function table_name($category = '') { ... }
function mode($category) { ... }
function compare_check($params) { ... }
function parse_docblock($fullpath) { ... }
function collectTpls($path) { ... }
function get_installset($params) { ... }
function add_version_strings($params) { ... }
```

**移行後（複数のクラスに分割）:**

```php
// src/Support/DocBlockParser.php
namespace EvolutionCMS\Install\Support;

class DocBlockParser
{
    public function parse(string $filePath): array { ... }
    public function extractVersion(array $params): string { ... }
}

// src/Asset/AssetCollector.php
namespace EvolutionCMS\Install\Asset;

class AssetCollector
{
    public function collectTemplates(string $path): array { ... }
    public function collectAll(): array { ... }
}

// src/Asset/AssetComparator.php
namespace EvolutionCMS\Install\Asset;

class AssetComparator
{
    public function compare(array $params): string { ... }
    public function needsUpdate(array $new, array $old): bool { ... }
}

// src/Asset/AssetMetadata.php
namespace EvolutionCMS\Install\Asset;

class AssetMetadata
{
    public function getKeyField(string $category): string { ... }
    public function getTableName(string $category): string { ... }
    public function getComparisonMode(string $category): string { ... }
}
```

### 4. データベース関連

**現状（functions.php）:**
```php
function getCreateDbCategory($category) { ... }
```

**移行後（src/Database/CategoryManager.php）:**
```php
namespace EvolutionCMS\Install\Database;

class CategoryManager
{
    public function getOrCreate(string $name): int { ... }
    public function exists(string $name): bool { ... }
}
```

### 5. アップグレード関連

**現状（functions.php）:**
```php
function isUpGradeable() { ... }
function clean_up($sqlParser) { ... }
function convert2utf8mb4() { ... }
```

**移行後（複数のクラスに分割）:**

```php
// src/Installer/UpgradeDetector.php
namespace EvolutionCMS\Install\Installer;

class UpgradeDetector
{
    public function isUpgradeable(): bool { ... }
    public function getExistingVersion(): string { ... }
}

// src/Installer/CleanupService.php
namespace EvolutionCMS\Install\Installer;

class CleanupService
{
    public function cleanUp(): void { ... }
    public function updateSecuritySettings(): void { ... }
}

// src/Migration/Utf8mb4Converter.php
namespace EvolutionCMS\Install\Migration;

class Utf8mb4Converter
{
    public function convert(): void { ... }
    public function isRequired(): bool { ... }
}
```

### 6. プロパティ処理

**現状（functions.php）:**
```php
function propUpdate($new, $old) { ... }
function parseProperties($propertyString) { ... }
```

**移行後（src/Asset/PropertyMerger.php）:**
```php
namespace EvolutionCMS\Install\Asset;

class PropertyMerger
{
    public function merge(string $new, string $old): string { ... }
    public function parse(string $propertyString): array { ... }
}
```

### 7. HTML/ビューヘルパー

**現状（複数ファイルに散在）:**
```php
// instprocessor.php
function ok($name, $msg) { ... }
function ng($name, $msg) { ... }

// actions/summary.php
function echo_ok() { ... }
function echo_failed($msg = NULL) { ... }
function p($str) { ... }

// functions.php
function result($status = 'ok', $ph = array()) { ... }
```

**移行後（src/View/ViewHelper.php）:**
```php
namespace EvolutionCMS\Install\View;

class ViewHelper
{
    public function success(string $message): string { ... }
    public function error(string $message): string { ... }
    public function warning(string $message): string { ... }
    public function paragraph(string $content): string { ... }
    public function statusMessage(string $status, array $data): string { ... }
}
```

### 8. ビュー生成関数

**現状（actions/options.php）:**
```php
function show_object_list($ph) { ... }
function block_install_sample_site($ph) { ... }
function block_templates($tplTemplates, $ph) { ... }
function block_tvs($tplTVs, $ph) { ... }
function block_chunks($tplChunks, $ph) { ... }
function block_modules($tplModules, $ph) { ... }
function block_plugins($tplPlugins, $ph) { ... }
function block_snippets($tplSnippets, $ph) { ... }
function is_demo($option) { ... }
function is_check($elements, $num) { ... }
```

**移行後（src/View/OptionsRenderer.php）:**
```php
namespace EvolutionCMS\Install\View;

class OptionsRenderer
{
    public function renderObjectList(array $data): string { ... }
    public function renderSampleSiteBlock(array $data): string { ... }
    public function renderAssetBlock(string $type, array $assets, array $data): string { ... }
    public function isDemoAsset(array $option): bool { ... }
    public function isChecked(array $elements, int $index): bool { ... }
}
```

### 9. ユーティリティ関数

**現状（functions.php）:**
```php
function is_webmatrix() { ... }
function is_iis() { ... }
function getLast($array = array()) { ... }
function withSample($installset) { ... }
function ph() { ... }
```

**移行後（複数のクラスに分割）:**

```php
// src/Support/ServerDetector.php
namespace EvolutionCMS\Install\Support;

class ServerDetector
{
    public function isWebMatrix(): bool { ... }
    public function isIIS(): bool { ... }
    public function getServerType(): string { ... }
}

// src/Support/ArrayHelper.php
namespace EvolutionCMS\Install\Support;

class ArrayHelper
{
    public function getLast(array $array) { ... }
}

// src/View/PlaceholderBuilder.php
namespace EvolutionCMS\Install\View;

class PlaceholderBuilder
{
    public function buildCommonPlaceholders(): array { ... }
    public function merge(array ...$placeholders): array { ... }
}
```

### 10. ディレクトリ/ファイル操作

**現状（actions/summary.php + instprocessor.php）:**
```php
// actions/summary.php:270-293
function mkd($path) { ... }

// instprocessor.php:221-236
function deleteCacheDirectory($cachePath) { ... }
```

**移行後（src/Support/FileHelper.php）:**
```php
namespace EvolutionCMS\Install\Support;

class FileHelper
{
    public function createDirectory(string $path, int $permissions = 0755): bool { ... }
    public function deleteDirectory(string $path): void { ... }
    public function ensureWritable(string $path): bool { ... }
}
```

---

## 移行マップ（完全版）

| 既存関数 | 既存ファイル | 移行先クラス | 移行先メソッド |
|---------|------------|-------------|--------------|
| `setOption()` | functions.php | `Session\InstallSession` | `set()` |
| `getOption()` | functions.php | `Session\InstallSession` | `get()` |
| `install_sessionCheck()` | functions.php | `Session\InstallSession` | `check()` |
| `browser_lang()` | functions.php | `Support\LanguageHelper` | `detectBrowserLanguage()` |
| `includeLang()` | functions.php | `Support\LanguageHelper` | `loadLanguage()` |
| `get_langs()` | functions.php | `Support\LanguageHelper` | `getAvailableLanguages()` |
| `get_lang_options()` | functions.php | `Support\LanguageHelper` | `getLanguageOptions()` |
| `lang_name()` | functions.php | `Support\LanguageHelper` | `getCurrentLanguage()` |
| `parse_docblock()` | functions.php | `Support\DocBlockParser` | `parse()` |
| `collectTpls()` | functions.php | `Asset\AssetCollector` | `collectTemplates()` |
| `compare_check()` | functions.php | `Asset\AssetComparator` | `compare()` |
| `key_field()` | functions.php | `Asset\AssetMetadata` | `getKeyField()` |
| `table_name()` | functions.php | `Asset\AssetMetadata` | `getTableName()` |
| `mode()` | functions.php | `Asset\AssetMetadata` | `getComparisonMode()` |
| `propUpdate()` | functions.php | `Asset\PropertyMerger` | `merge()` |
| `parseProperties()` | functions.php | `Asset\PropertyMerger` | `parse()` |
| `getCreateDbCategory()` | functions.php | `Database\CategoryManager` | `getOrCreate()` |
| `isUpGradeable()` | functions.php | `Installer\UpgradeDetector` | `isUpgradeable()` |
| `clean_up()` | functions.php | `Installer\CleanupService` | `cleanUp()` |
| `convert2utf8mb4()` | functions.php | `Migration\Utf8mb4Converter` | `convert()` |
| `is_webmatrix()` | functions.php | `Support\ServerDetector` | `isWebMatrix()` |
| `is_iis()` | functions.php | `Support\ServerDetector` | `isIIS()` |
| `getLast()` | functions.php | `Support\ArrayHelper` | `getLast()` |
| `withSample()` | functions.php | `Asset\AssetCollector` | `shouldIncludeSample()` |
| `ph()` | functions.php | `View\PlaceholderBuilder` | `buildCommonPlaceholders()` |
| `result()` | functions.php | `View\ViewHelper` | `statusMessage()` |
| `ok()` | instprocessor.php | `View\ViewHelper` | `success()` |
| `ng()` | instprocessor.php | `View\ViewHelper` | `error()` |
| `showError()` | instprocessor.php | `View\ViewHelper` | `showDatabaseError()` |
| `echo_ok()` | summary.php | `View\ViewHelper` | `successBadge()` |
| `echo_failed()` | summary.php | `View\ViewHelper` | `errorBadge()` |
| `p()` | summary.php | `View\ViewHelper` | `paragraph()` |
| `mkd()` | summary.php | `Support\FileHelper` | `createDirectory()` |
| `deleteCacheDirectory()` | instprocessor.php | `Support\FileHelper` | `deleteDirectory()` |
| `show_object_list()` | options.php | `View\OptionsRenderer` | `renderObjectList()` |
| `block_install_sample_site()` | options.php | `View\OptionsRenderer` | `renderSampleSiteBlock()` |
| `block_templates()` | options.php | `View\OptionsRenderer` | `renderAssetBlock()` |
| `block_tvs()` | options.php | `View\OptionsRenderer` | `renderAssetBlock()` |
| `block_chunks()` | options.php | `View\OptionsRenderer` | `renderAssetBlock()` |
| `block_modules()` | options.php | `View\OptionsRenderer` | `renderAssetBlock()` |
| `block_plugins()` | options.php | `View\OptionsRenderer` | `renderAssetBlock()` |
| `block_snippets()` | options.php | `View\OptionsRenderer` | `renderAssetBlock()` |
| `is_demo()` | options.php | `View\OptionsRenderer` | `isDemoAsset()` |
| `is_check()` | options.php | `View\OptionsRenderer` | `isChecked()` |
| `get_installset()` | setup.info.php | `Asset\AssetCollector` | `getInstallSet()` |
| `add_version_strings()` | setup.info.php | `Support\DocBlockParser` | `formatVersionString()` |

---

## 後方互換性

移行期間中、既存の関数はファサードとして残す：

```php
// install/functions.php（縮小版）

// セッション関連のファサード
function setOption($fieldName, $value = '') {
    return app()->session->set($fieldName, $value);
}

function getOption($fieldName) {
    return app()->session->get($fieldName);
}

// 言語関連のファサード
function browser_lang() {
    return app()->language->detectBrowserLanguage();
}

function includeLang($lang_name, $dir = 'langs/') {
    return app()->language->loadLanguage($lang_name);
}

// その他も同様...
```

すべてのクラス実装が完了し、テストが通ったら、ファサード関数を段階的に削除します。

---

## 実装スケジュール

Phase 8（1-2週間）で実装：

**Week 1:**
- [ ] クラス構造の作成（全クラスファイル作成）
- [ ] 優先度高い関数から移行（Session, Language, Database）
- [ ] 単体テストの作成

**Week 2:**
- [ ] 残りの関数移行（Asset, View, Support）
- [ ] ファサード関数の作成
- [ ] 統合テスト
- [ ] 既存コードでの動作確認

---

## 期待される効果

1. **可読性向上** - 機能ごとに整理され、発見しやすい
2. **テスト可能** - クラスメソッドとして単体テスト可能
3. **再利用性** - 依存性注入で柔軟に利用可能
4. **保守性向上** - 責任の所在が明確
5. **名前空間の整理** - グローバル汚染の解消
