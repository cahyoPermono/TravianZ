<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       res.tpl                                                     ##
##  Developed by:  Dzoki                                                       ##
##  Refactored by: Shadow Incremental Refactor                                 ##
##  Enhanced with: Real-Time SDA Full Time Indicator & Storage Analysis         ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
#################################################################################

if (!empty($village)) {

    $wood = round($village->getProd("wood"));
    $clay = round($village->getProd("clay"));
    $iron = round($village->getProd("iron"));
    $crop = round($village->getProd("crop"));

    $totalproduction = $village->allcrop;

    $woodStore = round($village->awood);
    $clayStore = round($village->aclay);
    $ironStore = round($village->airon);
    $cropStore = round($village->acrop);

    $maxStore  = $village->maxstore;
    $maxCrop   = $village->maxcrop;

    // Helper: Hitung estimasi waktu menuju penuh / habis
    $calcSdaFill = function($current, $max, $prod) {
        $cur = max(0, (float)$current);
        $m = max(1, (float)$max);
        $pct = min(100, round(($cur / $m) * 100, 1));

        if ($cur >= $m) {
            return [
                'status'    => 'full',
                'sec'       => 0,
                'short'     => '⚠️ PENUH!',
                'full_date' => 'Sudah Penuh (Hangus!)',
                'pct'       => 100,
                'class'     => 'tz-res-full'
            ];
        }

        if ($prod <= 0) {
            if ($prod == 0) {
                return [
                    'status'    => 'idle',
                    'sec'       => -1,
                    'short'     => '0/jam',
                    'full_date' => 'Produksi 0 (Tidak Bertambah)',
                    'pct'       => $pct,
                    'class'     => 'tz-res-idle'
                ];
            } else {
                $starveSec = max(0, round(($cur / abs($prod)) * 3600));
                $d = floor($starveSec / 86400);
                $h = floor(($starveSec % 86400) / 3600);
                $min = floor(($starveSec % 3600) / 60);
                $short = ($d > 0 ? "{$d}h {$h}j" : sprintf("%02d:%02d", $h, $min));
                return [
                    'status'    => 'starve',
                    'sec'       => $starveSec,
                    'short'     => "⚠️ Habis {$short}",
                    'full_date' => 'Defisit! Pasukan kelaparan pada ' . date('d M H:i', time() + $starveSec),
                    'pct'       => $pct,
                    'class'     => 'tz-res-starve'
                ];
            }
        }

        $needed = $m - $cur;
        $sec = max(0, round(($needed / $prod) * 3600));
        $d = floor($sec / 86400);
        $h = floor(($sec % 86400) / 3600);
        $min = floor(($sec % 3600) / 60);

        if ($d > 0) {
            $short = "{$d}h {$h}j";
        } elseif ($h > 0) {
            $short = sprintf("%dj %02dm", $h, $min);
        } else {
            $s = $sec % 60;
            $short = sprintf("%02d:%02d", $min, $s);
        }

        $warnClass = ($pct >= 90) ? 'tz-res-warn' : 'tz-res-normal';

        return [
            'status'    => 'filling',
            'sec'       => $sec,
            'short'     => "⏳ {$short}",
            'full_date' => date('d M H:i', time() + $sec),
            'pct'       => $pct,
            'class'     => $warnClass
        ];
    };

    $infoWood = $calcSdaFill($woodStore, $maxStore, $wood);
    $infoClay = $calcSdaFill($clayStore, $maxStore, $clay);
    $infoIron = $calcSdaFill($ironStore, $maxStore, $iron);
    $infoCrop = $calcSdaFill($cropStore, $maxCrop, $crop);

    $upkeepTotal = (isset($technology) && isset($village->unitall)) ? $technology->getUpkeep($village->unitall, 0) : 0;
    $cropUsed = $village->pop + $upkeepTotal;
    $pctPop = ($totalproduction > 0) ? min(100, round(($cropUsed / $totalproduction) * 100, 1)) : 0;
    $freeCrop = max(0, $totalproduction - $cropUsed);

    // Cari sumber daya yang paling cepat penuh
    $resList = [
        ['name' => LUMBER, 'key' => 'Kayu', 'sec' => $infoWood['sec'], 'status' => $infoWood['status'], 'date' => $infoWood['full_date'], 'pct' => $infoWood['pct'], 'short' => $infoWood['short']],
        ['name' => CLAY, 'key' => 'Tanah Liat', 'sec' => $infoClay['sec'], 'status' => $infoClay['status'], 'date' => $infoClay['full_date'], 'pct' => $infoClay['pct'], 'short' => $infoClay['short']],
        ['name' => IRON, 'key' => 'Besi', 'sec' => $infoIron['sec'], 'status' => $infoIron['status'], 'date' => $infoIron['full_date'], 'pct' => $infoIron['pct'], 'short' => $infoIron['short']],
        ['name' => CROP, 'key' => 'Gandum', 'sec' => $infoCrop['sec'], 'status' => $infoCrop['status'], 'date' => $infoCrop['full_date'], 'pct' => $infoCrop['pct'], 'short' => $infoCrop['short']],
    ];

    $hasFull = false;
    $fastest = null;
    $minSec = PHP_INT_MAX;

    foreach ($resList as $r) {
        if ($r['status'] === 'full') {
            $hasFull = true;
        } elseif ($r['status'] === 'filling' && $r['sec'] < $minSec) {
            $minSec = $r['sec'];
            $fastest = $r;
        }
    }
?>

<style type="text/css">
/* ==================== SDA TIME INDICATOR STYLING ==================== */
#res {
    height: auto !important;
    overflow: visible !important;
}
#resWrap {
    overflow: visible !important;
}

/* Base timer badge styles */
.tz-res-timer {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-weight: 700;
    white-space: nowrap;
    text-shadow: none;
    cursor: help;
}
.tz-res-timer.tz-res-normal {
    color: #1e5e34;
    background: #e8f5e9;
    border: 1px solid #c8e6c9;
}
.tz-res-timer.tz-res-warn {
    color: #b75304;
    background: #fff3e0;
    border: 1px solid #ffe0b2;
    font-weight: 800;
}
.tz-res-timer.tz-res-full {
    color: #ffffff !important;
    background: #d32f2f !important;
    border: 1px solid #b71c1c !important;
    font-weight: 800;
    animation: tzResBlink 1.2s infinite;
}
.tz-res-timer.tz-res-starve {
    color: #ffffff !important;
    background: #c2185b !important;
    border: 1px solid #880e4f !important;
    font-weight: 800;
    animation: tzResBlink 1s infinite;
}
.tz-res-timer.tz-res-idle {
    color: #666;
    background: #eee;
    border: 1px solid #ddd;
}
.tz-res-timer.tz-res-free {
    color: #2e602f;
    background: #f1f8e9;
    border: 1px solid #dcedc8;
}

/* Micro progress bar */
.tz-res-bar-fill {
    height: 100%;
    transition: width 0.6s ease;
}
.tz-res-bar-fill.tz-safe { background: #27ae60; }
.tz-res-bar-fill.tz-warning { background: #f39c12; }
.tz-res-bar-fill.tz-critical { background: #e74c3c; }
.tz-res-bar-cropuse { background: #795548; }

.tz-text-danger { color: #c0392b; font-weight: bold; }

@keyframes tzResBlink {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.03); }
}

/* ==================== DESKTOP (WEB) STYLING ==================== */
@media screen and (min-width: 768px) {
    div#res {
        height: 24px !important;
        z-index: 10 !important;
    }
    div#res table {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        margin: 0 auto !important;
        height: 22px !important;
    }
    div#res table td.res-cell {
        position: relative !important;
        padding: 1px 5px 3px 5px !important;
        height: 22px !important;
        vertical-align: middle !important;
        background: transparent !important;
        border: none !important;
        white-space: nowrap !important;
    }
    .tz-res-box {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 3px !important;
        min-width: 0 !important;
        padding: 0 !important;
        white-space: nowrap !important;
    }
    .tz-res-row {
        display: inline-flex !important;
        align-items: center !important;
        gap: 3px !important;
        width: auto !important;
        white-space: nowrap !important;
    }
    .tz-res-row span {
        font-weight: 700 !important;
        font-size: 11px !important;
        color: #2b1d0c !important;
        text-shadow: 0 1px 1px rgba(255, 255, 255, 0.7) !important;
    }
    .tz-res-sub {
        display: inline-flex !important;
        align-items: center !important;
        width: auto !important;
        margin: 0 0 0 2px !important;
        white-space: nowrap !important;
    }
    .tz-res-timer {
        font-size: 9px !important;
        padding: 0 3px !important;
        height: 14px !important;
        line-height: 14px !important;
        border-radius: 3px !important;
        white-space: nowrap !important;
    }
    /* Thin 2px progress line at bottom of each desktop cell */
    .tz-res-bar {
        position: absolute !important;
        bottom: 0 !important;
        left: 4px !important;
        right: 4px !important;
        height: 2px !important;
        background: rgba(0, 0, 0, 0.1) !important;
        border-radius: 1px !important;
        overflow: hidden !important;
        margin: 0 !important;
    }
    /* Sembunyikan quick alert strip melayang di desktop agar TIDAK MENABRAK JUDUL DESA */
    .tz-res-quick-alert {
        display: none !important;
    }
    /* Pastikan halaman hero.php dan halaman tanpa top padding tidak tertutup oleh bar SDA */
    div.hero,
    div#content.hero {
        padding-top: 48px !important;
        padding-left: 25px !important;
        padding-right: 25px !important;
        width: 502px !important;
        box-sizing: border-box !important;
    }
}

/* ==================== MOBILE STYLING (PRESERVED) ==================== */
@media screen and (max-width: 767px) {
    div#res table {
        border-collapse: separate !important;
        border-spacing: 2px !important;
        background: transparent !important;
    }
    div#res table td.res-cell {
        height: auto !important;
        min-height: 36px !important;
        padding: 2px 1px !important;
    }
    .tz-res-box {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 0 !important;
        width: 100% !important;
        padding: 0 !important;
    }
    .tz-res-row {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 2px !important;
        width: 100% !important;
    }
    .tz-res-row span {
        font-size: 8.5px !important;
        letter-spacing: -0.6px !important;
    }
    .tz-res-sub {
        margin-top: 1px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
    }
    .tz-res-timer {
        font-size: 8px !important;
        padding: 0 2px !important;
    }
    .tz-res-bar {
        width: 100% !important;
        height: 3px !important;
        background: rgba(0, 0, 0, 0.12) !important;
        border-radius: 2px !important;
        overflow: hidden !important;
        margin-top: 2px !important;
    }
    .tz-res-quick-alert {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 5px !important;
        margin: 2px auto 0 !important;
        max-width: 100% !important;
        background: rgba(255, 255, 255, 0.95) !important;
        border: 1px solid #d6c6a8 !important;
        border-radius: 4px !important;
        padding: 2px 4px !important;
        font-size: 9px !important;
        color: #3b2a1a !important;
        box-shadow: 0 1px 4px rgba(0,0,0,0.12) !important;
    }
}
</style>

<div id="res">
<div id="resWrap">

    <!-- ================= RESOURCES ================= -->
    <table cellpadding="1" cellspacing="1">
        <tr>
            <!-- Wood -->
            <td class="res-cell res-wood" title="<?php echo LUMBER; ?>: <?php echo $wood; ?>/h | Penuh: <?php echo $infoWood['full_date']; ?> (<?php echo $infoWood['pct']; ?>%)">
                <div class="tz-res-box">
                    <div class="tz-res-row">
                        <img src="img/x.gif" class="r1" alt="<?php echo LUMBER; ?>" title="<?php echo LUMBER; ?>" />
                        <span id="l4" title="<?php echo $wood; ?>"><?php echo $woodStore . "/" . $maxStore; ?></span>
                    </div>
                    <div class="tz-res-sub">
                        <span class="tz-res-timer <?php echo $infoWood['class']; ?>" id="rt4" title="Estimasi penuh: <?php echo $infoWood['full_date']; ?>"><?php echo $infoWood['short']; ?></span>
                    </div>
                    <div class="tz-res-bar">
                        <div class="tz-res-bar-fill <?php echo ($infoWood['pct']>=95?'tz-critical':($infoWood['pct']>=80?'tz-warning':'tz-safe')); ?>" id="rb4" style="width: <?php echo $infoWood['pct']; ?>%;"></div>
                    </div>
                </div>
            </td>

            <!-- Clay -->
            <td class="res-cell res-clay" title="<?php echo CLAY; ?>: <?php echo $clay; ?>/h | Penuh: <?php echo $infoClay['full_date']; ?> (<?php echo $infoClay['pct']; ?>%)">
                <div class="tz-res-box">
                    <div class="tz-res-row">
                        <img src="img/x.gif" class="r2" alt="<?php echo CLAY; ?>" title="<?php echo CLAY; ?>" />
                        <span id="l3" title="<?php echo $clay; ?>"><?php echo $clayStore . "/" . $maxStore; ?></span>
                    </div>
                    <div class="tz-res-sub">
                        <span class="tz-res-timer <?php echo $infoClay['class']; ?>" id="rt3" title="Estimasi penuh: <?php echo $infoClay['full_date']; ?>"><?php echo $infoClay['short']; ?></span>
                    </div>
                    <div class="tz-res-bar">
                        <div class="tz-res-bar-fill <?php echo ($infoClay['pct']>=95?'tz-critical':($infoClay['pct']>=80?'tz-warning':'tz-safe')); ?>" id="rb3" style="width: <?php echo $infoClay['pct']; ?>%;"></div>
                    </div>
                </div>
            </td>

            <!-- Iron -->
            <td class="res-cell res-iron" title="<?php echo IRON; ?>: <?php echo $iron; ?>/h | Penuh: <?php echo $infoIron['full_date']; ?> (<?php echo $infoIron['pct']; ?>%)">
                <div class="tz-res-box">
                    <div class="tz-res-row">
                        <img src="img/x.gif" class="r3" alt="<?php echo IRON; ?>" title="<?php echo IRON; ?>" />
                        <span id="l2" title="<?php echo $iron; ?>"><?php echo $ironStore . "/" . $maxStore; ?></span>
                    </div>
                    <div class="tz-res-sub">
                        <span class="tz-res-timer <?php echo $infoIron['class']; ?>" id="rt2" title="Estimasi penuh: <?php echo $infoIron['full_date']; ?>"><?php echo $infoIron['short']; ?></span>
                    </div>
                    <div class="tz-res-bar">
                        <div class="tz-res-bar-fill <?php echo ($infoIron['pct']>=95?'tz-critical':($infoIron['pct']>=80?'tz-warning':'tz-safe')); ?>" id="rb2" style="width: <?php echo $infoIron['pct']; ?>%;"></div>
                    </div>
                </div>
            </td>

            <!-- Crop -->
            <td class="res-cell res-crop" title="<?php echo CROP; ?>: <?php echo $crop; ?>/h | Penuh: <?php echo $infoCrop['full_date']; ?> (<?php echo $infoCrop['pct']; ?>%)">
                <div class="tz-res-box">
                    <div class="tz-res-row">
                        <img src="img/x.gif" class="r4" alt="<?php echo CROP; ?>" title="<?php echo CROP; ?>" />
                        <span id="l1" title="<?php echo $crop; ?>"><?php echo ($village->acrop > 0 ? $cropStore : '0') . "/" . $maxCrop; ?></span>
                    </div>
                    <div class="tz-res-sub">
                        <span class="tz-res-timer <?php echo $infoCrop['class']; ?>" id="rt1" title="Estimasi penuh: <?php echo $infoCrop['full_date']; ?>"><?php echo $infoCrop['short']; ?></span>
                    </div>
                    <div class="tz-res-bar">
                        <div class="tz-res-bar-fill <?php echo ($infoCrop['pct']>=95?'tz-critical':($infoCrop['pct']>=80?'tz-warning':'tz-safe')); ?>" id="rb1" style="width: <?php echo $infoCrop['pct']; ?>%;"></div>
                    </div>
                </div>
            </td>

            <!-- Crop consumption -->
            <td class="res-cell res-pop" title="<?php echo CROP_COM; ?>: <?php echo $cropUsed . "/" . $totalproduction; ?> (Sisa bebas: <?php echo $freeCrop; ?>)">
                <div class="tz-res-box">
                    <div class="tz-res-row">
                        <img src="img/x.gif" class="r5" alt="<?php echo CROP_COM; ?>" title="<?php echo CROP_COM; ?>" />
                        <span><?php echo $cropUsed . "/" . $totalproduction; ?></span>
                    </div>
                    <div class="tz-res-sub">
                        <span class="tz-res-timer tz-res-free" id="rt5" title="Sisa gandum bebas untuk ekspansi bangunan/pasukan">🌾 +<?php echo $freeCrop; ?></span>
                    </div>
                    <div class="tz-res-bar">
                        <div class="tz-res-bar-fill tz-res-bar-cropuse" style="width: <?php echo $pctPop; ?>%;"></div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Quick Alert Summary -->
    <div class="tz-res-quick-alert" id="tzResQuickAlert">
        <span id="tzQuickAlertIcon">⚡</span>
        <span id="tzQuickAlertText">
            <?php if ($hasFull): ?>
                <span class="tz-text-danger">🚨 PERINGATAN:</span> Ada sumber daya yang sudah <b class="tz-text-danger">PENUH (100%)</b>! Hasil panen sedang hangus.
            <?php elseif ($fastest): ?>
                SDA Tercepat Penuh: <b><?php echo $fastest['key']; ?></b> ⏳ <b><span id="tzQuickTimer"><?php echo $fastest['short']; ?></span></b> (<span id="tzQuickClock"><?php echo $fastest['date']; ?></span>)
            <?php else: ?>
                Status Gudang &amp; Lumbung Aman
            <?php endif; ?>
        </span>
    </div>

</div>
</div>

<script type="text/javascript">
(function() {
    var tzProdConfig = [
        { id: 'l4', tid: 'rt4', bid: 'rb4', prod: <?php echo (int)$wood; ?>, max: <?php echo (int)$maxStore; ?>, name: 'Kayu' },
        { id: 'l3', tid: 'rt3', bid: 'rb3', prod: <?php echo (int)$clay; ?>, max: <?php echo (int)$maxStore; ?>, name: 'Tanah Liat' },
        { id: 'l2', tid: 'rt2', bid: 'rb2', prod: <?php echo (int)$iron; ?>, max: <?php echo (int)$maxStore; ?>, name: 'Besi' },
        { id: 'l1', tid: 'rt1', bid: 'rb1', prod: <?php echo (int)$crop; ?>, max: <?php echo (int)$maxCrop; ?>, name: 'Gandum' }
    ];

    function tzFormatTimer(s) {
        if (s <= 0) return '00:00:00';
        var d = Math.floor(s / 86400);
        var h = Math.floor((s % 86400) / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;

        if (d > 0) {
            return d + 'h ' + (h < 10 ? '0' : '') + h + 'j';
        }
        var hStr = (h < 10 ? '0' : '') + h;
        var mStr = (m < 10 ? '0' : '') + m;
        var sStr = (sec < 10 ? '0' : '') + sec;
        return hStr + ':' + mStr + ':' + sStr;
    }

    function tzFormatClock(d) {
        var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        var day = ('0' + d.getDate()).slice(-2);
        var month = months[d.getMonth()];
        var hour = ('0' + d.getHours()).slice(-2);
        var min = ('0' + d.getMinutes()).slice(-2);
        return day + ' ' + month + ' ' + hour + ':' + min;
    }

    function tzUpdateResTimers() {
        var minSeconds = Infinity;
        var criticalRes = null;
        var hasFull = false;

        for (var i = 0; i < tzProdConfig.length; i++) {
            var r = tzProdConfig[i];
            var el = document.getElementById(r.id);
            var timerEl = document.getElementById(r.tid);
            var barEl = document.getElementById(r.bid);
            if (!el || !timerEl) continue;

            var curVal = 0;
            if (typeof fb !== 'undefined' && fb[r.id] && fb[r.id].value !== undefined) {
                curVal = parseInt(fb[r.id].value, 10);
            } else {
                var m = el.innerHTML.match(/(\d+)\/(\d+)/);
                if (m) curVal = parseInt(m[1], 10);
            }

            var maxVal = r.max;
            var prod = r.prod;
            var pct = Math.min(100, Math.max(0, (curVal / maxVal) * 100));

            if (barEl) {
                barEl.style.width = pct + '%';
                if (pct >= 95) barEl.className = 'tz-res-bar-fill tz-critical';
                else if (pct >= 80) barEl.className = 'tz-res-bar-fill tz-warning';
                else barEl.className = 'tz-res-bar-fill tz-safe';
            }

            if (curVal >= maxVal) {
                hasFull = true;
                timerEl.innerHTML = '⚠️ PENUH!';
                timerEl.className = 'tz-res-timer tz-res-full';
                timerEl.title = r.name + ' sudah PENUH (' + curVal + '/' + maxVal + ')! Hasil panen hangus.';
            } else if (prod <= 0) {
                if (prod === 0) {
                    timerEl.innerHTML = '0/jam';
                    timerEl.className = 'tz-res-timer tz-res-idle';
                } else {
                    var secLeft = Math.max(0, Math.ceil(curVal / (Math.abs(prod) / 3600)));
                    timerEl.innerHTML = '⚠️ Habis ' + tzFormatTimer(secLeft);
                    timerEl.className = 'tz-res-timer tz-res-starve';
                    timerEl.title = 'Gandum defisit! Pasukan akan kelaparan dalam ' + tzFormatTimer(secLeft);
                }
            } else {
                var needed = maxVal - curVal;
                var secLeft = Math.max(0, Math.ceil(needed / (prod / 3600)));
                timerEl.innerHTML = '⏳ ' + tzFormatTimer(secLeft);
                if (pct >= 90) timerEl.className = 'tz-res-timer tz-res-warn';
                else timerEl.className = 'tz-res-timer tz-res-normal';

                var targetDate = new Date(Date.now() + secLeft * 1000);
                timerEl.title = r.name + ': ' + curVal + '/' + maxVal + ' (' + pct.toFixed(1) + '%)\nPenuh dalam: ' + tzFormatTimer(secLeft) + '\nEstimasi: ' + tzFormatClock(targetDate);

                if (secLeft < minSeconds) {
                    minSeconds = secLeft;
                    criticalRes = { name: r.name, sec: secLeft, date: targetDate, pct: pct };
                }
            }
        }

        var quickText = document.getElementById('tzQuickAlertText');
        if (quickText) {
            if (hasFull) {
                quickText.innerHTML = '<span class="tz-text-danger">🚨 PERINGATAN:</span> Ada sumber daya yang sudah <b class="tz-text-danger">PENUH (100%)</b>! Hasil panen sedang hangus.';
            } else if (criticalRes && minSeconds < Infinity) {
                var urgent = criticalRes.sec < 1800 ? ' <b class="tz-text-danger">(SEGERA PENUH!)</b>' : '';
                quickText.innerHTML = 'SDA Tercepat Penuh: <b>' + criticalRes.name + '</b> ⏳ <b>' + tzFormatTimer(criticalRes.sec) + '</b> (' + tzFormatClock(criticalRes.date) + ')' + urgent;
            }
        }
    }

    setInterval(tzUpdateResTimers, 1000);
    setTimeout(tzUpdateResTimers, 200);
})();
</script>

<?php } ?>