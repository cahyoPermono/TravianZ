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
