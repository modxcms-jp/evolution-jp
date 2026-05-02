<?php

/**
 * skill-lib.php
 *
 * 共通ライブラリ: skill-*.phpコマンド群で使用する定数・関数を集約（SSOT）
 */

// ========================================
// 定数定義（SSOT）
// ========================================

/**
 * 学び生成のトリガー値
 */
const SKILL_TRIGGERS = ['execplan_completed', 'user_feedback', 'failure_threshold_exceeded'];

/**
 * learning-request.json の status値
 */
const SKILL_REQUEST_STATUSES = ['pending', 'processing', 'completed', 'skipped'];

/**
 * proposal.json の status値
 */
const SKILL_PROPOSAL_STATUSES = ['proposed', 'approved', 'rejected', 'applied', 'archived'];

/**
 * learning.json の outcome値
 */
const SKILL_OUTCOMES = ['success', 'success_with_rework', 'partial', 'failed', 'cancelled'];

/**
 * learning-request.json の priority値
 */
const SKILL_PRIORITIES = ['low', 'normal', 'high'];

/**
 * trace.jsonl event type値
 */
const SKILL_TRACE_EVENT_TYPES = ['step', 'decision', 'error', 'feedback', 'result'];

/**
 * trace.jsonl agent値
 */
const SKILL_TRACE_AGENTS = ['worker', 'explorer', 'reviewer', 'planner', 'user', 'system'];

/**
 * trace.jsonl status値（step, error, result）
 */
const SKILL_TRACE_STATUSES = ['started', 'ok', 'failed', 'blocked', 'done'];

/**
 * trace.jsonl failure_mode値（error event）
 */
const SKILL_FAILURE_MODES = ['bad_assumption', 'missing_instruction', 'missing_reference', 'repeated_manual_work', 'tool_gap', 'validation_gap'];

/**
 * trace.jsonl feedback_type値（feedback event）
 */
const SKILL_FEEDBACK_TYPES = ['direction_change', 'rework_request', 'scope_change', 'priority_change'];

/**
 * proposal.json change action値
 */
const SKILL_CHANGE_ACTIONS = ['add', 'move', 'merge', 'retire', 'script_extraction'];

/**
 * 予約されたskill名（ディレクトリとして使用不可）
 */
const SKILL_RESERVED_NAMES = ['templates'];

/**
 * 予約されたディレクトリ名（runディレクトリとして使用不可）
 */
const SKILL_RESERVED_DIRS = ['templates', 'archive'];

/**
 * learning-request.json で許可される evidence ファイル
 */
const SKILL_ALLOWED_EVIDENCE = ['trace.jsonl', 'chat.md', 'learning.json', 'pruning.json', 'proposal.json', 'notes.md'];

// ========================================
// 引数パース関数
// ========================================

/**
 * 引数配列から--key=value形式の値を取得
 *
 * @param array $args 引数配列
 * @param string $key キー名（例: 'plan', 'skill'）
 * @param string|null $default デフォルト値
 * @return string|null 値、または未指定時はdefault
 */
function skill_get_arg(array $args, string $key, ?string $default = null): ?string
{
    $prefix = '--' . $key . '=';
    $prefixLen = strlen($prefix);

    foreach ($args as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return trim(substr($arg, $prefixLen));
        }
    }

    return $default;
}

/**
 * 引数配列に指定されたフラグが存在するかチェック
 *
 * @param array $args 引数配列
 * @param string $flag フラグ名（例: 'json', 'strict'）
 * @return bool フラグが存在する場合true
 */
function skill_has_flag(array $args, string $flag): bool
{
    return in_array('--' . $flag, $args, true);
}

/**
 * 引数配列から--key=value形式の整数値を取得（範囲制限あり）
 *
 * @param array $args 引数配列
 * @param string $key キー名
 * @param int $default デフォルト値
 * @param int $min 最小値
 * @param int $max 最大値
 * @return int 範囲内に制限された整数値
 */
function skill_get_int_arg(array $args, string $key, int $default, int $min, int $max): int
{
    $value = skill_get_arg($args, $key);
    if ($value === null) {
        return $default;
    }

    return max($min, min($max, (int)$value));
}

/**
 * 引数配列から--key=value形式の浮動小数点値を取得（範囲制限あり）
 *
 * @param array $args 引数配列
 * @param string $key キー名
 * @param float $default デフォルト値
 * @param float $min 最小値
 * @param float $max 最大値
 * @return float 範囲内に制限された浮動小数点値
 */
function skill_get_float_arg(array $args, string $key, float $default, float $min, float $max): float
{
    $value = skill_get_arg($args, $key);
    if ($value === null) {
        return $default;
    }

    return max($min, min($max, (float)$value));
}

// ========================================
// JSON操作関数
// ========================================

/**
 * JSONファイルを読み込む（寛容版：エラー時null返却）
 *
 * skill-status, skill-prune, skill-sync で使用
 *
 * @param string $path ファイルパス
 * @return array|null 配列、またはエラー時null
 */
function skill_read_json(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * JSONファイルを読み込む（即座終了版：エラー時cli_usage呼び出し）
 *
 * skill-complete, skill-archive で使用
 *
 * @param string $path ファイルパス
 * @param string $label エラーメッセージ用ラベル
 * @return array 配列
 */
function skill_read_json_strict(string $path, string $label): array
{
    if (!is_file($path)) {
        cli_usage("{$label} missing: {$path}");
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        cli_usage("{$label} unreadable: {$path}");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        cli_usage("{$label} invalid JSON: {$path}");
    }

    return $data;
}

/**
 * JSONファイルを読み込む（エラー蓄積版：エラー時$errors配列に追加）
 *
 * skill-validate で使用
 *
 * @param string $path ファイルパス
 * @param string $label エラーメッセージ用ラベル
 * @param array &$errors エラー配列（参照渡し）
 * @return array|null 配列、またはエラー時null
 */
function skill_read_json_with_errors(string $path, string $label, array &$errors): ?array
{
    if (!is_file($path)) {
        $errors[] = "{$label} missing: {$path}";
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        $errors[] = "{$label} unreadable: {$path}";
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $errors[] = "{$label} invalid JSON: {$path}";
        return null;
    }

    return $data;
}

/**
 * 配列をJSON文字列にエンコード（整形済み、UTF-8エスケープなし）
 *
 * @param array $data エンコードする配列
 * @return string JSON文字列
 */
function skill_encode_json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        cli_usage('Failed to encode JSON.');
    }
    return $json . PHP_EOL;
}

/**
 * JSON配列をファイルに書き込む
 *
 * @param string $path ファイルパス
 * @param array $data 書き込む配列
 * @param bool $dryRun trueの場合、実際には書き込まない
 * @return void
 */
function skill_write_json(string $path, array $data, bool $dryRun = false): void
{
    if ($dryRun) {
        return;
    }

    $json = skill_encode_json($data);
    if (file_put_contents($path, $json) === false) {
        cli_usage("Failed to write: {$path}");
    }
    chmod($path, 0644);
}

// ========================================
// バリデーション関数
// ========================================

/**
 * 識別子（plan_id, run_id, skill等）が妥当な形式かチェック
 *
 * @param string $value 検証する値
 * @param bool $allowEmpty 空文字列を許容するか
 * @return bool 妥当ならtrue
 */
function skill_is_valid_identifier(string $value, bool $allowEmpty = false): bool
{
    if ($value === '') {
        return $allowEmpty;
    }

    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value);
}

/**
 * 識別子を検証（不正な場合cli_usageで即座終了）
 *
 * @param string $value 検証する値
 * @param string $label エラーメッセージ用ラベル
 * @param bool $allowEmpty 空文字列を許容するか
 * @return void
 */
function skill_validate_identifier(string $value, string $label, bool $allowEmpty = false): void
{
    if (!skill_is_valid_identifier($value, $allowEmpty)) {
        cli_usage("Invalid {$label}: {$value}");
    }
}

/**
 * 予約語でないことを検証（予約語の場合cli_usageで即座終了）
 *
 * @param string $skill skill名
 * @param bool $allowEmpty 空文字列を許容するか
 * @return void
 */
function skill_validate_not_reserved(string $skill, bool $allowEmpty = false): void
{
    if ($skill === '') {
        if (!$allowEmpty) {
            cli_usage('Skill name cannot be empty');
        }
        return;
    }

    if (in_array($skill, SKILL_RESERVED_NAMES, true)) {
        cli_usage("Reserved skill name: {$skill}");
    }
}

/**
 * skill名を検証（識別子形式 + 予約語チェック）
 *
 * @param string $skill skill名
 * @param bool $allowEmpty 空文字列を許容するか
 * @return void
 */
function skill_validate_skill_name(string $skill, bool $allowEmpty = false): void
{
    skill_validate_identifier($skill, 'skill name', $allowEmpty);
    skill_validate_not_reserved($skill, $allowEmpty);
}

/**
 * 値が許可された値のリストに含まれるか検証（エラー蓄積版）
 *
 * skill-validate で使用
 *
 * @param mixed $value 検証する値
 * @param array $allowed 許可された値のリスト
 * @param string $label エラーメッセージ用ラベル
 * @param array &$errors エラー配列（参照渡し）
 * @return void
 */
function skill_validate_allowed($value, array $allowed, string $label, array &$errors): void
{
    if (!in_array($value, $allowed, true)) {
        $errors[] = "{$label} invalid value: " . (is_scalar($value) ? (string)$value : gettype($value));
    }
}

/**
 * 配列に必須キーが含まれているか検証（エラー蓄積版）
 *
 * skill-validate で使用
 *
 * @param array $data 検証する配列
 * @param array $keys 必須キーのリスト
 * @param string $label エラーメッセージ用ラベル
 * @param array &$errors エラー配列（参照渡し）
 * @return void
 */
function skill_validate_required_keys(array $data, array $keys, string $label, array &$errors): void
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            $errors[] = "{$label} missing key: {$key}";
        }
    }
}

/**
 * パスリスト（文字列配列）が妥当か検証（エラー蓄積版）
 *
 * skill-validate で使用
 *
 * @param mixed $value 検証する値
 * @param string $label エラーメッセージ用ラベル
 * @param array &$errors エラー配列（参照渡し）
 * @return void
 */
function skill_validate_path_list($value, string $label, array &$errors): void
{
    if (!is_array($value)) {
        $errors[] = "{$label} must be an array";
        return;
    }

    foreach ($value as $item) {
        if (!is_string($item) || $item === '' || str_starts_with($item, '/') || strpos($item, '..') !== false) {
            $errors[] = "{$label} contains invalid path: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}

// ========================================
// ヘルパー関数
// ========================================

/**
 * run IDからrunディレクトリのパスを取得
 *
 * @param string $runId run ID
 * @param bool $archived アーカイブディレクトリを参照するか
 * @return string runディレクトリのパス（末尾スラッシュあり）
 */
function skill_get_run_dir(string $runId, bool $archived = false): string
{
    $runsRoot = MODX_BASE_PATH . '.agent/runs/';
    if ($archived) {
        return $runsRoot . 'archive/' . $runId . '/';
    }
    return $runsRoot . $runId . '/';
}

/**
 * skill名からmetadataディレクトリのパスを取得
 *
 * @param string $skill skill名
 * @return string metadataディレクトリのパス（末尾スラッシュあり）
 */
function skill_get_metadata_dir(string $skill): string
{
    return MODX_BASE_PATH . '.agent/skill-metadata/' . $skill . '/';
}

/**
 * learning-request.jsonから識別子（plan_id, run_id, skill）を補完
 *
 * skill-complete, skill-archive で使用
 *
 * @param string $runDir runディレクトリのパス
 * @param string $planId CLI引数で指定されたplan_id（空文字列可）
 * @param string $runId CLI引数で指定されたrun_id（空文字列可）
 * @param string $skill CLI引数で指定されたskill（空文字列可）
 * @return array ['plan_id' => string, 'run_id' => string, 'skill' => string]
 */
function skill_complete_identifiers_from_request(string $runDir, string $planId, string $runId, string $skill): array
{
    $request = skill_read_json_strict($runDir . 'learning-request.json', 'learning-request.json');

    $completedPlanId = $planId;
    $completedSkill = $skill;

    if ($planId === '') {
        $jsonPlanId = $request['plan_id'] ?? null;
        if (!is_string($jsonPlanId)) {
            cli_usage('learning-request.json plan_id must be a string');
        }
        $completedPlanId = $jsonPlanId;
    }

    if ($skill === '') {
        $jsonSkill = $request['skill'] ?? null;
        if (!is_string($jsonSkill)) {
            cli_usage('learning-request.json skill must be a string');
        }
        $completedSkill = $jsonSkill;
    }

    return [
        'plan_id' => $completedPlanId,
        'run_id' => $runId !== '' ? $runId : basename(rtrim($runDir, '/')),
        'skill' => $completedSkill,
    ];
}
