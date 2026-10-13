<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       production.tpl                                              ##
##  Developed by:  Dzoki                                                       ##
##  Refactored by: Shadow Incremental Refactor 			                       ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##                                                                             ##
##  Incremental Refactor Notes:                                                ##
##  - Preserved original functionality                                         ##
##  - Compatible with older PHP 7+ environments                                ##
##  - Reduced repeated method calls where possible                             ##
##  - Added small safety layer for village object                              ##
##  - Improved readability                                                     ##
##                                                                             ##
#################################################################################

/**
 * ---------------------------------------------------------
 * Safety checks (legacy PHP compatibility)
 * ---------------------------------------------------------
 */
$woodProd = isset($village) ? $village->getProd("wood") : 0;
$clayProd = isset($village) ? $village->getProd("clay") : 0;
$ironProd = isset($village) ? $village->getProd("iron") : 0;
$cropProd = isset($village) ? $village->getProd("crop") : 0;

$cropGross  = isset($village->allcrop) ? (int)$village->allcrop : 0;
$cropPop    = isset($village->pop) ? (int)$village->pop : 0;
$cropUpkeep = (isset($technology) && isset($village->unitall)) ? (int)$technology->getUpkeep($village->unitall, 0) : 0;
$cropTitle  = "Produksi Bersih: {$cropProd}/jam (Ladang Kotor: +{$cropGross} | Populasi: -{$cropPop} | Upkeep Pasukan: -{$cropUpkeep})";

$vid = isset($village->wid) ? (int)$village->wid : 0;
$wMeta = null;
$wRemain = 0;
if (class_exists('Weather') && (!defined('WEATHER_SYSTEM_ENABLED') || WEATHER_SYSTEM_ENABLED)) {
    $curW = Weather::getCurrentWeather();
    $wType = (int)($curW['weather_type'] ?? Weather::TYPE_CLEAR);
    $wMeta = Weather::getWeatherInfo($wType);
    $wRemain = max(0, (int)($curW['end_time'] - time()));
}

$wMultWood = class_exists('Weather') ? Weather::getProductionMultiplier($vid, 'wood') : 1.0;
$wMultClay = class_exists('Weather') ? Weather::getProductionMultiplier($vid, 'clay') : 1.0;
$wMultIron = class_exists('Weather') ? Weather::getProductionMultiplier($vid, 'iron') : 1.0;
$wMultCrop = class_exists('Weather') ? Weather::getProductionMultiplier($vid, 'crop') : 1.0;

if (!function_exists('formatProdModBadge')) {
    function formatProdModBadge(float $m): string {
        if (abs($m - 1.0) < 0.001) return '';
        $diff = round(($m - 1.0) * 100);
        $color = $diff > 0 ? '#27ae60' : '#e74c3c';
        $sign = $diff > 0 ? '+' : '';
        return "<span style='font-size:10px; color:{$color}; margin-left:2px; font-weight:normal;'>({$sign}{$diff}%)</span>";
    }
}
?>

<table id="production" cellpadding="1" cellspacing="1">

    <thead>
        <tr>
            <th colspan="4" style="padding: 4px 6px;">
                <?php echo PRODUCTION; ?>:
            </th>
        </tr>
    </thead>

    <tbody>

        <!-- Wood -->
        <tr>
            <td class="ico">
                <img class="r1" src="img/x.gif" alt="<?php echo LUMBER; ?>" title="<?php echo LUMBER; ?>" />
            </td>

            <td class="res">
                <?php echo LUMBER; ?>:
            </td>

            <td class="num">
                <?php echo $woodProd . formatProdModBadge($wMultWood); ?>
            </td>

            <td class="per">
                <?php echo PER_HR; ?>
            </td>
        </tr>

        <!-- Clay -->
        <tr>
            <td class="ico">
                <img class="r2" src="img/x.gif" alt="<?php echo CLAY; ?>" title="<?php echo CLAY; ?>" />
            </td>

            <td class="res">
                <?php echo CLAY; ?>:
            </td>

            <td class="num">
                <?php echo $clayProd . formatProdModBadge($wMultClay); ?>
            </td>

            <td class="per">
                <?php echo PER_HR; ?>
            </td>
        </tr>

        <!-- Iron -->
        <tr>
            <td class="ico">
                <img class="r3" src="img/x.gif" alt="<?php echo IRON; ?>" title="<?php echo IRON; ?>" />
            </td>

            <td class="res">
                <?php echo IRON; ?>:
            </td>

            <td class="num">
                <?php echo $ironProd . formatProdModBadge($wMultIron); ?>
            </td>

            <td class="per">
                <?php echo PER_HR; ?>
            </td>
        </tr>

        <!-- Crop -->
        <tr title="<?php echo htmlspecialchars($cropTitle); ?>">
            <td class="ico">
                <img class="r4" src="img/x.gif" alt="<?php echo CROP; ?>" title="<?php echo htmlspecialchars($cropTitle); ?>" />
            </td>

            <td class="res">
                <?php echo CROP; ?>:
            </td>

            <td class="num" title="<?php echo htmlspecialchars($cropTitle); ?>">
                <?php if ($cropProd < 0): ?>
                    <span style="color:#d9534f; font-weight:bold;"><?php echo $cropProd; ?></span>
                <?php else: ?>
                    <?php echo $cropProd; ?>
                <?php endif; ?>
                <?php echo formatProdModBadge($wMultCrop); ?>
            </td>

            <td class="per">
                <?php echo PER_HR; ?>
            </td>
        </tr>

        <?php if ($cropProd < 0): ?>
        <tr>
            <td colspan="4" style="font-size:10px; color:#721c24; background:#f8d7da; padding:4px 8px; border-top:1px dashed #f5c6cb;">
                ⚠️ <b>Gandum Defisit:</b> Ladang menghasilkan <b>+<?php echo $cropGross; ?></b>, tetapi dikonsumsi <b>-<?php echo ($cropPop + $cropUpkeep); ?></b>. Pasukan akan mati kelaparan jika lumbung kosong!
            </td>
        </tr>
        <?php endif; ?>

    </tbody>

</table>