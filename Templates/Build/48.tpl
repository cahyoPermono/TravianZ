<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : BIGHOSPITAL                                               ##
##  Type           : BUILDING TEMPLATE                                         ##
## --------------------------------------------------------------------------- ##
##  Created by     : Shadow                                                    ##
##  Designed by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  Test Server    : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

global $village, $building, $database, $technology, $generator, $bid48, $id, $loopsame, $doublebuild, $master;

include("next.tpl");

$level = (int)$village->resarray['f'.$id];
$current = $level > 0 ? (int)round($bid48[$level]['attri'] * 100) : 100;
$capacity = $level * 60;

// ==================== RANITI + VINDECARE ====================
$woundedRows = $database->getWounded($village->wid);
$healingRows = $database->getHealing($village->wid);
$hasWounded = false;
$storedWounded = 0;
if (!empty($woundedRows)) {
    for ($wi = 1; $wi <= 100; $wi++) {
        if (!empty($woundedRows['u'.$wi])) {
            $hasWounded = true;
            $storedWounded += (int)$woundedRows['u'.$wi];
        }
    }
}
?>
<div id="build" class="gid48">
    <a href="#" onClick="return Popup(48,4);" class="build_logo">
        <img class="building g48" src="img/x.gif" alt="<?php echo BIGHOSPITAL; ?>" title="<?php echo BIGHOSPITAL;?>" />
    </a>
    <h1><?php echo BIGHOSPITAL;?> <span class="level"><?php echo LEVEL;?> <?php echo $level;?></span></h1>
    <p class="build_desc"><?php echo BIGHOSPITAL_DESC;?></p>

    <table cellpadding="1" cellspacing="1" id="build_value">
        <tr>
            <th><?php echo defined('HOSPITAL_CAPACITY') ? HOSPITAL_CAPACITY : 'Hospital capacity';?>:</th>
            <td><b><?php echo $storedWounded;?></b> / <?php echo $capacity;?></td>
        </tr>
        <tr>
            <th><?php echo HEALING_TIME_NOW;?></th>
            <td><b><?php echo $current;?></b> <?php echo PERCENT;?></td>
        </tr>
        <?php if (!$building->isMax($village->resarray['f'.$id.'t'], $id)):
            $next = $level + 1 + $loopsame + $doublebuild + $master;
            $next = $next > 20 ? 20 : $next;
            $nextFactor = (int)round($bid48[$next]['attri'] * 100);
        ?>
        <tr>
            <th><?php echo HEALING_TIME_LEVEL;?> <?php echo $next;?>:</th>
            <td><b><?php echo $nextFactor;?></b> <?php echo PERCENT;?></td>
        </tr>
        <?php endif;?>
    </table>

    <h4 class="hosp"><?php echo WOUNDED_TROOPS; ?></h4>
    <?php if ($hasWounded && $level > 0): ?>
    <form method="post" action="build.php?id=<?php echo (int)$id; ?>" name="snd">
    <table cellpadding="1" cellspacing="1" id="hospital_wounded">
        <thead>
            <tr>
                <td colspan="2"><?php echo NAME; ?></td>
                <td><?php echo AVAILABLE; ?></td>
                <td><?php echo QUANTITY; ?></td>
                <td><?php echo MAX; ?></td>
                <td></td>
            </tr>
        </thead>
        <tbody>
        <?php for ($wi = 1; $wi <= 100; $wi++): if (empty($woundedRows['u'.$wi])) continue;
            $availWounded = (int)$woundedRows['u'.$wi];
            $maxHeal = $technology->maxHeal($wi, true, $availWounded);
            $uName = $technology->getUnitName($wi);
            $uData = $GLOBALS['u'.$wi] ?? null;
            if (!$uData) {
                @include_once __DIR__ . '/../../GameEngine/Data/unitdata.php';
                $uData = $GLOBALS['u'.$wi] ?? [];
            }
            $hWood = (int)ceil(($uData['wood'] ?? 0) / 2);
            $hClay = (int)ceil(($uData['clay'] ?? 0) / 2);
            $hIron = (int)ceil(($uData['iron'] ?? 0) / 2);
            $hCrop = (int)ceil(($uData['crop'] ?? 0) / 2);
            $attri = $level > 0 && isset($bid48[$level]['attri']) ? $bid48[$level]['attri'] : 1.0;
            $hTime = (int)round(($uData['time'] ?? 0) * $attri / (defined('SPEED') ? SPEED : 1));
            if ($hTime < 1) $hTime = 1;
            $hTimeFmt = isset($generator) ? $generator->getTimeFormat($hTime) : gmdate("H:i:s", $hTime);
        ?>
        <tr>
            <td class="ico"><img class="unit u<?php echo $wi; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($uName); ?>" title="<?php echo htmlspecialchars($uName); ?>" /></td>
            <td class="desc">
                <div class="tit">
                    <a href="#" onClick="return Popup(<?php echo $wi; ?>,1);"><?php echo htmlspecialchars($uName); ?></a>
                </div>
                <div class="details">
                    <img class="r1" src="img/x.gif" alt="<?php echo defined('TZ_WOOD') ? TZ_WOOD : 'Lumber'; ?>" title="<?php echo defined('LUMBER') ? LUMBER : 'Lumber'; ?>" /><?php echo $hWood; ?>|
                    <img class="r2" src="img/x.gif" alt="<?php echo defined('CLAY') ? CLAY : 'Clay'; ?>" title="<?php echo defined('CLAY') ? CLAY : 'Clay'; ?>" /><?php echo $hClay; ?>|
                    <img class="r3" src="img/x.gif" alt="<?php echo defined('IRON') ? IRON : 'Iron'; ?>" title="<?php echo defined('IRON') ? IRON : 'Iron'; ?>" /><?php echo $hIron; ?>|
                    <img class="r4" src="img/x.gif" alt="<?php echo defined('CROP') ? CROP : 'Crop'; ?>" title="<?php echo defined('CROP') ? CROP : 'Crop'; ?>" /><?php echo $hCrop; ?>|
                    <img class="clock" src="img/x.gif" alt="<?php echo defined('DURATION') ? DURATION : 'Duration'; ?>" title="<?php echo defined('DURATION') ? DURATION : 'Duration'; ?>" /><?php echo $hTimeFmt; ?>
                </div>
            </td>
            <td class="val"><b><?php echo $availWounded; ?></b></td>
            <td class="val"><input class="text" type="text" name="hamt[<?php echo $wi; ?>]" id="hamt_<?php echo $wi; ?>" value="0" maxlength="6" /></td>
            <td class="max"><a href="#" onClick="document.getElementById('hamt_<?php echo $wi; ?>').value=<?php echo $maxHeal; ?>; return false;">(<?php echo $maxHeal; ?>)</a></td>
            <td><button type="submit" name="hunit" value="<?php echo $wi; ?>" class="trav_buttons"><?php echo HEAL_BUTTON; ?></button></td>
        </tr>
        <?php endfor; ?>
        </tbody>
    </table>
    <input type="hidden" name="ft" value="heal" />
    </form>
    <p class="c"><?php echo HEAL_COST_HINT; ?></p>
    <?php else: ?>
    <p class="none"><?php echo NO_WOUNDED; ?></p>
    <?php endif; ?>

    <?php if (!empty($healingRows)): ?>
    <h4 class="hosp"><?php echo HEALING_IN_PROGRESS; ?></h4>
    <table cellpadding="1" cellspacing="1" id="hospital_healing">
        <?php foreach ($healingRows as $hr):
            $doneAt = (int)$hr['timestamp2'] + ((int)$hr['amt'] - 1) * (int)$hr['eachtime']; ?>
        <tr>
            <td class="ico"><img class="unit u<?php echo (int)$hr['unit']; ?>" src="img/x.gif" alt="" /></td>
            <td class="desc"><?php echo (int)$hr['amt']; ?> x <?php echo $technology->getUnitName((int)$hr['unit']); ?></td>
            <td class="val"><?php echo date("H:i:s", $doneAt); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php include("upgrade.tpl");?>
</div>