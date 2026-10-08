<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : MAIN BUILDING                                             ##
##  Type           : BUILDING TEMPLATE                                         ##
## --------------------------------------------------------------------------- ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow                                                    ##
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

include 'next.tpl';

$field         = 'f' . $id;
$currentLevel  = (int)($village->resarray[$field] ?? 0);
$buildingType  = $village->resarray[$field . 't'] ?? 0;

$currentTime   = $currentLevel > 0 ? round($bid15[$currentLevel]['attri']) : 300;

$isMax         = $building->isMax($buildingType, $id);
$maxLevel      = 20;

$nextLevelRaw  = $currentLevel + 1 + $loopsame + $doublebuild + $master;
$nextLevel     = min($nextLevelRaw, $maxLevel);
$nextTime      = round($bid15[$nextLevel]['attri']);
?>
<div id="build" class="gid15">
    <a href="#" onclick="return Popup(15,4);" class="build_logo">
        <img class="building g15" src="img/x.gif" alt="<?= MAINBUILDING ?>" title="<?= MAINBUILDING ?>">
    </a>

    <h1>
        <?= MAINBUILDING ?>
        <span class="level"><?= LEVEL ?> <?= $currentLevel ?></span>
    </h1>

    <p class="build_desc"><?= MAINBUILDING_DESC ?></p>

    <table cellpadding="1" cellspacing="1" id="build_value">
        <tr>
            <th><?= CURRENT_CONSTRUCTION_TIME ?>:</th>
            <td><b><?= $currentTime ?></b> <?= PERCENT ?></td>
        </tr>

        <?php if (!$isMax): ?>
        <tr>
            <th><?= CONSTRUCTION_TIME_LEVEL ?> <?= $nextLevel ?>:</th>
            <td><b><?= $nextTime ?></b> <?= PERCENT ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <?php
    require_once __DIR__ . '/../../GameEngine/VillageRelocate.php';
    $relocCheck = VillageRelocate::canRelocate($session->uid, $village->wid);
    if ($relocCheck['hasProtection'] && !$relocCheck['hasUsed']):
    ?>
    <div style="margin: 15px 0; padding: 12px 14px; background: #f4fbf4; border: 1.5px solid #a9dfbf; border-radius: 6px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 26px;">📍</span>
            <div>
                <b style="color: #196f3d; font-size: 12px;">Hak Istimewa: Relokasi Desa Pemula (1x)</b>
                <div style="font-size: 10px; color: #555; margin-top: 2px;">
                    Anda dapat memindahkan desa ke lembah kosong mana pun selama perlindungan masih aktif.
                </div>
            </div>
        </div>
        <a href="relocate.php" style="display: inline-block; padding: 6px 12px; background: #27ae60; color: #fff; text-decoration: none; font-weight: bold; font-size: 11px; border-radius: 4px; white-space: nowrap;">
            Pindahkan Desa &raquo;
        </a>
    </div>
    <?php endif; ?>

    <?php
    if ($currentLevel >= 10) {
        include 'Templates/Build/15_1.tpl';
    }
    include 'upgrade.tpl';
    ?>
</div>