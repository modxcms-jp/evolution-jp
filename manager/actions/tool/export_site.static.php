<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('export_static')) {
    alert()->setError(3);
    alert()->dumpError();
}

// フォームの値は、前回の指定（processor がセッションに保存する）を既定値にする
$exportTarget = sessionv('export_target', 'all');
if (!in_array($exportTarget, ['all', 'allow_ids', 'ignore_ids'], true)) {
    $exportTarget = 'all';
}

// JS に渡す文言（言語ファイルの静的な文字列）
$exportJsKeys = [
    'export_site_progress', 'export_site_running', 'export_site_cancelling', 'export_site_cancelled',
    'export_site_resume_notice', 'export_site_cancelled_notice', 'export_site_error_notice',
    'export_site_busy', 'export_site_request_failed',
    'export_site_result_success', 'export_site_result_skipped', 'export_site_result_failed',
    'export_site_result_elapsed', 'export_site_result_seconds', 'export_site_result_count',
    'export_site_failures_title', 'export_site_failures_more',
];
$exportJsLang = [];
foreach ($exportJsKeys as $key) {
    $exportJsLang[] = js_literal($key) . ':' . js_literal(lang($key));
}
?>

<h1><?= lang('export_site_html') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li
            class="mutate"
            id="Button5"
        ><a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"
            ><?= lang('cancel') ?></a>
        </li>
    </ul>
</div>
<div class="sectionBody">
    <div class="tab-pane" id="exportPane">
        <div class="tab-page" id="tabMain">
            <h2 class="tab"><?= lang('export_site') ?></h2>
            <?php /* 送信は JS が a=308 へ行う。シェルに横取りされないよう data-no-ajax を付ける */ ?>
            <form
                id="exportFrm"
                name="exportFrm"
                action="index.php"
                method="post"
                data-no-ajax
                onsubmit="return false;"
            >
                <style type="text/css">
                    table.settings {
                        width: 100%;
                    }

                    table.settings td.head {
                        white-space: nowrap;
                        vertical-align: top;
                        padding-right: 20px;
                        font-weight: bold;
                    }

                    #exportNotice {
                        margin: 1em 0;
                    }

                    #exportProgressBox progress {
                        width: 100%;
                        max-width: 600px;
                        height: 1.5em;
                    }

                    #exportResult dl {
                        margin: 0.5em 0;
                    }

                    #exportResult dt {
                        float: left;
                        clear: left;
                        width: 8em;
                        font-weight: bold;
                    }

                    #exportResult dd {
                        margin-left: 8em;
                    }

                    #exportFailures {
                        max-height: 20em;
                        overflow: auto;
                    }
                </style>
                <table class="settings" cellspacing="0" cellpadding="2">
                    <tr>
                        <td class="head"><?= lang('export_site_target') ?></td>
                        <td>
                            <label>
                                <input
                                    name="target"
                                    type="radio"
                                    value="all"
                                    <?= $exportTarget === 'all' ? 'checked' : '' ?>
                                ><?= lang('export_site_target_all') ?>
                            </label>
                            <label>
                                <input
                                    name="target"
                                    type="radio"
                                    value="allow_ids"
                                    <?= $exportTarget === 'allow_ids' ? 'checked' : '' ?>
                                ><?= lang('export_site_target_allow') ?>
                            </label>
                            <label>
                                <input
                                    name="target"
                                    type="radio"
                                    value="ignore_ids"
                                    <?= $exportTarget === 'ignore_ids' ? 'checked' : '' ?>
                                ><?= lang('export_site_target_ignore') ?>
                            </label>
                            <div
                                id="allow_ids"
                                style="display:<?= $exportTarget === 'allow_ids' ? 'block' : 'none' ?>"
                            >
                                <input
                                    type="text"
                                    name="allow_ids"
                                    value="<?= hsc(sessionv('export_allow_ids', '')) ?>"
                                    style="width:300px;background-color:#f2fff2;"
                                /><br/>
                                <?= lang('export_site_target_allow_hint') ?>
                            </div>
                            <div
                                id="ignore_ids"
                                style="display:<?= $exportTarget === 'ignore_ids' ? 'block' : 'none' ?>"
                            >
                                <input
                                    type="text"
                                    name="ignore_ids"
                                    value="<?= hsc(sessionv('export_ignore_ids', '')) ?>"
                                    style="width:300px;background-color:#fff2f2;"
                                /><br/>
                                <?= lang('export_site_target_ignore_hint') ?>
                            </div>
                        </td>
                    </tr>
                    <?php $includeNoncache = (bool)sessionv('export_includenoncache'); ?>
                    <tr>
                        <td class="head"><?= lang('export_site_cacheable') ?></td>
                        <td>
                            <label>
                                <input
                                    type="radio"
                                    name="includenoncache"
                                    value="1"
                                    <?= $includeNoncache ? 'checked' : '' ?>
                                ><?= lang('yes') ?>
                            </label>
                            <label>
                                <input
                                    type="radio"
                                    name="includenoncache"
                                    value="0"
                                    <?= $includeNoncache ? '' : 'checked' ?>
                                ><?= lang('no') ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td class="head"><?= lang('export_site_replace_before') ?></td>
                        <td>
                            <input
                                type="text"
                                name="repl_before"
                                value="<?= hsc(sessionv('export_repl_before', MODX_SITE_URL)) ?>"
                                style="width:300px;"/>
                        </td>
                    </tr>
                    <tr>
                        <td class="head"><?= lang('export_site_replace_after') ?></td>
                        <td>
                            <input
                                type="text"
                                name="repl_after"
                                value="<?= hsc(sessionv('export_repl_after', MODX_SITE_URL)) ?>"
                                style="width:300px;"
                            />
                        </td>
                    </tr>
                </table>

                <ul class="actionButtons" id="exportStartButtons">
                    <li>
                        <a
                            href="#"
                            class="default"
                            id="exportStart"
                        ><?= lang('export_site_start') ?></a>
                    </li>
                </ul>
            </form>

            <div id="exportNotice" role="status" hidden></div>
            <ul class="actionButtons" id="exportChoice" hidden>
                <li>
                    <a href="#" class="default" id="exportResume"><?= lang('export_site_resume') ?></a>
                </li>
                <li>
                    <a href="#" id="exportRestart"><?= lang('export_site_restart') ?></a>
                </li>
            </ul>

            <div id="exportProgressBox" hidden>
                <progress id="exportProgress" value="0" max="1"></progress>
                <p>
                    <span id="exportCount"></span>
                </p>
                <ul class="actionButtons">
                    <li>
                        <a href="#" id="exportCancel"><?= lang('export_site_cancel') ?></a>
                    </li>
                </ul>
            </div>

            <div id="exportMessages"></div>
            <div id="exportResult" hidden></div>
        </div>
        <div class="tab-page" id="tabHelp">
            <h2 class="tab"><?= lang('help') ?></h2>
            <?php
            echo '<p>' . evo()->parseText(
                lang('export_site_message'),
                ['export_dir' => hsc(str_replace('[(base_path)]', '', config('export_dir')))]
            ) . '</p>';
            ?>
        </div>
    </div>
</div>

<script>
    tpExport = new WebFXTabPane(document.getElementById("exportPane"));
    (function () {
        'use strict';
        const T = {<?= implode(',', $exportJsLang) ?>};
        const ENDPOINT = 'index.php?a=308';
        const $ = function (id) {
            return document.getElementById(id);
        };
        const form = $('exportFrm');
        const els = {
            startButtons: $('exportStartButtons'),
            start: $('exportStart'),
            notice: $('exportNotice'),
            choice: $('exportChoice'),
            resume: $('exportResume'),
            restart: $('exportRestart'),
            box: $('exportProgressBox'),
            progress: $('exportProgress'),
            count: $('exportCount'),
            cancel: $('exportCancel'),
            messages: $('exportMessages'),
            result: $('exportResult')
        };
        let running = false;
        let cancelling = false;

        const fill = function (template, values) {
            return Object.keys(values).reduce(function (text, key) {
                return text.split('[+' + key + '+]').join(String(values[key]));
            }, template);
        };
        const sleep = function (ms) {
            return new Promise(function (resolve) {
                window.setTimeout(resolve, ms);
            });
        };
        const setNotice = function (text, html) {
            if (!text) {
                els.notice.hidden = true;
                els.notice.textContent = '';
                return;
            }
            // html は言語ファイルの静的な文字列だけ。利用者のデータは textContent で入れる
            if (html) {
                els.notice.innerHTML = text;
            } else {
                els.notice.textContent = text;
            }
            els.notice.hidden = false;
        };
        const setFormDisabled = function (disabled) {
            Array.prototype.forEach.call(form.elements, function (el) {
                el.disabled = disabled;
            });
            els.startButtons.hidden = disabled;
        };

        // サーバーに送る。通信に失敗したら間をおいて数回やり直す
        const call = async function (op, extra) {
            const data = Object.assign({op: op}, extra || {});
            for (let attempt = 0; attempt < 5; attempt++) {
                try {
                    const response = await Evo.post(ENDPOINT, data);
                    return await response.json();
                } catch (e) {
                    setNotice(T.export_site_request_failed);
                    await sleep(2000);
                }
            }
            return {state: 'error', message: T.export_site_request_failed};
        };

        const formData = function () {
            const get = function (name) {
                const el = form.elements[name];
                return el ? el.value : '';
            };
            const checked = form.querySelector('input[name="target"]:checked');
            const noncache = form.querySelector('input[name="includenoncache"]:checked');
            return {
                target: checked ? checked.value : 'all',
                allow_ids: get('allow_ids'),
                ignore_ids: get('ignore_ids'),
                includenoncache: noncache ? noncache.value : '0',
                repl_before: get('repl_before'),
                repl_after: get('repl_after')
            };
        };

        const renderProgress = function (res) {
            const total = Number(res.total) || 0;
            const done = Number(res.done) || 0;
            els.box.hidden = false;
            els.progress.max = Math.max(total, 1);
            els.progress.value = total === 0 ? els.progress.max : done;
            els.count.textContent = fill(T.export_site_progress, {done: done, total: total});
        };

        const renderMessages = function (res) {
            // イベントの出力はプラグインの作者が書く HTML
            els.messages.innerHTML = (res.messages || []).join('\n');
        };

        const renderResult = function (res) {
            const list = document.createElement('dl');
            const add = function (label, value) {
                const dt = document.createElement('dt');
                const dd = document.createElement('dd');
                dt.textContent = label;
                dd.textContent = value;
                list.appendChild(dt);
                list.appendChild(dd);
            };
            add(T.export_site_result_elapsed, fill(T.export_site_result_seconds, {sec: res.elapsed}));
            add(T.export_site_result_success, fill(T.export_site_result_count, {count: res.success}));
            if (Number(res.skipped) > 0) {
                add(T.export_site_result_skipped, fill(T.export_site_result_count, {count: res.skipped}));
            }
            add(T.export_site_result_failed, fill(T.export_site_result_count, {count: res.failed}));

            els.result.textContent = '';
            els.result.appendChild(list);

            const failures = res.failures || [];
            if (failures.length > 0) {
                const title = document.createElement('h3');
                title.textContent = T.export_site_failures_title;
                const ul = document.createElement('ul');
                ul.id = 'exportFailures';
                failures.forEach(function (f) {
                    const li = document.createElement('li');
                    // ページタイトルは利用者のデータなので textContent で入れる
                    li.textContent = 'id=' + f.id + ' ' + f.pagetitle + ' - ' + f.reason;
                    ul.appendChild(li);
                });
                els.result.appendChild(title);
                els.result.appendChild(ul);
                const more = Number(res.failed) - failures.length;
                if (more > 0) {
                    const p = document.createElement('p');
                    p.textContent = fill(T.export_site_failures_more, {count: more});
                    els.result.appendChild(p);
                }
            }
            els.result.hidden = false;
        };

        // 終わった（done / cancelled / error）。やり直せる状態に戻す
        const showFinished = function (res) {
            running = false;
            cancelling = false;
            els.cancel.hidden = true;
            els.choice.hidden = true;
            renderProgress(res);
            renderMessages(res);
            if (res.state === 'cancelled') {
                setNotice(T.export_site_cancelled);
            } else if (res.state === 'error') {
                setNotice(res.message || T.export_site_request_failed, true);
            } else {
                setNotice('');
            }
            if (res.state !== 'error' || res.total) {
                renderResult(res);
            }
            setFormDisabled(false);
        };

        // 続きを進めるを、終わるまで繰り返す
        const loop = async function () {
            if (running) {
                return;
            }
            running = true;
            setFormDisabled(true);
            els.choice.hidden = true;
            els.result.hidden = true;
            els.cancel.hidden = false;
            setNotice(T.export_site_running);
            while (running) {
                const res = await call(cancelling ? 'cancel' : 'step');
                if (!running) {
                    return;
                }
                if (res.state === 'running') {
                    renderProgress(res);
                    if (cancelling) {
                        await sleep(300);
                    }
                    continue;
                }
                if (res.state === 'busy') {
                    setNotice(T.export_site_busy);
                    await sleep(1500);
                    continue;
                }
                showFinished(res);
                return;
            }
        };

        const start = async function () {
            if (running) {
                return;
            }
            setNotice('');
            els.messages.textContent = '';
            els.result.hidden = true;
            const res = await call('start', formData());
            if (res.state === 'busy') {
                setNotice(T.export_site_busy);
                return;
            }
            if (res.state === 'error') {
                setNotice(res.message || T.export_site_request_failed, true);
                return;
            }
            renderProgress(res);
            renderMessages(res);
            loop();
        };

        const cancel = async function () {
            if (!running || cancelling) {
                return;
            }
            cancelling = true;
            setNotice(T.export_site_cancelling);
            // 処理中の続きを進めるを待たずに、中止の印を書かせる
            const res = await call('cancel');
            if (res.state === 'cancelled' || res.state === 'done' || res.state === 'error') {
                showFinished(res);
            }
        };

        const bind = function (el, handler) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                handler();
            });
        };
        bind(els.start, start);
        bind(els.restart, start);
        bind(els.resume, loop);
        bind(els.cancel, cancel);

        // 出力する範囲に合わせて ID の入力欄を出し入れする
        const show = function (id, visible) {
            const el = $(id);
            if (el) {
                el.style.display = visible ? 'block' : 'none';
            }
        };
        form.querySelectorAll('input[type="radio"][name="target"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const checked = form.querySelector('input[name="target"]:checked');
                const value = checked ? checked.value : '';
                show('allow_ids', value === 'allow_ids');
                show('ignore_ids', value === 'ignore_ids');
            });
        });

        // 開いたとき、保存したジョブがあれば状態を見せる
        call('status').then(function (res) {
            if (!res || res.state === 'none') {
                return;
            }
            renderProgress(res);
            renderMessages(res);
            if (res.state === 'running') {
                setNotice(T.export_site_resume_notice);
                els.choice.hidden = false;
                els.cancel.hidden = true;
                return;
            }
            if (res.state === 'cancelled') {
                setNotice(T.export_site_cancelled_notice);
            } else if (res.state === 'error') {
                setNotice(T.export_site_error_notice + ' ' + (res.message || ''), true);
            }
            renderResult(res);
            els.cancel.hidden = true;
        });
    })();
</script>
