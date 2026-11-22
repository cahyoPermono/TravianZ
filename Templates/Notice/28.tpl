<?php
#################################################################################
##                -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-               ##
## --------------------------------------------------------------------------- ##
##  Filename       : 28.tpl                                                    ##
##  Type           : Bandit Camp / Intel Report                                ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Shadow & TravianZ Community                               ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

$rawContent = (string)($message->readingNotice['data'] ?? '');
$parsed = [];
parse_str($rawContent, $parsed);

$isIntel = (!empty($parsed['type']) && $parsed['type'] === 'bandit_intel');
$isExpedition = (!empty($parsed['type']) && $parsed['type'] === 'expedition_report');
$expAction = $parsed['action'] ?? '';
$expLosses = $parsed['losses_pct'] ?? '0';
$expWood = (int)($parsed['wood'] ?? 0);
$expClay = (int)($parsed['clay'] ?? 0);
$expIron = (int)($parsed['iron'] ?? 0);
$expCrop = (int)($parsed['crop'] ?? 0);
$expSilver = (int)($parsed['silver'] ?? 0);
$expCp = (int)($parsed['cp'] ?? 0);
$expExp = (int)($parsed['exp'] ?? 0);
$expEssence = (int)($parsed['essence'] ?? 0);
$expReason = $parsed['reason'] ?? '';
$campName = $parsed['camp_name'] ?? 'Sarang Penyamun';
$conqueror = $parsed['conqueror'] ?? 'Pejuang Sekutu';
$conquerorUid = (int)($parsed['conqueror_uid'] ?? 0);
$wref = (int)($parsed['wref'] ?? ($message->readingNotice['toWref'] ?? 0));
$returnDur = $parsed['return_duration'] ?? '';
$origDur = $parsed['orig_duration'] ?? '';
$silver = (int)($parsed['silver'] ?? 0);
$exp = (int)($parsed['exp'] ?? 0);
$msgText = $parsed['message'] ?? $rawContent;

$coor = $database->getCoor($wref);
$mapCheck = $generator->getMapCheck($wref);
$campLink = '<a href="karte.php?d=' . $wref . '&c=' . $mapCheck . '"><b>' . htmlspecialchars($campName, ENT_QUOTES, 'UTF-8') . '</b> (' . ($coor['x'] ?? 0) . '|' . ($coor['y'] ?? 0) . ')</a>';
$conquerorLink = ($conquerorUid > 0)
    ? '<a href="spieler.php?uid=' . $conquerorUid . '"><b>' . htmlspecialchars($conqueror, ENT_QUOTES, 'UTF-8') . '</b></a>'
    : '<b>' . htmlspecialchars($conqueror, ENT_QUOTES, 'UTF-8') . '</b>';
?>

<table cellpadding="1" cellspacing="1" id="report_surround">
<thead>
<tr>
    <th><?php echo defined('SUBJECT') ? SUBJECT : 'Subject'; ?>:</th>
    <th><?php echo tz_loc_topic($message->readingNotice['topic']); ?></th>
</tr>
<tr>
    <?php $date = $generator->procMtime($message->readingNotice['time']); ?>
    <td class="sent"><?php echo defined('TZ_SENT') ? TZ_SENT : 'Sent'; ?></td>
    <td><?php echo (defined('ON') ? ON : 'on') . ' '; ?><span><?php echo $date[0] . " " . $date[1]; ?></span></td>
</tr>
</thead>
<tbody>
<tr><td colspan="2" class="empty"></td></tr>
<tr><td colspan="2" class="report_content" style="padding: 12px; font-size: 13px; line-height: 1.6;">

<?php if ($isIntel) { ?>
    <div style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #f8fafc; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);">
        <div style="display: flex; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
            <span style="font-size: 24px; margin-right: 10px;">🏹</span>
            <div>
                <h3 style="margin: 0; color: #38bdf8; font-size: 16px;">Laporan Intel Pengintai: Pasukan Berbalik Arah</h3>
                <span style="color: #94a3b8; font-size: 12px;">Sarang Penyamun Berhasil Ditaklukkan Terlebih Dahulu</span>
            </div>
        </div>

        <p style="margin: 0 0 10px 0; color: #cbd5e1;">
            Pengintai garis depan mendeteksi bahwa target <?php echo $campLink; ?> telah dihancurkan rata dengan tanah oleh <?php echo $conquerorLink; ?> sebelum armada pasukanmu tiba.
        </p>

        <div style="background: rgba(15, 23, 42, 0.6); border-left: 3px solid #38bdf8; padding: 10px 14px; margin: 12px 0; border-radius: 0 6px 6px 0;">
            <p style="margin: 0 0 6px 0; color: #e2e8f0; font-weight: bold;">
                🔄 Perjalanan Dipangkas (Auto-Recall dari Posisi Terkini):
            </p>
            <p style="margin: 0; color: #94a3b8; font-size: 12px;">
                Pasukan tidak perlu berjalan sia-sia sampai ke sarang yang telah hancur. Komandan regu langsung memerintahkan pasukan memutar balik dari posisinya di jalan.
                <?php if (!empty($returnDur)) { ?>
                    <br>⏱️ <b>Waktu Tempuh Pulang:</b> <span style="color: #38bdf8; font-weight: bold;"><?php echo htmlspecialchars($returnDur); ?></span>
                    <?php if (!empty($origDur)) { ?>
                        <span style="color: #64748b;">(jauh lebih hemat daripada pergi-pulang penuh <?php echo htmlspecialchars($origDur); ?>)</span>
                    <?php } ?>
                <?php } ?>
            </p>
        </div>

        <?php if ($silver > 0 || $exp > 0) { ?>
        <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 6px; padding: 12px; margin-top: 14px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="font-weight: bold; color: #4ade80;">
                    🎁 Kompensasi Bekal Intel Pengintai:
                </div>
                <div style="font-size: 14px; font-weight: bold;">
                    <?php if ($silver > 0) { ?>
                        <span style="color: #fbbf24; margin-right: 12px;">🪙 +<?php echo $silver; ?> Silver</span>
                    <?php } ?>
                    <?php if ($exp > 0) { ?>
                        <span style="color: #60a5fa;">⭐ +<?php echo $exp; ?> Hero EXP</span>
                    <?php } ?>
                </div>
            </div>
            <p style="margin: 6px 0 0 0; color: #86efac; font-size: 11px;">
                Bonus koin perak dan pengalaman telah otomatis ditambahkan ke status Hero Anda sebagai kompensasi bekal jalan.
            </p>
        </div>
        <?php } ?>
    </div>
<?php } elseif ($isExpedition) { 
    // Ensure ExpeditionEngine and Technology are available
    if (!class_exists("ExpeditionEngine")) {
        require_once dirname(__DIR__, 2) . "/GameEngine/Expedition/ExpeditionEngine.php";
    }
    if (!isset($technology)) {
        if (class_exists("Technology")) {
            $technology = new Technology();
        } else {
            require_once dirname(__DIR__, 2) . "/GameEngine/Technology.php";
            $technology = new Technology();
        }
    }

    $expeditionId = (int)($parsed["expedition_id"] ?? 0);
    $noticeUid = (int)($message->readingNotice["uid"] ?? 0);
    $noticeWref = (int)($message->readingNotice["toWref"] ?? 0);
    $noticeTime = (int)($message->readingNotice["time"] ?? 0);

    // Backward compatibility lookup if expedition_id was not in notice query string
    if ($expeditionId <= 0 && $noticeUid > 0) {
        if ($wref > 0) {
            $lookup = $database->query_return("
                SELECT e.id 
                FROM `" . TB_PREFIX . "expeditions` e 
                JOIN `" . TB_PREFIX . "expedition_members` m ON e.id = m.expedition_id 
                WHERE m.uid = {$noticeUid} AND e.target_wref = {$wref} 
                ORDER BY ABS(e.impact_time - {$noticeTime}) ASC, e.id DESC 
                LIMIT 1
            ");
            if (!empty($lookup[0]["id"])) {
                $expeditionId = (int)$lookup[0]["id"];
            }
        }
        if ($expeditionId <= 0) {
            $lookup = $database->query_return("
                SELECT e.id 
                FROM `" . TB_PREFIX . "expeditions` e 
                JOIN `" . TB_PREFIX . "expedition_members` m ON e.id = m.expedition_id 
                WHERE m.uid = {$noticeUid} 
                ORDER BY ABS(e.impact_time - {$noticeTime}) ASC, e.id DESC 
                LIMIT 1
            ");
            if (!empty($lookup[0]["id"])) {
                $expeditionId = (int)$lookup[0]["id"];
            }
        }
    }

    $expeditionData = [];
    $expMembers = [];
    $allTribes = [];
    $totalExpAtk = 0;
    $totalExpDamage = 0;
    $totalExpSent = 0;
    $totalExpLost = 0;
    $totalExpSurvived = 0;
    $leaderUid = 0;

    if ($expeditionId > 0) {
        $expRows = $database->query_return("SELECT * FROM `" . TB_PREFIX . "expeditions` WHERE id = {$expeditionId} LIMIT 1");
        if (!empty($expRows[0])) {
            $expeditionData = $expRows[0];
            $leaderUid = (int)$expeditionData["leader_uid"];
        }

        $expMembers = $database->query_return("
            SELECT m.*, u.username, v.name AS village_name 
            FROM `" . TB_PREFIX . "expedition_members` m 
            LEFT JOIN `" . TB_PREFIX . "users` u ON u.id = m.uid 
            LEFT JOIN `" . TB_PREFIX . "vdata` v ON v.wref = m.from_wref 
            WHERE m.expedition_id = {$expeditionId} 
            ORDER BY (m.uid = {$leaderUid}) DESC, m.attack_power DESC, m.id ASC
        ");

        if (!empty($expMembers)) {
            foreach ($expMembers as $m) {
                $allTribes[] = (int)$m["tribe"];
                $totalExpAtk += (int)$m["attack_power"];
                $totalExpDamage += (int)$m["damage_dealt"];
            }
        }
    }

    $synergies = ExpeditionEngine::computeSynergiesFromTribes($allTribes);
    $hasGaul = in_array(3, $allTribes, true);
    $casualtyRecovery = $hasGaul ? 0.10 : 0.0;

    $tribeNames = [
        1 => "Romans",
        2 => "Teutons",
        3 => "Gauls",
        6 => "Huns",
        7 => "Egyptians",
        8 => "Spartans",
        9 => "Vikings",
        10 => "Nusantara"
    ];
    $tribeColors = [
        1 => "#38bdf8",
        2 => "#f97316",
        3 => "#22c55e",
        6 => "#ef4444",
        7 => "#eab308",
        8 => "#a855f7",
        9 => "#06b6d4",
        10 => "#ec4899"
    ];

    $lossPctFloat = (float)$expLosses;
    if ($lossPctFloat <= 0 && !empty($expMembers[0]["losses_pct"])) {
        $lossPctFloat = (float)$expMembers[0]["losses_pct"];
    }
?>
    <!-- ALLIANCE EXPEDITION REPORT -->
    <div style="background: linear-gradient(135deg, #090d16, #111827); color: #f8fafc; border: 1px solid #3b82f6; border-radius: 10px; padding: 16px; margin-bottom: 14px; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.45); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;">
        
        <!-- Header Banner -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border-bottom: 1px solid rgba(59, 130, 246, 0.3); padding-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 32px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));">
                    <?php echo ($expAction === "victory") ? "👑" : (($expAction === "cancelled") ? "🛡️" : "💀"); ?>
                </span>
                <div>
                    <h3 style="margin: 0; color: <?php echo ($expAction === "victory") ? "#38bdf8" : (($expAction === "cancelled") ? "#fbbf24" : "#f87171"); ?>; font-size: 17px; font-weight: bold; letter-spacing: 0.3px;">
                        Laporan Alliance Expedition: <?php echo ($expAction === "victory") ? "Kemenangan Forward Camp" : (($expAction === "cancelled") ? "Ekspedisi Dibatalkan" : "Terpukul Mundur"); ?>
                    </h3>
                    <div style="color: #94a3b8; font-size: 12px; margin-top: 3px;">
                        Target Benturan: <?php echo $campLink; ?>
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; color: #93c5fd;">
                    Forward Camp
                </span>
                <?php if ($lossPctFloat > 0) { ?>
                <span style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; color: #fca5a5;">
                    Kerugian: -<?php echo number_format($lossPctFloat, 1); ?>%
                </span>
                <?php } ?>
            </div>
        </div>

        <!-- Narasi Ringkasan -->
        <div style="background: rgba(15, 23, 42, 0.75); border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; border-left: 4px solid #3b82f6; font-size: 13px; line-height: 1.6; color: #e2e8f0;">
            <?php echo nl2br(htmlspecialchars($msgText, ENT_QUOTES, "UTF-8")); ?>
        </div>

        <?php if (!empty($synergies["synergies"]) || !empty($synergies["coalition_bonus"])) { ?>
        <!-- Active Sinergi Badges -->
        <div style="background: rgba(30, 27, 75, 0.5); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px;">
            <div style="font-size: 12px; font-weight: bold; color: #a5b4fc; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <span>✨ Sinergi Suku Aliansi yang Berperan:</span>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <?php foreach ($synergies["synergies"] as $syn) { ?>
                    <span style="background: rgba(99, 102, 241, 0.25); border: 1px solid #6366f1; color: #e0e7ff; padding: 3px 8px; border-radius: 6px; font-size: 11px;" title="<?php echo htmlspecialchars($syn["desc"]); ?>">
                        <?php echo htmlspecialchars($syn["badge"]); ?> (<?php echo htmlspecialchars($syn["tribe_name"]); ?>)
                    </span>
                <?php } ?>
                <?php if (!empty($synergies["coalition_bonus"])) { ?>
                    <span style="background: rgba(245, 158, 11, 0.25); border: 1px solid #f59e0b; color: #fef3c7; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: bold;">
                        ⭐ <?php echo htmlspecialchars($synergies["coalition_bonus"]["name"]); ?> (+5% Atk/Def)
                    </span>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if (!empty($expMembers)) { ?>
        <!-- SECTION 1: RINCIAN PASUKAN & ASAL DESA MASING-MASING PESERTA -->
        <div style="margin-bottom: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <h4 style="margin: 0; color: #38bdf8; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                    <span>🛡️ Rincian Pasukan &amp; Kontribusi per Anggota (<?php echo count($expMembers); ?> Peserta)</span>
                </h4>
                <span style="font-size: 11px; color: #94a3b8;">Format: Dikirim &bull; Gugur &bull; Selamat Pulang</span>
            </div>

            <?php 
            foreach ($expMembers as $idx => $m) {
                $mUid = (int)$m["uid"];
                $isLeader = ($mUid === $leaderUid);
                $isCurrentUser = ($mUid === (int)($session->uid ?? 0) || $mUid === $noticeUid);
                $mTribe = (int)$m["tribe"];
                $tribeName = $tribeNames[$mTribe] ?? "Suku " . $mTribe;
                $tribeColor = $tribeColors[$mTribe] ?? "#94a3b8";

                $fromWref = (int)$m["from_wref"];
                $fromCoor = $database->getCoor($fromWref);
                $fromMapCheck = $generator->getMapCheck($fromWref);
                $fromVilName = !empty($m["village_name"]) ? htmlspecialchars($m["village_name"], ENT_QUOTES, "UTF-8") : "Desa #" . $fromWref;
                $villageLink = '<a href="karte.php?d=' . $fromWref . '&c=' . $fromMapCheck . '" style="color: #93c5fd; text-decoration: underline;"><b>' . $fromVilName . '</b> (' . ($fromCoor["x"] ?? 0) . '|' . ($fromCoor["y"] ?? 0) . ')</a>';
                $userLink = '<a href="spieler.php?uid=' . $mUid . '" style="color: #67e8f9; font-weight: bold; text-decoration: none;">' . htmlspecialchars($m["username"] ?? ("Pemain #" . $mUid), ENT_QUOTES, "UTF-8") . '</a>';

                $mAtk = (int)$m["attack_power"];
                $mDmg = (int)$m["damage_dealt"];
                $dmgPct = ($totalExpDamage > 0) ? round(($mDmg / $totalExpDamage) * 100, 1) : (($totalExpAtk > 0) ? round(($mAtk / $totalExpAtk) * 100, 1) : 0);

                // Hitung unit sent, lost, recovered, survived
                $mSentTotal = 0;
                $mLostTotal = 0;
                $mRecTotal = 0;
                $mSurvTotal = 0;

                $unitData = [];
                $lossRatio = $lossPctFloat / 100.0;

                for ($i = 1; $i <= 10; $i++) {
                    $uCount = (int)$m["u" . $i];
                    $globalUId = (($mTribe - 1) * 10) + $i;
                    $uName = $technology->getUnitName($globalUId);

                    $uLost = (int)round($uCount * $lossRatio);
                    $uLost = min($uCount, max(0, $uLost));
                    $uRec = ($casualtyRecovery > 0 && $uLost > 0) ? (int)round($uLost * $casualtyRecovery) : 0;
                    $uRec = min($uLost, max(0, $uRec));
                    $uSurv = ($uCount - $uLost) + $uRec;

                    $mSentTotal += $uCount;
                    $mLostTotal += $uLost;
                    $mRecTotal += $uRec;
                    $mSurvTotal += $uSurv;

                    $unitData[$i] = [
                        "gid" => $globalUId,
                        "name" => $uName,
                        "sent" => $uCount,
                        "lost" => $uLost,
                        "rec" => $uRec,
                        "surv" => $uSurv
                    ];
                }

                $totalExpSent += $mSentTotal;
                $totalExpLost += $mLostTotal;
                $totalExpSurvived += $mSurvTotal;

                $cardBorder = $isCurrentUser ? "2px solid #38bdf8" : "1px solid rgba(51, 65, 85, 0.8)";
                $cardBg = $isCurrentUser ? "linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95))" : "rgba(15, 23, 42, 0.65)";
                $cardShadow = $isCurrentUser ? "0 0 12px rgba(56, 189, 248, 0.25)" : "none";
            ?>
            <div style="background: <?php echo $cardBg; ?>; border: <?php echo $cardBorder; ?>; box-shadow: <?php echo $cardShadow; ?>; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                
                <!-- Squad Header Info -->
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(51, 65, 85, 0.6); padding-bottom: 8px; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 15px;">
                            <?php echo $isLeader ? "👑" : "⚔️"; ?>
                        </span>
                        <span style="font-size: 13px;">
                            <?php echo $userLink; ?>
                        </span>
                        <span style="background: <?php echo $tribeColor; ?>22; border: 1px solid <?php echo $tribeColor; ?>; color: <?php echo $tribeColor; ?>; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
                            <?php echo htmlspecialchars($tribeName); ?>
                        </span>
                        <?php if ($isLeader) { ?>
                            <span style="background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #fbbf24; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
                                Rally Leader
                            </span>
                        <?php } ?>
                        <?php if ($m["has_hero"]) { ?>
                            <span style="background: rgba(168, 85, 247, 0.2); border: 1px solid #a855f7; color: #d8b4fe; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
                                ⭐ Hero
                            </span>
                        <?php } ?>
                        <?php if ($isCurrentUser) { ?>
                            <span style="background: rgba(56, 189, 248, 0.2); border: 1px solid #38bdf8; color: #7dd3fc; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
                                👤 Regu Anda
                            </span>
                        <?php } ?>
                    </div>
                    <div style="font-size: 12px; color: #94a3b8;">
                        Desa Asal: <?php echo $villageLink; ?>
                    </div>
                </div>

                <!-- Squad Power & Contribution Stat -->
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; margin-bottom: 8px; color: #cbd5e1; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span>⚡ Kekuatan Serang: <b style="color: #fbbf24;"><?php echo number_format($mAtk); ?></b></span>
                        <span>🎯 Damage Diberikan: <b style="color: #38bdf8;"><?php echo number_format($mDmg); ?></b></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span>Kontribusi Serangan: <b style="color: #4ade80;"><?php echo $dmgPct; ?>%</b></span>
                        <div style="background: rgba(51, 65, 85, 0.8); border-radius: 999px; height: 6px; width: 70px; overflow: hidden; display: inline-block;">
                            <div style="background: linear-gradient(90deg, #38bdf8, #4ade80); height: 100%; width: <?php echo min(100, max(0, $dmgPct)); ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Squad Troop Table (Scrollable on small screens) -->
                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 8px; border-radius: 6px; border: 1px solid #334155;">
                    <table style="width: 100%; border-collapse: collapse; text-align: center; font-size: 11px; min-width: 520px;">
                        <thead>
                            <tr style="background: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155;">
                                <th style="padding: 6px 8px; text-align: left; color: #94a3b8; font-weight: normal; width: 125px;">Kategori</th>
                                <?php for ($i = 1; $i <= 10; $i++) { ?>
                                    <th style="padding: 4px; min-width: 36px;">
                                        <img src="img/x.gif" class="unit u<?php echo $unitData[$i]["gid"]; ?>" title="<?php echo htmlspecialchars($unitData[$i]["name"]); ?>" alt="<?php echo htmlspecialchars($unitData[$i]["name"]); ?>" style="vertical-align: middle;" />
                                    </th>
                                <?php } ?>
                                <?php if ($m["has_hero"]) { ?>
                                    <th style="padding: 4px; min-width: 36px;">
                                        <img src="img/x.gif" class="unit uhero" title="Hero" alt="Hero" style="vertical-align: middle;" />
                                    </th>
                                <?php } ?>
                                <th style="padding: 6px 8px; text-align: right; color: #94a3b8; font-weight: normal; width: 60px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row: Pasukan Awal Dikirim -->
                            <tr style="border-bottom: 1px solid rgba(51, 65, 85, 0.4); background: rgba(15, 23, 42, 0.4);">
                                <td style="padding: 5px 8px; text-align: left; font-weight: bold; color: #f1f5f9;">Pasukan Awal</td>
                                <?php for ($i = 1; $i <= 10; $i++) { 
                                    $cnt = $unitData[$i]["sent"];
                                ?>
                                    <td style="padding: 4px; color: <?php echo ($cnt > 0) ? "#f8fafc" : "#475569"; ?>; font-weight: <?php echo ($cnt > 0) ? "bold" : "normal"; ?>;">
                                        <?php echo number_format($cnt); ?>
                                    </td>
                                <?php } ?>
                                <?php if ($m["has_hero"]) { ?>
                                    <td style="padding: 4px; color: #f8fafc; font-weight: bold;">1</td>
                                <?php } ?>
                                <td style="padding: 5px 8px; text-align: right; font-weight: bold; color: #f8fafc;">
                                    <?php echo number_format($mSentTotal + ($m["has_hero"] ? 1 : 0)); ?>
                                </td>
                            </tr>

                            <!-- Row: Gugur (Losses) -->
                            <tr style="border-bottom: 1px solid rgba(51, 65, 85, 0.4); background: rgba(239, 68, 68, 0.08);">
                                <td style="padding: 5px 8px; text-align: left; font-weight: bold; color: #f87171;">
                                    Gugur (-<?php echo number_format($lossPctFloat, 1); ?>%)
                                </td>
                                <?php for ($i = 1; $i <= 10; $i++) { 
                                    $lst = $unitData[$i]["lost"];
                                ?>
                                    <td style="padding: 4px; color: <?php echo ($lst > 0) ? "#f87171" : "#475569"; ?>; font-weight: <?php echo ($lst > 0) ? "bold" : "normal"; ?>;">
                                        <?php echo ($lst > 0) ? ("-" . number_format($lst)) : "0"; ?>
                                    </td>
                                <?php } ?>
                                <?php if ($m["has_hero"]) { 
                                    $heroDead = ($lossPctFloat >= 100) ? 1 : 0;
                                ?>
                                    <td style="padding: 4px; color: <?php echo ($heroDead > 0) ? "#f87171" : "#475569"; ?>; font-weight: bold;">
                                        <?php echo ($heroDead > 0) ? "-1" : "0"; ?>
                                    </td>
                                <?php } ?>
                                <td style="padding: 5px 8px; text-align: right; font-weight: bold; color: #f87171;">
                                    <?php echo ($mLostTotal > 0) ? ("-" . number_format($mLostTotal)) : "0"; ?>
                                </td>
                            </tr>

                            <?php if ($hasGaul) { ?>
                            <!-- Row: Pemulihan Herbal Galia (+10%) -->
                            <tr style="border-bottom: 1px solid rgba(51, 65, 85, 0.4); background: rgba(34, 197, 94, 0.08);">
                                <td style="padding: 5px 8px; text-align: left; font-weight: bold; color: #4ade80;">
                                    Sembuh (Galia)
                                </td>
                                <?php for ($i = 1; $i <= 10; $i++) { 
                                    $rec = $unitData[$i]["rec"];
                                ?>
                                    <td style="padding: 4px; color: <?php echo ($rec > 0) ? "#4ade80" : "#475569"; ?>; font-weight: <?php echo ($rec > 0) ? "bold" : "normal"; ?>;">
                                        <?php echo ($rec > 0) ? ("+" . number_format($rec)) : "0"; ?>
                                    </td>
                                <?php } ?>
                                <?php if ($m["has_hero"]) { ?>
                                    <td style="padding: 4px; color: #475569;">0</td>
                                <?php } ?>
                                <td style="padding: 5px 8px; text-align: right; font-weight: bold; color: #4ade80;">
                                    <?php echo ($mRecTotal > 0) ? ("+" . number_format($mRecTotal)) : "0"; ?>
                                </td>
                            </tr>
                            <?php } ?>

                            <!-- Row: Selamat Pulang ke Desa -->
                            <tr style="background: rgba(56, 189, 248, 0.08);">
                                <td style="padding: 5px 8px; text-align: left; font-weight: bold; color: #38bdf8;">
                                    Selamat Pulang
                                </td>
                                <?php for ($i = 1; $i <= 10; $i++) { 
                                    $srv = $unitData[$i]["surv"];
                                ?>
                                    <td style="padding: 4px; color: <?php echo ($srv > 0) ? "#38bdf8" : "#475569"; ?>; font-weight: <?php echo ($srv > 0) ? "bold" : "normal"; ?>;">
                                        <?php echo number_format($srv); ?>
                                    </td>
                                <?php } ?>
                                <?php if ($m["has_hero"]) { 
                                    $heroSurv = ($lossPctFloat >= 100) ? 0 : 1;
                                ?>
                                    <td style="padding: 4px; color: #38bdf8; font-weight: bold;">
                                        <?php echo $heroSurv; ?>
                                    </td>
                                <?php } ?>
                                <td style="padding: 5px 8px; text-align: right; font-weight: bold; color: #38bdf8;">
                                    <?php echo number_format($mSurvTotal + (($m["has_hero"] && $lossPctFloat < 100) ? 1 : 0)); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Squad Rewards Obtained -->
                <?php if ($expAction === "victory") { ?>
                <div style="background: rgba(15, 23, 42, 0.6); border-radius: 6px; padding: 6px 10px; font-size: 11px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                    <div style="color: #94a3b8;">
                        🎁 <b>Rampasan Masuk ke Desa <?php echo htmlspecialchars($fromVilName); ?>:</b>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 11px;">
                        <span>🌲 <b style="color: #4ade80;">+<?php echo number_format((int)$m["reward_wood"]); ?></b></span>
                        <span>🧱 <b style="color: #fb923c;">+<?php echo number_format((int)$m["reward_clay"]); ?></b></span>
                        <span>⛏️ <b style="color: #94a3b8;">+<?php echo number_format((int)$m["reward_iron"]); ?></b></span>
                        <span>🌾 <b style="color: #facc15;">+<?php echo number_format((int)$m["reward_crop"]); ?></b></span>
                        <span>🪙 <b style="color: #38bdf8;">+<?php echo number_format((int)$m["reward_silver"]); ?></b></span>
                        <span>🏛️ <b style="color: #c084fc;">+<?php echo number_format((int)$m["reward_cp"]); ?></b></span>
                        <span>⭐ <b style="color: #e879f9;">+<?php echo number_format((int)$m["reward_exp"]); ?> EXP</b></span>
                        <?php if (!empty($m["reward_essence"])) { ?>
                            <span>💎 <b style="color: #f472b6;">+<?php echo number_format((int)$m["reward_essence"]); ?> Essence</b></span>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- SECTION 2: LEADERBOARD KONTRIBUSI KESELURUHAN ALIANSI -->
        <?php if (!empty($expMembers) && count($expMembers) > 1) { ?>
        <div style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 14px;">
            <div style="font-weight: bold; color: #93c5fd; font-size: 13px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                <span>📊 Klasemen Kontribusi Serangan Aliansi</span>
                <span style="font-size: 11px; color: #64748b; font-weight: normal;">Sistem Bagi Hasil: 50% Rata + 50% Proporsional Damage</span>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid #334155; color: #94a3b8;">
                            <th style="padding: 6px 8px;">Peserta</th>
                            <th style="padding: 6px 8px;">Suku</th>
                            <th style="padding: 6px 8px; text-align: right;">Offense Power</th>
                            <th style="padding: 6px 8px; text-align: right;">Damage Boss</th>
                            <th style="padding: 6px 8px; width: 140px;">Porsi Kontribusi</th>
                            <th style="padding: 6px 8px; text-align: right;">Status Pasukan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($expMembers as $mRow) { 
                            $rUid = (int)$mRow["uid"];
                            $rAtk = (int)$mRow["attack_power"];
                            $rDmg = (int)$mRow["damage_dealt"];
                            $rPct = ($totalExpDamage > 0) ? round(($rDmg / $totalExpDamage) * 100, 1) : (($totalExpAtk > 0) ? round(($rAtk / $totalExpAtk) * 100, 1) : 0);
                            $rTribe = (int)$mRow["tribe"];
                            $rTribeName = $tribeNames[$rTribe] ?? "Suku " . $rTribe;
                            $rTribeColor = $tribeColors[$rTribe] ?? "#94a3b8";
                            $isViewer = ($rUid === (int)($session->uid ?? 0) || $rUid === $noticeUid);
                        ?>
                        <tr style="border-bottom: 1px solid rgba(51, 65, 85, 0.4); <?php if ($isViewer) echo "background: rgba(56, 189, 248, 0.08);"; ?>">
                            <td style="padding: 6px 8px; font-weight: bold;">
                                <a href="spieler.php?uid=<?php echo $rUid; ?>" style="color: <?php echo $isViewer ? "#38bdf8" : "#f1f5f9"; ?>; text-decoration: none;">
                                    <?php echo htmlspecialchars($mRow["username"] ?? ("User #" . $rUid)); ?>
                                </a>
                                <?php if ($rUid === $leaderUid) { ?>
                                    <span style="color: #fbbf24; font-size: 10px;" title="Rally Leader">👑</span>
                                <?php } ?>
                            </td>
                            <td style="padding: 6px 8px;">
                                <span style="color: <?php echo $rTribeColor; ?>; font-size: 11px;">
                                    <?php echo htmlspecialchars($rTribeName); ?>
                                </span>
                            </td>
                            <td style="padding: 6px 8px; text-align: right; color: #fbbf24; font-weight: bold;">
                                <?php echo number_format($rAtk); ?>
                            </td>
                            <td style="padding: 6px 8px; text-align: right; color: #38bdf8; font-weight: bold;">
                                <?php echo number_format($rDmg); ?>
                            </td>
                            <td style="padding: 6px 8px;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <div style="background: rgba(51, 65, 85, 0.8); border-radius: 999px; height: 6px; flex-grow: 1; overflow: hidden;">
                                        <div style="background: linear-gradient(90deg, #38bdf8, #4ade80); height: 100%; width: <?php echo min(100, max(0, $rPct)); ?>%;"></div>
                                    </div>
                                    <span style="font-size: 11px; font-weight: bold; color: #4ade80; min-width: 36px; text-align: right;">
                                        <?php echo $rPct; ?>%
                                    </span>
                                </div>
                            </td>
                            <td style="padding: 6px 8px; text-align: right; font-size: 11px; color: #94a3b8;">
                                <span style="color: #38bdf8;" title="Selamat Pulang">🛡️ Pulang Aman</span>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

        <!-- SECTION 3: KARTU TOTAL HADIAH PRIBADI PENERIMA LAPORAN -->
        <?php if ($expAction === "victory") { ?>
        <div style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.85), rgba(15, 23, 42, 0.95)); border: 1px solid #6366f1; border-radius: 8px; padding: 14px; margin-top: 12px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid rgba(99, 102, 241, 0.3); padding-bottom: 8px;">
                <div style="font-weight: bold; color: #c7d2fe; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                    <span>🎁 Total Bagian Hadiah &amp; Rampasan Anda:</span>
                </div>
                <span style="font-size: 11px; color: #a5b4fc;">Langsung ditambahkan ke desa &amp; akun</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px;">
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    🌲 Kayu: <span style="color: #4ade80;">+<?php echo number_format($expWood); ?></span>
                </div>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    🧱 Liat: <span style="color: #fb923c;">+<?php echo number_format($expClay); ?></span>
                </div>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    ⛏️ Besi: <span style="color: #94a3b8;">+<?php echo number_format($expIron); ?></span>
                </div>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    🌾 Gandum: <span style="color: #facc15;">+<?php echo number_format($expCrop); ?></span>
                </div>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    🪙 Silver: <span style="color: #38bdf8;">+<?php echo number_format($expSilver); ?></span>
                </div>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    🏛️ CP: <span style="color: #c084fc;">+<?php echo number_format($expCp); ?></span>
                </div>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid rgba(51, 65, 85, 0.5);">
                    ⭐ Hero EXP: <span style="color: #e879f9;">+<?php echo number_format($expExp); ?></span>
                </div>
                <?php if ($expEssence > 0) { ?>
                <div style="background: rgba(15, 23, 42, 0.8); padding: 8px; border-radius: 6px; font-size: 12px; font-weight: bold; color: #f1f5f9; border: 1px solid #ec4899;">
                    💎 Essence: <span style="color: #f472b6;">+<?php echo number_format($expEssence); ?></span>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

    </div>
<?php } else { ?>
    <!-- Fallback / Plain Text / Bandit Victory & World Boss Display -->
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="font-size: 14px; line-height: 1.7;">
            <?php echo nl2br(htmlspecialchars($msgText, ENT_QUOTES, 'UTF-8')); ?>
        </div>
    </div>
<?php } ?>

</td></tr>
</tbody>
</table>
