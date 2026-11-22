<?php
#################################################################################
##              -= ALLIANCE CO-OP EXPEDITION LOBBY WIDGET =-                  ##
## --------------------------------------------------------------------------- ##
##  Displays active forward camp lobbies in front of each alliance member's    ##
##  village overview (dorf1.php & dorf2.php) so everyone can see and join.    ##
#################################################################################

if (!isset($session) || (int)$session->uid <= 0) {
    return;
}

$allianceId = (int)($session->alliance ?? 0);
if ($allianceId <= 0) {
    return;
}

if (!class_exists('ExpeditionEngine')) {
    require_once dirname(__DIR__) . '/GameEngine/Expedition/ExpeditionEngine.php';
}

$activeExpeditions = ExpeditionEngine::getAllianceActiveExpeditions($allianceId);
if (empty($activeExpeditions)) {
    return;
}

$currWid = (int)($village->wid ?? 0);
$coorCurr = ($currWid > 0) ? $database->getCoor($currWid) : ['x' => 0, 'y' => 0];
$now = time();
?>

<style type="text/css">
.exp-village-widget {
    margin: 8px 0 12px 0;
    font-family: Arial, Helvetica, sans-serif;
}
.exp-lobby-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    margin-bottom: 8px;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.exp-lobby-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.exp-card-header {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
    color: #ffffff;
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11.5px;
    font-weight: bold;
    flex-wrap: wrap;
    gap: 6px;
}
.exp-card-header.marching {
    background: linear-gradient(135deg, #7c2d12 0%, #991b1b 100%);
}
.exp-header-badge {
    background: rgba(255,255,255,0.18);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10.5px;
    font-weight: normal;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.exp-card-body {
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: #fafafa;
    flex-wrap: wrap;
}
.exp-info-col {
    flex: 1;
    min-width: 220px;
}
.exp-action-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 6px;
}
.exp-target-title {
    font-size: 13px;
    font-weight: bold;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}
.exp-tier-tag {
    font-size: 9.5px;
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: bold;
    text-transform: uppercase;
}
.tier-boss {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
.tier-fortress {
    background: #f3e8ff;
    color: #7e22ce;
    border: 1px solid #d8b4fe;
}
.exp-meta-line {
    font-size: 11px;
    color: #475569;
    line-height: 1.45;
}
.exp-btn-cta {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: bold;
    text-decoration: none;
    text-align: center;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
.exp-btn-join {
    background: #16a34a;
    color: #ffffff !important;
    border: 1px solid #15803d;
}
.exp-btn-join:hover {
    background: #15803d;
    box-shadow: 0 2px 6px rgba(22,163,74,0.3);
    color: #ffffff !important;
}
.exp-btn-joined {
    background: #e0f2fe;
    color: #0369a1 !important;
    border: 1px solid #bae6fd;
}
.exp-btn-joined:hover {
    background: #bae6fd;
    color: #0284c7 !important;
}
.exp-btn-full {
    background: #e2e8f0;
    color: #475569 !important;
    border: 1px solid #cbd5e1;
}
.exp-btn-marching {
    background: #b91c1c;
    color: #ffffff !important;
    border: 1px solid #991b1b;
}
.exp-badge-joined-pill {
    font-size: 10px;
    padding: 2px 6px;
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
    border-radius: 4px;
    font-weight: bold;
}
.exp-badge-full-pill {
    font-size: 10px;
    padding: 2px 6px;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-weight: bold;
}
@media (max-width: 600px) {
    .exp-card-body {
        flex-direction: column;
        align-items: flex-start;
    }
    .exp-action-col {
        width: 100%;
        align-items: stretch;
        margin-top: 6px;
    }
    .exp-btn-cta {
        width: 100%;
        box-sizing: border-box;
    }
}
</style>

<div class="exp-village-widget">
    <?php foreach ($activeExpeditions as $eData): 
        $exp = $eData['expedition'];
        $members = $eData['members'];
        $mCount = (int)$eData['member_count'];
        $isMarching = ((int)$exp['status'] === ExpeditionEngine::STATUS_MARCHING);
        $remSec = max(0, (int)$exp['impact_time'] - $now);

        // Cari username pemimpin
        $leaderName = 'Aliansi';
        foreach ($members as $m) {
            if ((int)$m['uid'] === (int)$exp['leader_uid']) {
                $leaderName = $m['username'];
                break;
            }
        }

        // Cek apakah user saat ini sudah bergabung
        $hasUserJoined = false;
        $myStatus = -1;
        $myArriveTime = 0;
        foreach ($members as $m) {
            if ((int)$m['uid'] === (int)$session->uid) {
                $hasUserJoined = true;
                $myStatus = (int)$m['status'];
                $myArriveTime = (int)$m['arrive_time'];
                break;
            }
        }

        // Hitung jarak dari desa aktif saat ini ke Virtual Camp
        $dx = abs(($coorCurr['x'] ?? 0) - (int)$exp['camp_x']);
        if ($dx > WORLD_MAX) $dx = (2 * WORLD_MAX + 1) - $dx;
        $dy = abs(($coorCurr['y'] ?? 0) - (int)$exp['camp_y']);
        if ($dy > WORLD_MAX) $dy = (2 * WORLD_MAX + 1) - $dy;
        $distToCamp = round(sqrt(pow($dx, 2) + pow($dy, 2)), 1);

        $timerNum = ++$session->timer;
        $fmtTimer = sprintf('%02d:%02d:%02d', intdiv($remSec, 3600), intdiv($remSec % 3600, 60), $remSec % 60);

        $isTier4 = ((int)$exp['camp_tier'] === 4);
    ?>
    <div class="exp-lobby-card">
        <div class="exp-card-header <?php echo $isMarching ? 'marching' : ''; ?>">
            <div>
                <?php if ($isMarching): ?>
                    🔥 <b>SERANGAN KOALISI ALIANSI SEDANG BERLANGSUNG!</b>
                <?php else: ?>
                    🎺 <b>PANGGILAN PERANG KOALISI ALIANSI &bull; FORWARD CAMP DIBUKA!</b>
                <?php endif; ?>
            </div>
            <div class="exp-header-badge">
                <?php if ($isMarching): ?>
                    💥 Benturan dalam: <span id="timer<?php echo $timerNum; ?>" class="exp-live-timer" data-seconds="<?php echo $remSec; ?>"><?php echo $fmtTimer; ?></span>
                <?php else: ?>
                    ⏱️ Sisa Waktu Lobi: <span id="timer<?php echo $timerNum; ?>" class="exp-live-timer" data-seconds="<?php echo $remSec; ?>"><?php echo $fmtTimer; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="exp-card-body">
            <div class="exp-info-col">
                <div class="exp-target-title">
                    <span>🎯 <?php echo htmlspecialchars($exp['camp_name']); ?></span>
                    <span class="exp-tier-tag <?php echo $isTier4 ? 'tier-boss' : 'tier-fortress'; ?>">
                        <?php echo $isTier4 ? 'World Boss' : 'Benteng Bandit'; ?>
                    </span>
                    <span style="font-size: 11px; color: #64748b; font-weight: normal;">(<?php echo $exp['x']; ?>|<?php echo $exp['y']; ?>)</span>
                </div>

                <div class="exp-meta-line">
                    🏰 <b>Virtual Camp:</b> Desa <b><?php echo htmlspecialchars($exp['camp_vname']); ?> (<?php echo $exp['camp_x']; ?>|<?php echo $exp['camp_y']; ?>)</b> milik <b><?php echo htmlspecialchars($leaderName); ?></b><br>
                    📍 <b>Rute dari desa ini (<?php echo htmlspecialchars($village->vname); ?>):</b> Jarak <b><?php echo $distToCamp; ?> tiles</b> &bull; Pasukan butuh waktu berjalan ke camp.
                </div>

                <div class="exp-meta-line" style="margin-top: 3px; font-weight: bold; color: #334155;">
                    👥 <b>Peserta Koalisi:</b> <?php echo $mCount; ?>/5 Pemain &bull; ⚔️ <b>Total Power:</b> <?php echo number_format($eData['total_attack']); ?>
                </div>
            </div>

            <div class="exp-action-col">
                <?php if ($isMarching): ?>
                    <a href="expedition.php?id=<?php echo $exp['id']; ?>" class="exp-btn-cta exp-btn-marching">
                        🔥 Pantau Benturan &raquo;
                    </a>
                <?php elseif ($hasUserJoined): ?>
                    <span class="exp-badge-joined-pill">
                        <?php 
                        if ($myStatus === 0 && $myArriveTime > $now) {
                            $remArrive = $myArriveTime - $now;
                            $mArriveFmt = sprintf('%02d:%02d:%02d', intdiv($remArrive, 3600), intdiv($remArrive % 3600, 60), $remArrive % 60);
                            echo "🚀 Berbaris ke Camp (Tiba {$mArriveFmt})";
                        } else {
                            echo "✅ Pasukan Siaga di Camp";
                        }
                        ?>
                    </span>
                    <a href="expedition.php?id=<?php echo $exp['id']; ?>" class="exp-btn-cta exp-btn-joined">
                        🏰 Masuk Ruang Lobi &raquo;
                    </a>
                <?php elseif ($mCount >= 5): ?>
                    <span class="exp-badge-full-pill">🔒 Lobi Penuh (5/5)</span>
                    <a href="expedition.php?id=<?php echo $exp['id']; ?>" class="exp-btn-cta exp-btn-full">
                        Lihat Lobi &raquo;
                    </a>
                <?php else: ?>
                    <span style="font-size: 10px; color: #15803d; font-weight: bold;">
                        ✨ Tersisa <?php echo (5 - $mCount); ?> Slot Pasukan!
                    </span>
                    <a href="expedition.php?id=<?php echo $exp['id']; ?>" class="exp-btn-cta exp-btn-join">
                        ⚔️ GABUNG SEKARANG &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script type="text/javascript">
(function() {
    // Pastikan live timer di widget desa berjalan setiap detik
    function tickVillageExpTimers() {
        var timers = document.querySelectorAll('.exp-village-widget .exp-live-timer');
        for (var i = 0; i < timers.length; i++) {
            var el = timers[i];
            var s = parseInt(el.getAttribute('data-seconds'), 10);
            if (isNaN(s)) continue;
            if (s > 0) {
                s--;
                el.setAttribute('data-seconds', s);
                var h = Math.floor(s / 3600);
                var m = Math.floor((s % 3600) / 60);
                var sec = s % 60;
                el.innerHTML = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
            } else if (s === 0) {
                el.innerHTML = '00:00:00';
                el.setAttribute('data-seconds', -1);
                setTimeout(function() { window.location.reload(); }, 2000);
            }
        }
    }
    if (!window.expVillageTimerInterval) {
        window.expVillageTimerInterval = setInterval(tickVillageExpTimers, 1000);
    }
})();
</script>
