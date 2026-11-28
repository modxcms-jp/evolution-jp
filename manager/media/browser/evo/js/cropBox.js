// トリミングの枠の計算(画面に触れない純粋な関数だけ)。座標はすべて「回転後の画像の画素」。
// bounds は画像の大きさ { w, h }、ratio は 幅 / 高さ(null は自由)、min は枠の幅・高さの最小(画素)。
// 操作の途中は小数のまま計算し、最後に normalizeRect() で整数の画素にそろえて画像の中へ収める。

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

/** 画像全体の範囲 */
export function wholeRect(bounds) {
    return { x: 0, y: 0, w: bounds.w, h: bounds.h };
}

/** 範囲の中に収まる、指定の比率でいちばん大きな範囲(中心をそろえる) */
export function fitRatioInside(rect, ratio) {
    var w = rect.w;
    var h = rect.h;
    if (w / h > ratio) {
        w = h * ratio;
    } else {
        h = w / ratio;
    }
    return { x: rect.x + (rect.w - w) / 2, y: rect.y + (rect.h - h) / 2, w: w, h: h };
}

/** 範囲がその比率と言えるか。整数の画素に丸めた差(どちらかの辺で 1 画素まで)は同じとみなす */
export function matchesRatio(rect, ratio) {
    return Math.abs(rect.h - rect.w / ratio) <= 1 || Math.abs(rect.w - rect.h * ratio) <= 1;
}

/** 中心を保って幅と高さを入れ替える。画像からはみ出すときは比率を保って縮め、位置を中へ寄せる */
export function swapOrientation(rect, bounds) {
    var w = rect.h;
    var h = rect.w;
    var scale = Math.min(1, bounds.w / w, bounds.h / h);
    w *= scale;
    h *= scale;
    var cx = rect.x + rect.w / 2;
    var cy = rect.y + rect.h / 2;
    return {
        x: clamp(cx - w / 2, 0, bounds.w - w),
        y: clamp(cy - h / 2, 0, bounds.h - h),
        w: w,
        h: h
    };
}

/** 大きさを保ったまま動かす(画像の外へは出さない) */
export function moveRect(rect, dx, dy, bounds) {
    return {
        x: clamp(rect.x + dx, 0, bounds.w - rect.w),
        y: clamp(rect.y + dy, 0, bounds.h - rect.h),
        w: rect.w,
        h: rect.h
    };
}

/**
 * 固定した点(anchor)と動かす点(point)で範囲を決める。四隅のつまみと、枠の外からの描き直しで使う。
 * 動かす点が固定した点を越えたときは、向きを反転する
 */
export function resizeFromAnchor(anchor, point, ratio, bounds, min) {
    var sx = point.x >= anchor.x ? 1 : -1;
    var sy = point.y >= anchor.y ? 1 : -1;
    var maxW = sx > 0 ? bounds.w - anchor.x : anchor.x;
    var maxH = sy > 0 ? bounds.h - anchor.y : anchor.y;
    // 固定した点が画像の端にあって、外側へ広げられないときは内側へ向ける
    if (maxW < min) {
        sx = -sx;
        maxW = sx > 0 ? bounds.w - anchor.x : anchor.x;
    }
    if (maxH < min) {
        sy = -sy;
        maxH = sy > 0 ? bounds.h - anchor.y : anchor.y;
    }
    var w = Math.abs(point.x - anchor.x);
    var h = Math.abs(point.y - anchor.y);
    if (ratio) {
        // 比率に合わせるときは、大きく動かした側に合わせる
        w = Math.max(w, h * ratio);
        var limit = Math.min(maxW, maxH * ratio);
        w = clamp(w, Math.max(min, min * ratio), limit);
        h = w / ratio;
    } else {
        w = clamp(w, min, maxW);
        h = clamp(h, min, maxH);
    }
    return {
        x: sx > 0 ? anchor.x : anchor.x - w,
        y: sy > 0 ? anchor.y : anchor.y - h,
        w: w,
        h: h
    };
}

/** 四隅のつまみ('nw'・'ne'・'sw'・'se')を dx・dy だけ動かす。向かいの角を固定する */
export function resizeCorner(rect, corner, dx, dy, ratio, bounds, min) {
    var west = corner.indexOf('w') !== -1;
    var north = corner.indexOf('n') !== -1;
    var anchor = { x: west ? rect.x + rect.w : rect.x, y: north ? rect.y + rect.h : rect.y };
    var point = { x: (west ? rect.x : rect.x + rect.w) + dx, y: (north ? rect.y : rect.y + rect.h) + dy };
    return resizeFromAnchor(anchor, point, ratio, bounds, min);
}

/**
 * 各辺の中央のつまみ('n'・'e'・'s'・'w')を動かす。向かいの辺を固定する。
 * 比率を保つときは、もう一方の向きを中心から広げる(画像の端に当たるときは中へ寄せる)
 */
export function resizeEdge(rect, edge, dx, dy, ratio, bounds, min) {
    var x = rect.x;
    var y = rect.y;
    var w = rect.w;
    var h = rect.h;
    if (edge === 'e' || edge === 'w') {
        var right = rect.x + rect.w;
        var maxW = edge === 'e' ? bounds.w - rect.x : right;
        w = edge === 'e' ? rect.w + dx : rect.w - dx;
        if (ratio) {
            w = clamp(w, Math.max(min, min * ratio), Math.min(maxW, bounds.h * ratio));
            h = w / ratio;
            y = clamp(rect.y + rect.h / 2 - h / 2, 0, bounds.h - h);
        } else {
            w = clamp(w, min, maxW);
        }
        x = edge === 'e' ? rect.x : right - w;
    } else {
        var bottom = rect.y + rect.h;
        var maxH = edge === 's' ? bounds.h - rect.y : bottom;
        h = edge === 's' ? rect.h + dy : rect.h - dy;
        if (ratio) {
            h = clamp(h, Math.max(min, min / ratio), Math.min(maxH, bounds.w / ratio));
            w = h * ratio;
            x = clamp(rect.x + rect.w / 2 - w / 2, 0, bounds.w - w);
        } else {
            h = clamp(h, min, maxH);
        }
        y = edge === 's' ? rect.y : bottom - h;
    }
    return { x: x, y: y, w: w, h: h };
}

/**
 * 整数の画素にそろえ、画像の中へ収める。比率を保たないときは、幅ではなく辺の位置を丸める
 * (左上のつまみを動かしたときに、固定している右下の辺が 1 画素ずれないように)。比率を保つときは、幅から高さを決め直す
 */
export function normalizeRect(rect, ratio, bounds) {
    if (!ratio) {
        var left = clamp(Math.round(rect.x), 0, bounds.w - 1);
        var top = clamp(Math.round(rect.y), 0, bounds.h - 1);
        var right = clamp(Math.round(rect.x + rect.w), left + 1, bounds.w);
        var bottom = clamp(Math.round(rect.y + rect.h), top + 1, bounds.h);
        return { x: left, y: top, w: right - left, h: bottom - top };
    }
    var w = clamp(Math.round(rect.w), 1, bounds.w);
    var h = Math.round(w / ratio);
    if (h > bounds.h) {
        h = bounds.h;
        w = clamp(Math.round(h * ratio), 1, bounds.w);
    }
    h = Math.max(1, h);
    return {
        x: clamp(Math.round(rect.x), 0, bounds.w - w),
        y: clamp(Math.round(rect.y), 0, bounds.h - h),
        w: w,
        h: h
    };
}

/**
 * 画像を 90° 回したときに、同じ部分を指すように範囲を写す。
 * direction は 1(右回り)か -1(左回り)。bounds は回す前の画像の大きさ
 */
export function rotateRect(rect, direction, bounds) {
    if (direction === 1) {
        return { x: bounds.h - (rect.y + rect.h), y: rect.x, w: rect.h, h: rect.w };
    }
    return { x: rect.y, y: bounds.w - (rect.x + rect.w), w: rect.h, h: rect.w };
}
