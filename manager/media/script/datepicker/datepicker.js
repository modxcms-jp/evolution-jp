// 管理画面の日付選び。class="DatePicker" の入力欄にカレンダーを出す。
// カレンダーは入力欄の直後に作り、閉じたら取り除く(body直下には何も作らない)
(function (window, document) {
    'use strict';

    // シェル画面では断片を読み込むたびにこのファイルが実行され直すため、登録は一度だけ
    if (window.EvoDatePicker) {
        return;
    }

    const LANG = {
        ja: {
            days: ['月', '火', '水', '木', '金', '土', '日'],
            title: function (y, m) {
                return y + '年' + (m + 1) + '月';
            },
            prevYear: '前の年',
            prevMonth: '前の月',
            nextMonth: '次の月',
            nextYear: '次の年',
            time: '時刻',
            today: '今日',
            clear: 'クリア'
        },
        en: {
            days: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
            title: function (y, m) {
                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                    'August', 'September', 'October', 'November', 'December'];
                return months[m] + ' ' + y;
            },
            prevYear: 'Previous year',
            prevMonth: 'Previous month',
            nextMonth: 'Next month',
            nextYear: 'Next year',
            time: 'Time',
            today: 'Today',
            clear: 'Clear'
        }
    };

    const config = {format: 'YYYY/mm/dd', lang: 'ja'};
    let input = null;
    let popup = null;
    let view = null;
    let selected = null;
    let time = {h: 0, m: 0};

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function text() {
        return LANG[config.lang] || LANG.en;
    }

    function hasTime(el) {
        return el.getAttribute('data-timepicker') !== 'false';
    }

    function isPickerInput(el) {
        return el instanceof HTMLInputElement && el.classList.contains('DatePicker')
            && !el.disabled && !el.readOnly;
    }

    // datetime_format(YYYY/mm/dd・dd-mm-YYYY・mm/dd/YYYY)に時刻 HH:MM を足した形で書き出す
    function format(d, withTime) {
        const date = config.format.replace(/YYYY|mm|dd/g, function (token) {
            if (token === 'YYYY') {
                return String(d.getFullYear());
            }
            return pad(token === 'mm' ? d.getMonth() + 1 : d.getDate());
        });
        return withTime ? date + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : date;
    }

    // 書き出しと同じ形を読む。秒は付いていても無視する
    function parse(value) {
        const order = [];
        const pattern = config.format
            .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
            .replace(/YYYY|mm|dd/g, function (token) {
                order.push(token);
                return token === 'YYYY' ? '(\\d{4})' : '(\\d{1,2})';
            });
        const match = new RegExp('^\\s*' + pattern + '(?:\\s+(\\d{1,2}):(\\d{1,2}))?').exec(value);
        if (!match) {
            return null;
        }
        const p = {};
        order.forEach(function (token, i) {
            p[token] = parseInt(match[i + 1], 10);
        });
        const d = new Date(p.YYYY, p.mm - 1, p.dd, match[4] ? +match[4] : 0, match[5] ? +match[5] : 0);
        return d.getMonth() === p.mm - 1 && d.getDate() === p.dd ? d : null;
    }

    function write(value) {
        input.value = value;
        window.documentDirty = true;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
    }

    // 刻みに合わない今の値(手で打った 7 分など)も選択肢に残す
    function options(max, step, current) {
        const values = [];
        for (let i = 0; i < max; i += step) {
            values.push(i);
        }
        if (current % step) {
            values.push(current);
            values.sort(function (a, b) {
                return a - b;
            });
        }
        return values.map(function (i) {
            return '<option value="' + i + '"' + (i === current ? ' selected' : '') + '>' + pad(i) + '</option>';
        }).join('');
    }

    function render() {
        const t = text();
        const y = view.getFullYear();
        const m = view.getMonth();
        const today = new Date();
        const nav = function (step, label, mark) {
            return '<button type="button" data-nav="' + step + '" title="' + label + '">' + mark + '</button>';
        };
        let html = '<div class="evo-datepicker-head">' + nav(-12, t.prevYear, '&laquo;') + nav(-1, t.prevMonth, '&lsaquo;')
            + '<span class="evo-datepicker-title">' + t.title(y, m) + '</span>'
            + nav(1, t.nextMonth, '&rsaquo;') + nav(12, t.nextYear, '&raquo;') + '</div><div class="evo-datepicker-grid">';
        t.days.forEach(function (day) {
            html += '<span>' + day + '</span>';
        });
        // 月曜始まり。6 週分を出して高さを一定にする
        const start = new Date(y, m, 1 - (view.getDay() + 6) % 7);
        for (let i = 0; i < 42; i++) {
            const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            const classes = ['is-w' + d.getDay()];
            if (d.getMonth() !== m) {
                classes.push('is-other');
            }
            if (d.toDateString() === today.toDateString()) {
                classes.push('is-today');
            }
            if (selected && d.toDateString() === selected.toDateString()) {
                classes.push('is-selected');
            }
            html += '<button type="button" class="' + classes.join(' ') + '" data-date="'
                + d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate() + '">' + d.getDate() + '</button>';
        }
        html += '</div><div class="evo-datepicker-foot">';
        if (hasTime(input)) {
            html += '<span class="evo-datepicker-time">' + t.time + ' <select data-time="h">' + options(24, 1, time.h)
                + '</select>:<select data-time="m">' + options(60, 5, time.m) + '</select></span>';
        }
        html += '<button type="button" data-action="today">' + t.today + '</button>'
            + '<button type="button" data-action="clear">' + t.clear + '</button></div>';
        popup.innerHTML = html;
    }

    // 入力欄の下(入らなければ上)に出す。位置の基準になる祖先は場所によって違うため、
    // 左上に置いたときの実際の位置との差で決める
    function place() {
        popup.style.left = '0px';
        popup.style.top = '0px';
        const origin = popup.getBoundingClientRect();
        const rect = input.getBoundingClientRect();
        let top = rect.bottom + 2;
        if (top + popup.offsetHeight > window.innerHeight && rect.top - popup.offsetHeight - 2 > 0) {
            top = rect.top - popup.offsetHeight - 2;
        }
        const left = Math.max(4, Math.min(rect.left, window.innerWidth - popup.offsetWidth - 4));
        popup.style.left = (left - origin.left) + 'px';
        popup.style.top = (top - origin.top) + 'px';
    }

    function showMonth(d) {
        view = new Date(d.getFullYear(), d.getMonth(), 1);
    }

    function select(d) {
        selected = new Date(d.getFullYear(), d.getMonth(), d.getDate(), time.h, time.m);
        write(format(selected, hasTime(input)));
        if (!hasTime(input)) {
            close();
            return;
        }
        showMonth(selected);
        render();
    }

    function onClick(e) {
        const btn = e.target.closest('button');
        if (!btn) {
            return;
        }
        if (btn.dataset.nav) {
            view.setMonth(view.getMonth() + Number(btn.dataset.nav));
            render();
        } else if (btn.dataset.date) {
            const p = btn.dataset.date.split('-').map(Number);
            select(new Date(p[0], p[1], p[2]));
        } else if (btn.dataset.action === 'today') {
            const now = new Date();
            time = {h: now.getHours(), m: now.getMinutes() - now.getMinutes() % 5};
            select(now);
        } else if (btn.dataset.action === 'clear') {
            write('');
            close();
        }
    }

    function onTimeChange(e) {
        // 時刻の欄の change は入力欄の外へ伝えない(入力欄の change だけを出す)
        e.stopPropagation();
        if (e.type !== 'change' || !e.target.dataset.time) {
            return;
        }
        time[e.target.dataset.time] = Number(e.target.value);
        if (selected) {
            selected.setHours(time.h, time.m);
            write(format(selected, true));
        }
    }

    function sync() {
        selected = parse(input.value);
        time = selected ? {h: selected.getHours(), m: selected.getMinutes()} : {h: 0, m: 0};
        showMonth(selected || new Date());
    }

    function open(el) {
        if (input === el) {
            return;
        }
        close();
        input = el;
        input.setAttribute('autocomplete', 'off');
        sync();
        popup = document.createElement('div');
        popup.className = 'evo-datepicker';
        // 時刻の欄以外を押しても入力欄からフォーカスを外さない
        popup.addEventListener('mousedown', function (e) {
            if (e.target.tagName !== 'SELECT' && e.target.tagName !== 'OPTION') {
                e.preventDefault();
            }
        });
        popup.addEventListener('click', onClick);
        popup.addEventListener('change', onTimeChange);
        popup.addEventListener('input', onTimeChange);
        input.insertAdjacentElement('afterend', popup);
        render();
        place();
    }

    function close() {
        if (popup) {
            popup.remove();
        }
        popup = null;
        input = null;
    }

    function onPointer(e) {
        if (isPickerInput(e.target)) {
            open(e.target);
        } else if (popup && !popup.contains(e.target)) {
            close();
        }
    }

    document.addEventListener('focusin', onPointer);
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('keydown', function (e) {
        if (popup && e.key === 'Escape') {
            close();
        }
    });
    // 手で打ち直したときは、読める値になった時点でカレンダーを合わせる
    document.addEventListener('input', function (e) {
        if (popup && e.target === input && e.isTrusted && parse(input.value)) {
            sync();
            render();
        }
    });
    window.addEventListener('resize', function () {
        if (popup) {
            place();
        }
    });

    window.EvoDatePicker = {
        setup: function (options) {
            Object.assign(config, options);
            document.querySelectorAll('input.DatePicker').forEach(function (el) {
                el.setAttribute('autocomplete', 'off');
            });
        }
    };
})(window, document);
