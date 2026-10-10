<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       res.tpl                                                     ##
##  Developed by:  Dzoki                                                       ##
##  Refactored by: Shadow Incremental Refactor 			                       ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##                                                                             ##
##  Incremental Refactor Notes:                                                ##
##  - Preserved original functionality                                         ##
##  - Added safety checks for legacy PHP                                       ##
##  - Reduced repeated property access                                         ##
##  - Improved readability                                                     ##
##  - Kept UI structure unchanged                                              ##
##                                                                             ##
#################################################################################

/**
 * ---------------------------------------------------------
 * Safety check (avoid undefined village context)
 * ---------------------------------------------------------
 */
if (!empty($village)) {

    /**
     * -----------------------------------------------------
     * Production values (rounded)
     * -----------------------------------------------------
     */
    $wood = round($village->getProd("wood"));
    $clay = round($village->getProd("clay"));
    $iron = round($village->getProd("iron"));
    $crop = round($village->getProd("crop"));

    /**
     * Total crop production capacity
     */
    $totalproduction = $village->allcrop;

    /**
     * Safely cache values to reduce repeated access
     */
    $woodStore = round($village->awood);
    $clayStore = round($village->aclay);
    $ironStore = round($village->airon);
    $cropStore = round($village->acrop);

    $maxStore  = $village->maxstore;
    $maxCrop   = $village->maxcrop;
?>

<div id="res">
<div id="resWrap">

    <!-- ================= RESOURCES ================= -->
    <table cellpadding="1" cellspacing="1">
        <tr>
            <!-- Wood -->
            <td class="res-cell res-wood" title="<?php echo LUMBER; ?>: <?php echo $wood; ?>/h">
                <img src="img/x.gif" class="r1" alt="<?php echo LUMBER; ?>" title="<?php echo LUMBER; ?>" />
                <span id="l4" title="<?php echo $wood; ?>"><?php echo $woodStore . "/" . $maxStore; ?></span>
            </td>

            <!-- Clay -->
            <td class="res-cell res-clay" title="<?php echo CLAY; ?>: <?php echo $clay; ?>/h">
                <img src="img/x.gif" class="r2" alt="<?php echo CLAY; ?>" title="<?php echo CLAY; ?>" />
                <span id="l3" title="<?php echo $clay; ?>"><?php echo $clayStore . "/" . $maxStore; ?></span>
            </td>

            <!-- Iron -->
            <td class="res-cell res-iron" title="<?php echo IRON; ?>: <?php echo $iron; ?>/h">
                <img src="img/x.gif" class="r3" alt="<?php echo IRON; ?>" title="<?php echo IRON; ?>" />
                <span id="l2" title="<?php echo $iron; ?>"><?php echo $ironStore . "/" . $maxStore; ?></span>
            </td>

            <!-- Crop -->
            <td class="res-cell res-crop" title="<?php echo CROP; ?>: <?php echo $crop; ?>/h">
                <img src="img/x.gif" class="r4" alt="<?php echo CROP; ?>" title="<?php echo CROP; ?>" />
                <span id="l1" title="<?php echo $crop; ?>"><?php echo ($village->acrop > 0 ? $cropStore : '0') . "/" . $maxCrop; ?></span>
            </td>

            <!-- Crop consumption -->
            <td class="res-cell res-pop" title="<?php echo CROP_COM; ?>">
                <img src="img/x.gif" class="r5" alt="<?php echo CROP_COM; ?>" title="<?php echo CROP_COM; ?>" />
                <span><?php echo ($village->pop + $technology->getUpkeep($village->unitall, 0)) . "/" . $totalproduction; ?></span>
            </td>
        </tr>
    </table>

</div>
</div>

<?php } ?>