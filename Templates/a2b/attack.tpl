<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : attack.tpl                               				   ##
##  Type           : Rally Point Attack / Raid Confirmation   				   ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  URLs:          : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

$ckey = $generator->generateRandStr(6);

// --- 1. Normalizare trupe t1..t11 (păstrăm exact logica originală) ---
$t = [];
for ($i = 1; $i <= 11; $i++) {
    $key = 't' . $i;
    $t[$i] = (isset($process[$key]) && $process[$key] !== '') ? $process[$key] : 0;
}
// Hero is strictly at most 1 unit in an army
$t[11] = !empty($t[11]) ? min(1, max(0, (int)$t[11])) : 0;

// --- 2. Detectare scout / hero ---
// Slotul cercetasului per trib: Gali = 3, Huni/Vikingi = 2, restul = 4
$scoutSlots = [1 => 4, 2 => 4, 3 => 3, 4 => 4, 5 => 4, 6 => 2, 7 => 4, 8 => 4, 9 => 2, 10 => 4];
$villageTribe = (!empty($village->tribe) && $village->tribe > 0) ? (int)$village->tribe : ((!empty($session->tribe) && $session->tribe > 0) ? (int)$session->tribe : 1);
$scoutSlot = isset($scoutSlots[$villageTribe]) ? $scoutSlots[$villageTribe] : 4;
$scout = 0;
if (!empty($t[$scoutSlot])) { $scout = 1; }

$showhero = !empty($t[11]) ? 1 : 0;

// --- 3. Total unități fără cercetași (pentru forțarea tipului de atac) ---
$totalunits = 0;
for ($i = 1; $i <= 11; $i++) {
    $isScout = ($i == $scoutSlot);
    if (!$isScout) {
        $totalunits += (!empty($process['t'.$i]) ? (int)$process['t'.$i] : 0);
    }
}

// Dacă sunt doar cercetași și nu e întărire, forțează c=1 (Scout)
if ($scout == 1 && $totalunits == 0 && $process['c'] != 2) {
    $process['c'] = 1;
}

// --- 4. Salvare în a2b (aceeași ordine de parametri) ---
$id = $database->addA2b(
    $ckey, time(), $process['0'],
    $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10], $t[11],
    $process['c']
);

$actionTypes = [1 => 'Scout', 2 => 'Reinforcement', 3 => 'Normal attack', 4 => 'Raid'];
$actionType = $actionTypes[(int)$process['c']] ?? 'Normal attack';

$uid = $session->uid;
$tribe = (!empty($village->tribe) && $village->tribe > 0) ? (int)$village->tribe : ((!empty($session->tribe) && $session->tribe > 0) ? (int)$session->tribe : 1);
$start = ($tribe - 1) * 10 + 1;
$end = $tribe * 10;

$hasHero = !empty($process['t11']);
$colspan = $hasHero ? 11 : 10;
$kata = !empty($process['t8']);
?>
<h1><?php echo $actionType . " to " . $process[1]; ?></h1>
<?php if (isset($form) && $form->getError('error')): ?>
    <div style="margin: 10px 0; padding: 10px 14px; background: #fee2e2; border: 1.5px solid #ef4444; border-radius: 6px; font-size: 12px; color: #991b1b; font-weight: bold;">
        ⚠️ <?php echo $form->getError('error'); ?>
    </div>
<?php endif; ?>
<form method="post" action="a2b.php">
    <table id="short_info" cellpadding="1" cellspacing="1">
        <tbody>
            <tr>
                <th><?php echo TZ_DESTINATION; ?></th>
                <td><a href="karte.php?d=<?php echo $process[0]; ?>&c=<?php echo $generator->getMapCheck($process[0]); ?>"><?php echo $process[1]; ?> (<?php echo $coor['x']; ?>|<?php echo $coor['y']; ?>)</a></td>
            </tr>
            <tr>
                <th><?php echo TZ_OWNER; ?></th>
                <td><a href="spieler.php?uid=<?php echo $process['2']; ?>"><?php echo $database->getUserField($process['2'], 'username', 0); ?></a></td>
            </tr>
        </tbody>
    </table>

    <table class="troop_details" cellpadding="1" cellspacing="1">
        <thead>
            <tr>
                <td><?php echo $process[1]; ?></td>
                <td colspan="<?php echo $colspan; ?>"><?php echo $actionType . " to " . $process['1']; ?></td>
            </tr>
        </thead>
        <tbody class="units">
            <tr>
                <td></td>
                <?php
                for ($i = $start; $i <= $end; $i++) {
                    echo '<td><img src="img/x.gif" class="unit u' . $i . '" title="' . $technology->getUnitName($i) . '" alt="' . $technology->getUnitName($i) . '" /></td>';
                }
                if ($hasHero) {
                    echo '<td><img src="img/x.gif" class="unit uhero" title="'.U0.'" alt="'.U0.'" /></td>';
                }
                ?>
            </tr>
            <tr>
                <th><?php echo TROOPS; ?></th>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <td <?php if (!isset($process['t'.$i]) || $process['t'.$i] == '') { echo 'class="none">0'; } else { echo '>' . $process['t'.$i]; } ?></td>
                <?php endfor; ?>
                <?php if ($hasHero) { echo '<td>' . $process['t11'] . '</td>'; } ?>
            </tr>
        </tbody>

        <?php if ($process['c'] == 1): ?>
        <tbody class="options">
            <tr>
                <th><?php echo OPTION; ?></th>
                <td colspan="<?php echo $colspan; ?>">
                    <input class="radio" name="spy" value="1" checked="checked" type="radio"><?php echo TZ_SCOUT_RESOURCES_AND_TROOPS; ?><br>
                    <input class="radio" name="spy" value="2" type="radio"><?php echo TZ_SCOUT_DEFENCES_AND_TROOPS; ?>
                </td>
            </tr>
        </tbody>
        <?php endif; ?>

        <?php if ($kata && $process['c'] != 2): ?>
            <?php if ($process['c'] == 3): ?>
            <tbody class="cata">
                <tr>
                    <th><?php echo TZ_DESTINATION; ?></th>
                    <td colspan="<?php echo $colspan; ?>">
                        <select name="ctar1" class="dropdown">
                            <option value="0"><?php echo RANDOM; ?></option>
                            <?php if ($building->getTypeLevel(16) >= 5): ?>
                            <optgroup label="Resources">
                                <option value="1"><?php echo WOODCUTTER; ?></option>
                                <option value="2"><?php echo CLAYPIT; ?></option>
                                <option value="3"><?php echo IRONMINE; ?></option>
                                <option value="4"><?php echo CROPLAND; ?></option>
                                <option value="5"><?php echo SAWMILL; ?></option>
                                <option value="6"><?php echo BRICKYARD; ?></option>
                                <option value="7"><?php echo IRONFOUNDRY; ?></option>
                                <option value="8"><?php echo GRAINMILL; ?></option>
                                <option value="9"><?php echo BAKERY; ?></option>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($building->getTypeLevel(16) >= 3): ?>
                            <optgroup label="Infrastructure">
                                <option value="10"><?php echo WAREHOUSE; ?></option>
                                <option value="11"><?php echo GRANARY; ?></option>
                                <?php if ($building->getTypeLevel(16) >= 10): ?>
                                <option value="15"><?php echo MAINBUILDING; ?></option>
                                <option value="17"><?php echo MARKETPLACE; ?></option>
                                <option value="18"><?php echo EMBASSY; ?></option>
                                <option value="24"><?php echo TZ_TOWNHALL; ?></option>
                                <option value="25"><?php echo RESIDENCE; ?></option>
                                <option value="26"><?php echo PALACE; ?></option>
                                <option value="27"><?php echo TREASURY; ?></option>
                                <option value="28"><?php echo TRADEOFFICE; ?></option>
                                <option value="35"><?php echo BREWERY; ?></option>
                                <?php endif; ?>
                                <option value="38"><?php echo GREATWAREHOUSE; ?></option>
                                <option value="39"><?php echo GREATGRANARY; ?></option>
                                <option value="40"><?php echo WONDER; ?></option>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($building->getTypeLevel(16) >= 10): ?>
                            <optgroup label="Military">
                                <option value="12"><?php echo BLACKSMITH; ?></option>
                                <option value="13"><?php echo ARMOURY; ?></option>
                                <option value="14"><?php echo TOURNAMENTSQUARE; ?></option>
                                <option value="16"><?php echo RALLYPOINT; ?></option>
                                <option value="19"><?php echo BARRACKS; ?></option>
                                <option value="20"><?php echo STABLE; ?></option>
                                <option value="21"><?php echo WORKSHOP; ?></option>
                                <option value="22"><?php echo ACADEMY; ?></option>
                                <option value="29"><?php echo GREATBARRACKS; ?></option>
                                <option value="30"><?php echo GREATSTABLE; ?></option>
                                <option value="37"><?php echo HEROSMANSION; ?></option>
                                <option value="44"><?php echo COMMANDCENTER; ?></option>
                                <option value="45"><?php echo WATERWORKS; ?></option>
                                <option value="46"><?php echo HOSPITAL; ?></option>
                                <option value="48"><?php echo BIGHOSPITAL; ?></option>
                                <option value="49"><?php echo GREATWORKSHOP; ?></option>
                                <option value="51"><?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?></option>
                            </optgroup>
                            <?php endif; ?>
                        </select>

                        <?php if ($building->getTypeLevel(16) == 20 && $process['t8'] >= 20): ?>
                        <select name="ctar2" class="dropdown">
                            <option value="0">-</option>
                            <option value="99"><?php echo RANDOM; ?></option>
                            <?php if ($building->getTypeLevel(16) >= 5): ?>
                            <optgroup label="Resources">
                                <option value="1"><?php echo WOODCUTTER; ?></option><option value="2"><?php echo CLAYPIT; ?></option><option value="3"><?php echo IRONMINE; ?></option>
                                <option value="4"><?php echo CROPLAND; ?></option><option value="5"><?php echo SAWMILL; ?></option><option value="6"><?php echo BRICKYARD; ?></option>
                                <option value="7"><?php echo IRONFOUNDRY; ?></option><option value="8"><?php echo GRAINMILL; ?></option><option value="9"><?php echo BAKERY; ?></option>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($building->getTypeLevel(16) >= 3): ?>
                            <optgroup label="Infrastructure">
                                <option value="10"><?php echo WAREHOUSE; ?></option><option value="11"><?php echo GRANARY; ?></option>
                                <?php if ($building->getTypeLevel(16) >= 10): ?>
                                <option value="15"><?php echo MAINBUILDING; ?></option><option value="17"><?php echo MARKETPLACE; ?></option><option value="18"><?php echo EMBASSY; ?></option>
                                <option value="24"><?php echo TZ_TOWNHALL; ?></option><option value="25"><?php echo RESIDENCE; ?></option><option value="26"><?php echo PALACE; ?></option>
                                <option value="27"><?php echo TREASURY; ?></option><option value="28"><?php echo TRADEOFFICE; ?></option><option value="35"><?php echo BREWERY; ?></option>
                                <?php endif; ?>
                                <option value="38"><?php echo GREATWAREHOUSE; ?></option><option value="39"><?php echo GREATGRANARY; ?></option><option value="40"><?php echo WONDER; ?></option>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($building->getTypeLevel(16) >= 10): ?>
                            <optgroup label="Military">
                                <option value="12"><?php echo BLACKSMITH; ?></option><option value="13"><?php echo ARMOURY; ?></option><option value="14"><?php echo TOURNAMENTSQUARE; ?></option>
                                <option value="16"><?php echo RALLYPOINT; ?></option><option value="19"><?php echo BARRACKS; ?></option><option value="20"><?php echo STABLE; ?></option>
                                <option value="21"><?php echo WORKSHOP; ?></option><option value="22"><?php echo ACADEMY; ?></option><option value="29"><?php echo GREATBARRACKS; ?></option>
                                <option value="30"><?php echo GREATSTABLE; ?></option><option value="37"><?php echo HEROSMANSION; ?></option>
                                <option value="44"><?php echo COMMANDCENTER; ?></option><option value="45"><?php echo WATERWORKS; ?></option><option value="46"><?php echo HOSPITAL; ?></option>
                                <option value="48"><?php echo BIGHOSPITAL; ?></option><option value="49"><?php echo GREATWORKSHOP; ?></option>
                                <option value="51"><?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?></option>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <?php endif; ?>
                        <span class="info"><?php echo TZ_WILL_BE_ATTACKED_BY_CATAPULT_S; ?></span>
                    </td>
                </tr>
            </tbody>
            <?php elseif ($process['c'] == '4'): ?>
            <tbody class="infos">
                <tr>
                    <th><?php echo TZ_DESTINATION; ?></th>
                    <td colspan="<?php echo $colspan; ?>"><?php echo TZ_WARNING_CATAPULT_WILL; ?> <b>ONLY</b> <?php echo TZ_SHOOT_WITH_A_NORMAL_ATTACK_THEY_DO; ?></td>
                </tr>
            </tbody>
            <?php endif; ?>
        <?php endif; ?>

        <tbody class="infos">
            <tr>
                <th><?php echo TZ_ARRIVED; ?></th>
                <?php
                $troopsTime = $units->getWalkingTroopsTime($village->wid, $process[0], $session->uid, $tribe, $process, 1, 't');
                $time = $database->getArtifactsValueInfluence($session->uid, $village->wid, 2, $troopsTime);
                ?>
                <td colspan="<?php echo $colspan; ?>">
                    <div class="in"><?php echo P_IN; ?> <?php echo $generator->getTimeFormat($time); ?></div>
                    <div class="at"><?php echo AT; ?> <span id="tp2"><?php echo $generator->procMtime(date('U') + $time, 9) ?></span><span> <?php echo HOURS; ?></span></div>
                </td>
            </tr>
        </tbody>

        <?php
        if (!class_exists('BlackMarket')) {
            @include_once __DIR__ . '/../../GameEngine/BlackMarket.php';
        }
        $userSilver = class_exists('BlackMarket') ? BlackMarket::getSilver((int)$session->uid) : 0;
        $scheduleSilverCost = 500;
        $canAffordSchedule = ($userSilver >= $scheduleSilverCost);

        $serverNow = time();
        $minArrivalTs = $serverNow + (int)$time;
        $minArrivalDate = date('Y-m-d', $minArrivalTs);
        $minArrivalTime = date('H:i:s', $minArrivalTs);
        $minDurH = (int)floor($time / 3600);
        $minDurM = (int)floor(($time % 3600) / 60);
        $minDurS = (int)($time % 60);
        ?>
        <tbody class="infos custom_schedule_tbody" style="border-top: 1px dashed #cbd5e1;">
            <tr>
                <th style="vertical-align: top; padding-top: 8px;">
                    🎯 Jadwal Tiba:<br>
                    <span style="font-size: 10px; font-weight: normal; color: #64748b;">(Opsi Khusus)</span>
                </th>
                <td colspan="<?php echo $colspan; ?>" style="padding: 8px 10px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <label style="display: inline-flex; align-items: center; cursor: <?php echo $canAffordSchedule ? 'pointer' : 'not-allowed'; ?>; font-weight: bold; font-size: 12.5px; color: #1e3a8a;">
                            <input type="checkbox" name="custom_arrival_enable" id="custom_arrival_enable" value="1" <?php if (!$canAffordSchedule) echo 'disabled="disabled"'; ?> onchange="toggleCustomSchedule(this.checked)" style="margin-right: 6px; cursor: pointer;" />
                            Atur Jam Tiba Pasukan (Schedule Attack Arrival)
                        </label>
                        <span style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 4px;">
                            🪙 Biaya: <?php echo number_format($scheduleSilverCost); ?> Silver
                        </span>
                    </div>

                    <div style="margin-top: 4px; font-size: 11px;">
                        Saldo Silver Anda: <b style="color: <?php echo $canAffordSchedule ? '#15803d' : '#dc2626'; ?>;"><?php echo number_format($userSilver); ?> Silver</b>
                        <?php if (!$canAffordSchedule): ?>
                            <span style="color: #dc2626; margin-left: 6px;">(⚠️ Silver tidak mencukupi, butuh 500 Silver)</span>
                        <?php endif; ?>
                    </div>

                    <div id="custom_schedule_panel" style="display: none; margin-top: 10px; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <div style="font-size: 11.5px; color: #475569; margin-bottom: 8px; line-height: 1.4;">
                            ℹ️ Pasukan akan <b>langsung diberangkatkan sekarang</b>, dan kecepatan jalan diperlambat agar tiba tepat pada jadwal yang Anda inginkan.<br>
                            ⚠️ <b>Aturan:</b> Waktu tiba <b>harus melebihi</b> waktu tempuh minimal (tidak bisa tiba lebih cepat dari waktu normal).
                        </div>

                        <div style="padding: 6px 10px; background: #e0f2fe; border-left: 3px solid #0284c7; border-radius: 2px; font-size: 11.5px; color: #0369a1; margin-bottom: 10px;">
                            ⏱️ <b>Waktu Tempuh Minimal:</b> <?php echo $generator->getTimeFormat($time); ?> &nbsp;|&nbsp;
                            <b>Paling Cepat Tiba:</b> <?php echo date('d/m/Y H:i:s', $minArrivalTs); ?>
                        </div>

                        <!-- Opsi 1: Tentukan Jam Tiba -->
                        <div style="margin-bottom: 8px;">
                            <b style="font-size: 12px; color: #334155;">1. Tentukan Waktu Tiba (Waktu Server):</b><br>
                            <div style="display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; flex-wrap: wrap;">
                                <span>Tanggal:</span>
                                <input type="date" id="cust_date" name="custom_arrival_date" value="<?php echo $minArrivalDate; ?>" min="<?php echo date('Y-m-d', $serverNow); ?>" style="padding: 3px 6px; border: 1px solid #94a3b8; border-radius: 4px;" onchange="syncFromClock()" />
                                <span>Jam (HH:MM:SS):</span>
                                <input type="time" step="1" id="cust_time" name="custom_arrival_time" value="<?php echo $minArrivalTime; ?>" style="padding: 3px 6px; border: 1px solid #94a3b8; border-radius: 4px;" onchange="syncFromClock()" />
                            </div>
                        </div>

                        <!-- Opsi 2: Tentukan Total Durasi -->
                        <div style="margin-bottom: 10px;">
                            <b style="font-size: 12px; color: #334155;">2. Atau Tentukan Total Durasi Perjalanan Pasukan:</b><br>
                            <div style="display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; flex-wrap: wrap;">
                                <input type="number" id="cust_dur_h" name="custom_duration_h" value="<?php echo $minDurH; ?>" min="0" max="336" style="width: 55px; padding: 3px 5px; border: 1px solid #94a3b8; border-radius: 4px; text-align: center;" oninput="syncFromDuration()" /> Jam
                                <input type="number" id="cust_dur_m" name="custom_duration_m" value="<?php echo $minDurM; ?>" min="0" max="59" style="width: 45px; padding: 3px 5px; border: 1px solid #94a3b8; border-radius: 4px; text-align: center;" oninput="syncFromDuration()" /> Menit
                                <input type="number" id="cust_dur_s" name="custom_duration_s" value="<?php echo $minDurS; ?>" min="0" max="59" style="width: 45px; padding: 3px 5px; border: 1px solid #94a3b8; border-radius: 4px; text-align: center;" oninput="syncFromDuration()" /> Detik
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div style="margin-bottom: 10px; font-size: 11px;">
                            <b style="color: #475569;">Quick Tambah Waktu:</b>
                            <button type="button" onclick="addDelayMinutes(15)" style="cursor: pointer; padding: 2px 7px; margin-left: 3px; border: 1px solid #cbd5e1; background: #fff; border-radius: 3px;">+15 mnt</button>
                            <button type="button" onclick="addDelayMinutes(30)" style="cursor: pointer; padding: 2px 7px; margin-left: 3px; border: 1px solid #cbd5e1; background: #fff; border-radius: 3px;">+30 mnt</button>
                            <button type="button" onclick="addDelayMinutes(60)" style="cursor: pointer; padding: 2px 7px; margin-left: 3px; border: 1px solid #cbd5e1; background: #fff; border-radius: 3px;">+1 jam</button>
                            <button type="button" onclick="addDelayMinutes(120)" style="cursor: pointer; padding: 2px 7px; margin-left: 3px; border: 1px solid #cbd5e1; background: #fff; border-radius: 3px;">+2 jam</button>
                            <button type="button" onclick="roundNextHour()" style="cursor: pointer; padding: 2px 7px; margin-left: 3px; border: 1px solid #cbd5e1; background: #fff; border-radius: 3px;">Bulatkan Jam Berikutnya</button>
                        </div>

                        <!-- Live Summary Box -->
                        <div id="cust_feedback_box" style="padding: 8px 10px; border-radius: 4px; font-size: 11.5px; background: #ffffff; border: 1px solid #e2e8f0;">
                            <div><b>🎯 Rencana Kedatangan:</b> <span id="cust_calc_arrival" style="font-weight: bold; color: #1e3a8a;"><?php echo date('d/m/Y H:i:s', $minArrivalTs); ?></span></div>
                            <div style="margin-top: 2px; color: #64748b;">
                                Total Durasi: <b id="cust_calc_dur"><?php echo $generator->getTimeFormat($time); ?></b> &nbsp;|&nbsp;
                                Perlambatan: +<span id="cust_calc_delay">0:00:00</span>
                            </div>
                            <div id="cust_error_alert" style="display: none; margin-top: 5px; color: #dc2626; font-weight: bold;">
                                ❌ Waktu tiba tidak boleh lebih cepat dari waktu tempuh minimal (<?php echo date('H:i:s', $minArrivalTs); ?>)!
                            </div>
                            <div id="cust_ok_alert" style="margin-top: 5px; color: #15803d; font-weight: bold;">
                                ✅ Jadwal tiba valid. Siap diberangkatkan!
                            </div>
                        </div>

                        <input type="hidden" id="custom_arrival_ts" name="custom_arrival_ts" value="<?php echo $minArrivalTs; ?>" />
                        <input type="hidden" id="custom_duration_seconds" name="custom_duration_seconds" value="<?php echo $time; ?>" />
                        <input type="hidden" id="custom_arrival_mode" name="custom_arrival_mode" value="clock" />
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <script type="text/javascript">
    var SCHEDULE_CFG = {
        serverNow: <?php echo (int)$serverNow; ?>,
        clientNow: Math.floor(Date.now() / 1000),
        minDuration: <?php echo (int)$time; ?>,
        minArrivalTs: <?php echo (int)$minArrivalTs; ?>,
        userSilver: <?php echo (int)$userSilver; ?>,
        costSilver: <?php echo (int)$scheduleSilverCost; ?>
    };

    function formatDuration(sec) {
        if (sec < 0) sec = 0;
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function toggleCustomSchedule(enabled) {
        var panel = document.getElementById('custom_schedule_panel');
        if (!panel) return;
        panel.style.display = enabled ? 'block' : 'none';
        if (enabled) {
            syncFromClock();
        }
    }

    function updateScheduleFeedback(targetTs, durationSec) {
        var minTs = SCHEDULE_CFG.minArrivalTs;
        var minDur = SCHEDULE_CFG.minDuration;
        var isValid = (targetTs >= minTs);

        var delaySec = Math.max(0, durationSec - minDur);

        var dt = new Date(targetTs * 1000);
        var pad = function(n) { return (n < 10 ? '0' : '') + n; };
        var dateFormatted = pad(dt.getDate()) + '/' + pad(dt.getMonth() + 1) + '/' + dt.getFullYear() + ' ' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());

        var arrivalEl = document.getElementById('cust_calc_arrival');
        var durEl = document.getElementById('cust_calc_dur');
        var delayEl = document.getElementById('cust_calc_delay');
        var errEl = document.getElementById('cust_error_alert');
        var okEl = document.getElementById('cust_ok_alert');

        if (arrivalEl) arrivalEl.innerText = dateFormatted;
        if (durEl) durEl.innerText = formatDuration(durationSec);
        if (delayEl) delayEl.innerText = formatDuration(delaySec);

        if (isValid) {
            if (errEl) errEl.style.display = 'none';
            if (okEl) okEl.style.display = 'block';
        } else {
            if (errEl) errEl.style.display = 'block';
            if (okEl) okEl.style.display = 'none';
        }

        var tsInput = document.getElementById('custom_arrival_ts');
        var durInput = document.getElementById('custom_duration_seconds');
        if (tsInput) tsInput.value = targetTs;
        if (durInput) durInput.value = durationSec;
    }

    function syncFromClock() {
        var dateEl = document.getElementById('cust_date');
        var timeEl = document.getElementById('cust_time');
        if (!dateEl || !timeEl) return;
        var dateVal = dateEl.value;
        var timeVal = timeEl.value;
        if (!dateVal || !timeVal) return;

        if (timeVal.split(':').length === 2) {
            timeVal += ':00';
        }

        var parts = dateVal.split('-');
        var tParts = timeVal.split(':');
        if (parts.length !== 3 || tParts.length < 2) return;

        var targetDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), parseInt(tParts[0], 10), parseInt(tParts[1], 10), parseInt(tParts[2] || 0, 10));
        var targetTs = Math.floor(targetDate.getTime() / 1000);
        if (isNaN(targetTs)) return;

        var durationSec = targetTs - SCHEDULE_CFG.serverNow;
        if (durationSec < 0) durationSec = 0;

        var h = Math.floor(durationSec / 3600);
        var m = Math.floor((durationSec % 3600) / 60);
        var s = durationSec % 60;

        document.getElementById('cust_dur_h').value = h;
        document.getElementById('cust_dur_m').value = m;
        document.getElementById('cust_dur_s').value = s;
        document.getElementById('custom_arrival_mode').value = 'clock';

        updateScheduleFeedback(targetTs, durationSec);
    }

    function syncFromDuration() {
        var h = parseInt(document.getElementById('cust_dur_h').value, 10) || 0;
        var m = parseInt(document.getElementById('cust_dur_m').value, 10) || 0;
        var s = parseInt(document.getElementById('cust_dur_s').value, 10) || 0;

        var durationSec = (h * 3600) + (m * 60) + s;
        var targetTs = SCHEDULE_CFG.serverNow + durationSec;

        var dt = new Date(targetTs * 1000);
        var pad = function(n) { return (n < 10 ? '0' : '') + n; };
        var ymd = dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate());
        var hms = pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds());

        document.getElementById('cust_date').value = ymd;
        document.getElementById('cust_time').value = hms;
        document.getElementById('custom_arrival_mode').value = 'duration';

        updateScheduleFeedback(targetTs, durationSec);
    }

    function addDelayMinutes(minutes) {
        var durSec = parseInt(document.getElementById('custom_duration_seconds').value, 10) || SCHEDULE_CFG.minDuration;
        if (durSec < SCHEDULE_CFG.minDuration) durSec = SCHEDULE_CFG.minDuration;
        durSec += (minutes * 60);

        var h = Math.floor(durSec / 3600);
        var m = Math.floor((durSec % 3600) / 60);
        var s = durSec % 60;

        document.getElementById('cust_dur_h').value = h;
        document.getElementById('cust_dur_m').value = m;
        document.getElementById('cust_dur_s').value = s;

        syncFromDuration();
    }

    function roundNextHour() {
        var currentTs = parseInt(document.getElementById('custom_arrival_ts').value, 10) || SCHEDULE_CFG.minArrivalTs;
        if (currentTs < SCHEDULE_CFG.minArrivalTs) currentTs = SCHEDULE_CFG.minArrivalTs;
        var nextHourTs = Math.ceil((currentTs + 1) / 3600) * 3600;
        var durSec = nextHourTs - SCHEDULE_CFG.serverNow;
        if (durSec < SCHEDULE_CFG.minDuration) {
            nextHourTs += 3600;
            durSec = nextHourTs - SCHEDULE_CFG.serverNow;
        }

        var h = Math.floor(durSec / 3600);
        var m = Math.floor((durSec % 3600) / 60);
        var s = durSec % 60;

        document.getElementById('cust_dur_h').value = h;
        document.getElementById('cust_dur_m').value = m;
        document.getElementById('cust_dur_s').value = s;

        syncFromDuration();
    }

    function validateAttackSchedule() {
        var chk = document.getElementById('custom_arrival_enable');
        if (!chk || !chk.checked) {
            return true;
        }

        if (SCHEDULE_CFG.userSilver < SCHEDULE_CFG.costSilver) {
            alert('Koin Silver Anda tidak mencukupi untuk menggunakan opsi ini.\nDibutuhkan: ' + SCHEDULE_CFG.costSilver + ' Silver\nSaldo Anda: ' + SCHEDULE_CFG.userSilver + ' Silver');
            return false;
        }

        var targetTs = parseInt(document.getElementById('custom_arrival_ts').value, 10) || 0;
        if (targetTs < SCHEDULE_CFG.minArrivalTs) {
            var minDt = new Date(SCHEDULE_CFG.minArrivalTs * 1000);
            var pad = function(n) { return (n < 10 ? '0' : '') + n; };
            var minTimeStr = pad(minDt.getHours()) + ':' + pad(minDt.getMinutes()) + ':' + pad(minDt.getSeconds());
            alert('Waktu tiba tidak boleh lebih cepat dari waktu tempuh minimal!\nMinimal waktu tiba: ' + minTimeStr + '\nSilakan atur waktu tiba yang melebihi batas minimal.');
            return false;
        }

        return true;
    }

    function handleAttackSubmit(btn) {
        if (!btn || btn.disabled) return false;
        if (!validateAttackSchedule()) {
            return false;
        }
        btn.disabled = true;
        var form = btn.form || document.querySelector('form[action="a2b.php"]') || document.getElementsByTagName('form')[0];
        if (form) {
            form.submit();
        }
        return true;
    }
    </script>

    <input name="timestamp" value="<?php echo time(); ?>" type="hidden">
    <input name="timestamp_checksum" value="<?php echo $ckey; ?>" type="hidden">
    <input name="ckey" value="<?php echo $id; ?>" type="hidden">
    <input name="id" value="39" type="hidden">
    <input name="a" value="533374" type="hidden">
    <input name="c" value="3" type="hidden">

    <?php
    if (!class_exists('BanditCamp')) {
        @include_once __DIR__ . '/../../GameEngine/BanditCamp.php';
    }
    $isBanditTarget = (!empty($process[0]) && class_exists('BanditCamp') && BanditCamp::isBanditCamp((int)$process[0]));
    if ($isBanditTarget && (int)$process['c'] === 3):
    ?>
    <div style="margin: 12px 0; padding: 10px 14px; background: #fee2e2; border: 1.5px solid #ef4444; border-radius: 6px; font-size: 12px; color: #991b1b; line-height: 1.5;">
        <strong>⚠️ PERHATIAN: Anda memilih SERANGAN NORMAL (Normal Attack) ke Sarang Bandit!</strong><br />
        Pertempuran normal adalah <em>fight to the death</em> sampai salah satu pihak gugur 100%. Jika kekuatan serang pasukan Anda di bawah total pertahanan penjaga bandit, seluruh pasukan Anda akan <strong>WIPEOUT (mati 100%)</strong> dan Hero Anda akan gugur!<br />
        💡 <strong>Sangat Disarankan:</strong> Batalkan dan gunakan mode <strong>Serbu / Jarah (Raid)</strong> untuk pertempuran aman dengan kerugian pasukan minimal.
    </div>
    <?php endif; ?>

    <?php
    if ($database->hasBeginnerProtection($village->wid) == 1 && $database->hasBeginnerProtection($process['0']) == 0) {
        echo '<span style="color: #DD0000"><b>Caution:</b> Attacking a player will lose the protection!</span>';
    }
    if ($database->hasBeginnerProtection($process['0']) == 1) {
        echo '<b>User presently has beginners protection</b>';
    } else {
    ?>
        <p class="btn"><input value="ok" name="s1" id="btn_ok" class="dynamic_img " src="img/x.gif" alt="OK" type="image" onclick="return handleAttackSubmit(this);" onLoad="this.disabled=false;"></p>
    <?php } ?>
</form>
</div>
