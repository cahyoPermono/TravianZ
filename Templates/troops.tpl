<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       troops.tpl                                                  ##
##  Developed by:  Dzoki                                                       ##
##  Refactored by: Shadow Incremental Refactor 			                       ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##                                                                             ##
##  Incremental Refactor Notes:                                                ##
##  - Preserved original functionality                                         ##
##  - Reduced repeated method calls for unit names                             ##
##  - Added small safety guards for legacy PHP                                 ##
##  - Improved readability                                                     ##
##                                                                             ##
#################################################################################
?>

<table id="troops" cellpadding="1" cellspacing="1">

<thead>
<tr>
    <th colspan="3">
        <?php echo TROOPS; ?>
    </th>
</tr>
</thead>

<tbody>

<?php
/**
 * ---------------------------------------------------------
 * Load all village troops
 * ---------------------------------------------------------
 */
$troops = $technology->getAllUnits($village->wid, true, 1);

/**
 * Cache unit list to avoid repeated calls
 */
$unitNameCache = array();

$troopsPresent = false;

/**
 * ---------------------------------------------------------
 * Loop all possible units (u1-u90, tribes 1-9)
 * ---------------------------------------------------------
 */
for ($i = 1; $i <= 100; $i++) {

    $unitKey = 'u' . $i;

    if (!empty($troops[$unitKey])) {

        /**
         * Cache unit name (avoid repeated calls)
         */
        if (!isset($unitNameCache[$i])) {
            $unitNameCache[$i] = $technology->getUnitName($i);
        }

        $unitName = $unitNameCache[$i];

        echo '
        <tr>
            <td class="ico">
                <a href="build.php?id=39">
                    <img
                        class="unit u' . $i . '"
                        src="img/x.gif"
                        alt="' . $unitName . '"
                        title="' . $unitName . '"
                    />
                </a>
            </td>

            <td class="num">
                ' . $troops[$unitKey] . '
            </td>

            <td class="un">
                ' . $unitName . '
            </td>
        </tr>';

        $troopsPresent = true;
    }
}

/**
 * ---------------------------------------------------------
 * Hero handling
 * ---------------------------------------------------------
 */
if (!empty($troops['hero'])) {

    echo '
    <tr>
        <td class="ico">
            <a href="build.php?id=39">
                <img
                    class="unit uhero"
                    src="img/x.gif"
                    alt="'.U0.'"
                    title="'.U0.'"
                />
            </a>
        </td>

        <td class="num">
            ' . (int)$troops['hero'] . '
        </td>

        <td class="un">
            ' . U0 . '
        </td>
    </tr>';

    $troopsPresent = true;
}

/**
 * ---------------------------------------------------------
 * Nusantara Astral Spirits (Sanggar Paranormal GID 51)
 * ---------------------------------------------------------
 */
if (class_exists('Paranormal') || file_exists(dirname(__DIR__) . '/GameEngine/Paranormal.php')) {
    if (!class_exists('Paranormal')) {
        require_once dirname(__DIR__) . '/GameEngine/Paranormal.php';
    }
    $spirits = Paranormal::getVillageSpirits($village->wid);
    $pocong = (int)($spirits['pocong'] ?? 0);
    $kunti = (int)($spirits['kuntilanak'] ?? 0);
    $genderuwo = (int)($spirits['genderuwo'] ?? 0);
    $tuyulActive = !empty($spirits['tuyul_until']) && (int)$spirits['tuyul_until'] > time();

    if ($pocong > 0 || $kunti > 0 || $genderuwo > 0 || $tuyulActive) {
        $troopsPresent = true;
        echo '
        <tr class="thead">
            <th colspan="3" style="background:linear-gradient(135deg, #1e293b, #334155); color:#38bdf8; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; padding:4px 6px; border-top:1px solid #475569;">
                <a href="build.php?gid=51" style="color:#38bdf8; text-decoration:none;">👻 Pasukan Gaib (Sanggar)</a>
            </th>
        </tr>';

        if ($pocong > 0) {
            echo '
            <tr>
                <td class="ico">
                    <a href="build.php?gid=51" title="Pocong: 40 Pertahanan Infanteri, 20 Kavaleri">
                        <span style="display:inline-block; width:16px; height:16px; font-size:14px; line-height:16px; text-align:center;">👻</span>
                    </a>
                </td>
                <td class="num">' . $pocong . '</td>
                <td class="un"><a href="build.php?gid=51" style="color:inherit; text-decoration:none;" title="Pocong (40 Def Inf / 20 Def Kav)">Pocong</a></td>
            </tr>';
        }

        if ($kunti > 0) {
            echo '
            <tr>
                <td class="ico">
                    <a href="build.php?gid=51" title="Kuntilanak: 20 Pertahanan Infanteri, 60 Kavaleri">
                        <span style="display:inline-block; width:16px; height:16px; font-size:14px; line-height:16px; text-align:center;">👰‍♀️</span>
                    </a>
                </td>
                <td class="num">' . $kunti . '</td>
                <td class="un"><a href="build.php?gid=51" style="color:inherit; text-decoration:none;" title="Kuntilanak (20 Def Inf / 60 Def Kav)">Kuntilanak</a></td>
            </tr>';
        }

        if ($genderuwo > 0) {
            echo '
            <tr>
                <td class="ico">
                    <a href="build.php?gid=51" title="Genderuwo: 75 Pertahanan Infanteri, 75 Kavaleri">
                        <span style="display:inline-block; width:16px; height:16px; font-size:14px; line-height:16px; text-align:center;">👹</span>
                    </a>
                </td>
                <td class="num">' . $genderuwo . '</td>
                <td class="un"><a href="build.php?gid=51" style="color:inherit; text-decoration:none;" title="Genderuwo (75 Def Inf / 75 Def Kav)">Genderuwo</a></td>
            </tr>';
        }

        if ($tuyulActive) {
            $tuyulSec = (int)$spirits['tuyul_until'] - time();
            $tuyulFmt = class_exists('Generator') && isset($GLOBALS['generator']) ? $GLOBALS['generator']->getTimeFormat($tuyulSec) : gmdate("H:i:s", $tuyulSec);
            echo '
            <tr>
                <td class="ico">
                    <a href="build.php?gid=51" title="Tuyul: +15% Hasil Rampasan / Raid Bounty">
                        <span style="display:inline-block; width:16px; height:16px; font-size:14px; line-height:16px; text-align:center;">🕯️</span>
                    </a>
                </td>
                <td class="num" style="color:#16a34a; font-weight:bold; font-size:10px;">+15%</td>
                <td class="un"><a href="build.php?gid=51" style="color:#16a34a; text-decoration:none;" title="Tuyul Aktif (Sisa: ' . $tuyulFmt . ')">Tuyul (Aktif)</a></td>
            </tr>';
        }
    }
}

/**
 * ---------------------------------------------------------
 * Empty state
 * ---------------------------------------------------------
 */
if (!$troopsPresent) {
    echo '<tr><td>none</td></tr>';
}
?>

</tbody>

</table>
</div>