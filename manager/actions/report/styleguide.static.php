<?php
/**
 * スタイルガイド（a=201）。管理画面の部品の見本（a=202）を iframe で表示し、テーマと見え方を選べるようにする。
 * テーマは見本の要求の中だけで差し替えるので、設定は変わらない。
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('settings')) {
    alert()->setError(3);
    alert()->dumpError();
}
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';

$themes = EvoSettingsForm::themes();
$current = (string)evo()->config('manager_theme');
?>
<h1><?= lang('styleguide_title') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate"><a href="#" onclick="EvoShell.go('index.php?a=2', {discard: true}); return false;"><?= lang('cancel') ?></a></li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <p><?= lang('styleguide_msg') ?></p>
        <p class="styleguide-controls">
            <label><?= lang('styleguide_theme') ?>
                <select id="styleguideTheme">
                    <?php foreach ($themes as $name => $label) { ?>
                        <option value="<?= hsc($name) ?>"<?= $name === $current ? ' selected' : '' ?>><?= hsc($label) ?></option>
                    <?php } ?>
                </select>
            </label>
            <label><?= lang('styleguide_font') ?>
                <select id="styleguideFont">
                    <option value=""><?= lang('styleguide_font_theme') ?></option>
                    <option value="12">12px</option>
                    <option value="13">13px</option>
                    <option value="14">14px</option>
                </select>
            </label>
            <label><?= lang('styleguide_view') ?>
                <select id="styleguideView">
                    <option value="current"><?= lang('styleguide_view_current') ?></option>
                    <option value="proposal"><?= lang('styleguide_view_proposal') ?></option>
                    <option value="compare"><?= lang('styleguide_view_compare') ?></option>
                </select>
            </label>
        </p>
        <div class="styleguide-frames" id="styleguideFrames"></div>
    </div>
</div>

<link rel="stylesheet" href="<?= manager_asset('media/style/common/styleguide.css') ?>">

<script>
    (function () {
        const theme = document.getElementById('styleguideTheme');
        const view = document.getElementById('styleguideView');
        const font = document.getElementById('styleguideFont');
        const frames = document.getElementById('styleguideFrames');
        const labels = {
            current: <?= js_literal(lang('styleguide_view_current')) ?>,
            proposal: <?= js_literal(lang('styleguide_view_proposal')) ?>
        };

        // 見本の高さに iframe を合わせる（同じ生成元なので中の文書を読める）
        function fit(iframe) {
            const doc = iframe.contentDocument;
            if (doc && doc.documentElement) {
                iframe.style.height = doc.documentElement.scrollHeight + 'px';
            }
        }

        function render() {
            const kinds = view.value === 'compare' ? ['current', 'proposal'] : [view.value];
            frames.replaceChildren();
            kinds.forEach(function (kind) {
                const figure = document.createElement('figure');
                const caption = document.createElement('figcaption');
                caption.textContent = labels[kind];
                const iframe = document.createElement('iframe');
                iframe.src = 'index.php?a=202&theme=' + encodeURIComponent(theme.value) + (kind === 'proposal' ? '&proposal=1' : '')
                    + (font.value ? '&font=' + encodeURIComponent(font.value) : '');
                iframe.addEventListener('load', function () {
                    fit(iframe);
                    // タブの切り替えなどで高さが変わったら合わせ直す
                    new ResizeObserver(function () { fit(iframe); }).observe(iframe.contentDocument.body);
                });
                figure.append(caption, iframe);
                frames.append(figure);
            });
        }

        theme.addEventListener('change', render);
        view.addEventListener('change', render);
        font.addEventListener('change', render);
        render();
    })();
</script>
