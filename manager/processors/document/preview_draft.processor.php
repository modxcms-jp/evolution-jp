<?php
/**
 * プレビュー用の一時的な下書きの受け口（a=309）。POST のみ、JSON 応答。
 *
 * 編集中のフォームの値を、セッションの preview_drafts に置いてキーを返す。
 * 画面はこのキーを付けた文書の URL（?mgr_preview=<キー>。mgr_preview = 管理画面からのプレビュー）を GET で開く。
 * 置き場の決まり（件数の上限・期限）は DocumentParser の storePreviewDraft() / loadPreviewDraft() にある。
 *
 * 応答: {"url":"<文書の URL>"} か {"error":"…"}
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!is_post()) {
    previewDraftJson(['error' => 'Method Not Allowed'], 405);
}
if (!hasPermission('edit_document')) {
    previewDraftJson(['error' => lang('error_no_privileges')], 403);
}

$docid = post_intvar('id');
if ($docid <= 0) {
    previewDraftJson(['error' => 'Bad Request'], 400);
}

// 下書きを置く前に、開く先の URL を確かめる（エイリアスが無いと makeUrl() は空を返す）
$url = evo()->makeUrl($docid, '', '', 'full', true);
if (!$url) {
    previewDraftJson(['error' => lang('error_occurred')], 500);
}

// 編集フォームのすべての項目（TV・配列を含む）を getPreviewObject() の規則で変換するため、全体を渡す。
// 値は DB に入れず、セッションに置いてプレビューの表示にだけ使う。
try {
    $key = evo()->storePreviewDraft($docid, postv());
} catch (Throwable $e) {
    previewDraftJson(['error' => lang('error_occurred')], 500);
}

$url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query(['mgr_preview' => $key]);
previewDraftJson(['url' => $url]);

function previewDraftJson(array $data, $status = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}
