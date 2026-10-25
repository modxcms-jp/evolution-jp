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
    'export_site_busy', 'export_site_job_replaced', 'export_site_request_failed', 'export_site_unreadable',
    'export_site_result_success', 'export_site_result_skipped', 'export_site_result_failed',
    'export_site_result_elapsed', 'export_site_result_seconds', 'export_site_result_count',
    'export_site_failures_title', 'export_site_failures_more', 'export_site_previous_title',
    'export_site_done', 'export_site_done_with_failures', 'export_site_finished_at',
    'export_site_done_dialog_title', 'export_site_done_dialog', 'export_site_done_dialog_with_failures',
    'export_site_error_dialog_title', 'export_site_error_dialog',
    'export_site_eta', 'export_site_eta_calculating', 'export_site_eta_seconds', 'export_site_eta_minutes',
    'export_site_eta_hours',
    'export_site_title_done', 'export_site_title_failed', 'export_site_title_stopped',
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
                href="index.php?a=2"
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

                    #exportPane [hidden] {
                        display: none !important;
                    }

                    #exportNotice {
                        margin: 1em 0;
                    }

                    #exportBar {
                        position: relative;
                        max-width: 600px;
                    }

                    /* 処理中は縞を重ねて、止まっていないことを示す（位置は実際の件数のまま）。色はテーマの変数の半透明 */
                    #exportBar.is-working::after {
                        content: '';
                        position: absolute;
                        top: 0;
                        right: 0;
                        bottom: 0;
                        left: 0;
                        border-radius: 3px;
                        pointer-events: none;
                        opacity: 0.12;
                        background-image: linear-gradient(
                            45deg,
                            var(--color-surface, #fff) 25%, transparent 25%,
                            transparent 50%, var(--color-surface, #fff) 50%,
                            var(--color-surface, #fff) 75%, transparent 75%, transparent
                        );
                        background-size: 20px 20px;
                        animation: exportBarStripes 2s linear infinite;
                    }

                    @keyframes exportBarStripes {
                        from {
                            background-position: 0 0;
                        }
                        to {
                            background-position: 20px 0;
                        }
                    }

                    @media (prefers-reduced-motion: reduce) {
                        #exportBar.is-working::after {
                            animation: none;
                        }
                    }

                    #exportEta {
                        margin-left: 1em;
                    }

                    #exportProgressBox progress {
                        display: block;
                        width: 100%;
                        height: 1.5em;
                        /* 色はテーマの変数（assets/docs/theme-variables.md）。進んだ部分が primary、残りが薄い枠の色 */
                        appearance: none;
                        border: 1px solid var(--color-border-default, #ccc);
                        border-radius: 3px;
                        background: var(--color-border-light, #ededed);
                        color: var(--color-primary, #375665);
                        overflow: hidden;
                    }

                    #exportProgressBox progress::-webkit-progress-bar {
                        background: var(--color-border-light, #ededed);
                    }

                    #exportProgressBox progress::-webkit-progress-value {
                        background: var(--color-primary, #375665);
                    }

                    #exportProgressBox progress::-moz-progress-bar {
                        background: var(--color-primary, #375665);
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

                <ul class="actionButtons">
                    <li id="exportStartItem">
                        <a
                            href="#"
                            class="default"
                            id="exportStart"
                        ><?= lang('export_site_start') ?></a>
                    </li>
                </ul>
            </form>

            <h3 id="exportPrevious" hidden></h3>
            <div id="exportNotice" role="status" hidden></div>
            <ul class="actionButtons">
                <li id="exportResumeItem" hidden>
                    <a href="#" class="default" id="exportResume"><?= lang('export_site_resume') ?></a>
                </li>
                <li id="exportRestartItem" hidden>
                    <a href="#" id="exportRestart"><?= lang('export_site_restart') ?></a>
                </li>
            </ul>

            <div id="exportProgressBox" hidden>
                <div id="exportBar">
                    <progress id="exportProgress" value="0" max="1"></progress>
                </div>
                <p>
                    <span id="exportCount"></span><span id="exportEta" hidden></span>
                </p>
                <ul class="actionButtons">
                    <li id="exportCancelItem" hidden>
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
            startItem: $('exportStartItem'),
            resumeItem: $('exportResumeItem'),
            restartItem: $('exportRestartItem'),
            cancelItem: $('exportCancelItem'),
            start: $('exportStart'),
            previous: $('exportPrevious'),
            notice: $('exportNotice'),
            resume: $('exportResume'),
            restart: $('exportRestart'),
            box: $('exportProgressBox'),
            bar: $('exportBar'),
            progress: $('exportProgress'),
            count: $('exportCount'),
            eta: $('exportEta'),
            cancel: $('exportCancel'),
            messages: $('exportMessages'),
            result: $('exportResult')
        };
        let running = false;
        let starting = false;
        let cancelling = false;
        let buttonMode = 'start';
        // この画面が進めているジョブの id。step の応答が別の id を指したら、別の場所でやり直されたので止める
        let jobId = '';
        // シェルが別の画面へ差し替えたら、この画面のフォームは文書から外れる。残った関数が、新しい画面の処理と重ならないようにする
        // （モーダルの開け閉めでは外れないので、evoshell:unload ではなくこれで見る）
        const alive = function () {
            return form.isConnected;
        };
        // 処理中は documentDirty を立て、シェルが別の画面へ移る前に確認を出す
        const setDirty = function (dirty) {
            window.documentDirty = dirty;
        };

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
        // kind: 'success' / 'warning' で、完了の案内の見た目にする
        const setNotice = function (text, html, kind) {
            // 成功は他の画面（bkmanager など）と同じ okmsg、一部失敗は warning（テーマの既存のクラス）
            els.notice.className = kind === 'success' ? 'okmsg' : (kind === 'warning' ? 'warning' : '');
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
        // 完了を知らせる印を、ブラウザのタブの題名の先頭に付ける。外すと元に戻る
        let titleMark = '';
        const setTitleMark = function (mark) {
            if (titleMark && document.title.indexOf(titleMark) === 0) {
                document.title = document.title.slice(titleMark.length);
            }
            titleMark = mark || '';
            if (titleMark) {
                document.title = titleMark + document.title;
            }
        };
        // シェルが別の画面へ移るとき（題名を差し替える前）に戻す
        document.addEventListener('evoshell:unload', function () {
            setTitleMark('');
        }, {once: true});
        // 完了をダイアログで知らせる（管理画面の共通のダイアログ。メッセージは HTML なので、言語ファイルの文言と数だけを渡す）
        const notifyDialog = function (message, title, warning) {
            if (typeof window.jAlert === 'function') {
                window.jAlert(message, title, null, warning ? {icon: 'warning'} : undefined);
            }
        };
        // 処理中の縞と残り時間の目安は、処理が止まるところ（完了・中止・エラー・状態の取り直し）で必ず消す
        const setWorking = function (working) {
            els.bar.classList.toggle('is-working', working);
        };
        let etaSmoothed = null;
        const hideEta = function () {
            etaSmoothed = null;
            els.eta.hidden = true;
            els.eta.textContent = '';
        };
        const settle = function () {
            setWorking(false);
            hideEta();
        };
        // 残りの秒数を「約30秒」「約2分」のような文言にする（秒は 5 秒、分は切り上げ）
        const formatEta = function (sec) {
            if (sec < 60) {
                return fill(T.export_site_eta_seconds, {sec: Math.max(5, Math.round(sec / 5) * 5)});
            }
            const min = Math.ceil(sec / 60);
            if (min < 60) {
                return fill(T.export_site_eta_minutes, {min: min});
            }
            return fill(T.export_site_eta_hours, {hour: Math.floor(min / 60), min: min % 60});
        };
        // 処理した件数と、ジョブの処理にかかった時間（elapsed）の平均の速さから残りを推定し、移動平均でならして出す。
        // 始まってすぐ（5% 未満か 5 秒未満）は「計算中」
        const renderEta = function (res) {
            const total = Number(res.total) || 0;
            const done = Number(res.done) || 0;
            const elapsed = Number(res.elapsed) || 0;
            els.eta.hidden = false;
            if (total <= 0 || done / total < 0.05 || elapsed < 5 || done <= 0) {
                etaSmoothed = null;
                els.eta.textContent = T.export_site_eta_calculating;
                return;
            }
            const estimate = (total - done) * elapsed / done;
            etaSmoothed = etaSmoothed === null ? estimate : etaSmoothed * 0.7 + estimate * 0.3;
            els.eta.textContent = fill(T.export_site_eta, {time: formatEta(etaSmoothed)});
        };
        const setFormDisabled = function (disabled) {
            Array.prototype.forEach.call(form.elements, function (el) {
                el.disabled = disabled;
            });
        };
        // ボタンは状態ごとに 1 組だけ出す
        //   start: 開始 / running: 中止 / choice: 再開とやり直し / restart: やり直し / none: 出さない
        const setButtons = function (mode) {
            buttonMode = mode;
            els.startItem.hidden = mode !== 'start';
            els.cancelItem.hidden = mode !== 'running';
            els.resumeItem.hidden = mode !== 'choice';
            els.restartItem.hidden = mode !== 'choice' && mode !== 'restart';
        };
        // 応答が読めない・4xx のときは送り直さず止める
        const stopUnreadable = function () {
            settle();
            running = false;
            starting = false;
            cancelling = false;
            setDirty(false);
            setButtons('none');
            setNotice(T.export_site_unreadable);
        };

        // サーバーに送る。通信に失敗したら間をおいて数回やり直す
        // 戻り値の印:
        //   local: true      手元で作った失敗（通信の失敗が続いた・5xx・JSON でない 200 の応答）。サーバーのジョブは続いているかもしれない
        //   unreadable: true JSON でない 4xx（共通の入口の 405・CSRF の 403）。送り直しても同じなので止める
        //   どちらも無い     サーバーが返した JSON（権限なしの 403 の error も、その message を使う）
        const call = async function (op, extra) {
            const data = Object.assign({op: op}, extra || {});
            for (let attempt = 0; attempt < 5; attempt++) {
                if (!alive()) {
                    break;
                }
                let response;
                try {
                    response = await Evo.post(ENDPOINT, data);
                } catch (e) {
                    // 通信そのものの失敗だけ送り直す
                    setNotice(T.export_site_request_failed);
                    await sleep(2000);
                    continue;
                }
                let json = null;
                try {
                    json = await response.json();
                } catch (e) {
                    json = null;
                }
                if (json && typeof json === 'object' && json.state) {
                    return json;
                }
                if (response.status >= 400 && response.status < 500) {
                    return {state: 'error', unreadable: true};
                }
                return {state: 'error', local: true};
            }
            return {state: 'error', local: true};
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

        // ジョブが無い状態（開始ボタン）に戻す
        const showIdle = function () {
            settle();
            running = false;
            starting = false;
            cancelling = false;
            setDirty(false);
            els.box.hidden = true;
            els.result.hidden = true;
            els.previous.hidden = true;
            setNotice('');
            setTitleMark('');
            setButtons('start');
            setFormDisabled(false);
        };

        // 終わった（done / cancelled / error）。やり直せる状態に戻す
        const showFinished = function (res) {
            settle();
            running = false;
            starting = false;
            cancelling = false;
            setDirty(false);
            els.previous.hidden = true;
            renderProgress(res);
            renderMessages(res);
            setButtons(res.state === 'done' ? 'start' : 'restart');
            const at = res.finished_label ? fill(T.export_site_finished_at, {datetime: res.finished_label}) : '';
            const counts = {success: Number(res.success) || 0, failed: Number(res.failed) || 0};
            if (res.state === 'cancelled') {
                // 中止は利用者が押したので、案内だけ出す（ダイアログは出さない）
                setNotice(T.export_site_cancelled + at);
                setTitleMark('');
            } else if (res.state === 'error') {
                setNotice(res.message || T.export_site_request_failed, true);
                setTitleMark(T.export_site_title_stopped);
                notifyDialog(T.export_site_error_dialog, T.export_site_error_dialog_title, true);
            } else if (counts.failed > 0) {
                setNotice(T.export_site_done_with_failures + at, false, 'warning');
                setTitleMark(T.export_site_title_failed);
                notifyDialog(fill(T.export_site_done_dialog_with_failures, counts), T.export_site_done_dialog_title, true);
            } else {
                setNotice(T.export_site_done + at, false, 'success');
                setTitleMark(T.export_site_title_done);
                notifyDialog(fill(T.export_site_done_dialog, counts), T.export_site_done_dialog_title, false);
            }
            if (res.state !== 'error' || res.total) {
                renderResult(res);
            }
            setFormDisabled(false);
        };

        // サーバーに保存されたジョブの状態を画面に出す（開いたとき・通信が続けて失敗したあと）
        const showStatus = function (res) {
            settle();
            running = false;
            starting = false;
            cancelling = false;
            setDirty(false);
            setFormDisabled(false);
            if (res.state === 'none') {
                showIdle();
                return;
            }
            if (res.state === 'error' && res.total === undefined) {
                // ジョブの状態ではなく、サーバーが返した拒否など。その message をそのまま出す
                setButtons('none');
                setNotice(res.message || T.export_site_unreadable, true);
                return;
            }
            els.previous.hidden = true;
            renderProgress(res);
            renderMessages(res);
            if (res.state === 'running') {
                jobId = res.job_id || '';
                setNotice(T.export_site_resume_notice);
                setButtons('choice');
                return;
            }
            if (res.state === 'cancelled') {
                setNotice(T.export_site_cancelled_notice);
            } else if (res.state === 'error') {
                setNotice(T.export_site_error_notice + ' ' + (res.message || ''), true);
            } else {
                setNotice('');
            }
            // 開いたときに出すのは前回の結果。今の処理と取り違えないよう、見出しと完了日時を付け、進み具合の表示は出さない
            els.box.hidden = true;
            els.previous.textContent = fill(T.export_site_previous_title, {datetime: res.finished_label || ''});
            els.previous.hidden = false;
            els.result.hidden = true;
            renderResult(res);
            setButtons(res.state === 'done' ? 'start' : 'restart');
        };

        // 手元の失敗（通信が続けて失敗・5xx・JSON でない応答）のとき、サーバーのジョブの状態を取り直して出す。
        // 状態も取れなければ、案内（画面を開き直す）を出す
        const recover = async function (message) {
            settle();
            running = false;
            starting = false;
            cancelling = false;
            setDirty(false);
            setButtons('none');
            const res = await call('status');
            if (!alive()) {
                return;
            }
            if (res.unreadable || res.local) {
                stopUnreadable();
            } else {
                showStatus(res);
            }
            if (message) {
                const current = els.notice.hidden ? '' : els.notice.innerHTML;
                setNotice(message + (current ? '<br>' + current : ''), true);
            }
        };

        // call() の失敗の印を処理する。処理したら true（呼び出し側はそこで終える）
        const handleBroken = async function (res) {
            if (res.unreadable) {
                stopUnreadable();
                return true;
            }
            if (res.local) {
                await recover();
                return true;
            }
            return false;
        };

        // 続きを進めるを、終わるまで繰り返す
        const loop = async function () {
            if (running) {
                return;
            }
            running = true;
            setDirty(true);
            setFormDisabled(true);
            setButtons('running');
            els.result.hidden = true;
            setNotice(T.export_site_running);
            hideEta();
            while (running && alive()) {
                // 応答を待つあいだは縞を動かす
                setWorking(true);
                const res = await call(cancelling ? 'cancel' : 'step');
                setWorking(false);
                if (!running || !alive()) {
                    return;
                }
                if (await handleBroken(res)) {
                    return;
                }
                if (res.state === 'none') {
                    showIdle();
                    return;
                }
                if (!cancelling && res.job_id && jobId && res.job_id !== jobId) {
                    // 別の場所でジョブがやり直された。進め続けず、今の状態の表示に切り替える
                    showStatus(res);
                    setNotice(T.export_site_job_replaced + (els.notice.hidden ? '' : '<br>' + els.notice.innerHTML), true);
                    return;
                }
                if (res.state === 'running') {
                    renderProgress(res);
                    if (cancelling) {
                        hideEta();
                        await sleep(300);
                    } else {
                        renderEta(res);
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
            if (running || starting) {
                return;
            }
            // 応答を待つ間は、押せるボタンを出さない（二度押しを防ぐ）
            starting = true;
            setDirty(true);
            const previousMode = buttonMode;
            setButtons('none');
            setNotice('');
            setTitleMark('');
            els.messages.textContent = '';
            els.result.hidden = true;
            els.previous.hidden = true;
            const res = await call('start', formData());
            if (!alive()) {
                return;
            }
            if (await handleBroken(res)) {
                return;
            }
            starting = false;
            if (res.state === 'busy') {
                setDirty(false);
                setButtons(previousMode);
                setNotice(T.export_site_busy);
                return;
            }
            if (res.state === 'error') {
                // 前の状態へは戻さない（捨てたジョブの再開を出さない）。message に続けて、サーバーの実際の状態を出す
                await recover(res.message || T.export_site_request_failed);
                return;
            }
            jobId = res.job_id || '';
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
            if (!alive()) {
                return;
            }
            if (await handleBroken(res)) {
                return;
            }
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
        // 応答が届くまでボタンは出さない（先に押された開始と食い違わせない）
        setButtons('none');
        call('status').then(function (res) {
            // 応答を待つ間に開始されていたら、その状態を戻さない
            if (!alive() || running || starting) {
                return;
            }
            if (res.unreadable || res.local) {
                stopUnreadable();
                return;
            }
            showStatus(res);
        });
    })();
</script>
