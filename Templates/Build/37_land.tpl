<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : HEROMANSION OASIS PAGE				                       ##
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

	$oasisarray = $database->getOasis($village->wid);

	// Waterworks (gid 45, Egipteni): creste bonusul oazelor cu +5% relativ / nivel.
	// Determin nivelul din satul curent si factorul efectiv (25% de baza).
	global $bid45;
	$wwLevel = 0;
	$wwFactor = 0.25;
	for ($wwi = 19; $wwi <= 40; $wwi++) {
		if (isset($village->resarray['f'.$wwi.'t']) && (int)$village->resarray['f'.$wwi.'t'] == 45) {
			$wwLevel = max($wwLevel, (int)$village->resarray['f'.$wwi]);
		}
	}
	if ($wwLevel > 0 && isset($bid45[$wwLevel]['attri'])) {
		$wwFactor = 0.25 * (1 + $bid45[$wwLevel]['attri']);
	}
	$wwMultiplier = $wwFactor / 0.25; // 1.0 fara Waterworks, 1.5 la nivel maxim

	if (
    ((isset($_GET['gid']) && (int)$_GET['gid'] === 37) || (isset($_GET['t4tab']) && $_GET['t4tab'] === 'land') || isset($_GET['land'])) &&
    isset($_GET['del'])
	) {
    $oasisWref = (int)$_GET['del'];

    /*
     * Verificam server-side ca:
     *
     * 1. oaza exista;
     * 2. apartine jucatorului;
     * 3. este cucerita de SATUL CURENT.
     *
     * Nu este suficient doar owner == session uid deoarece un jucator
     * poate avea mai multe sate si mai multe oaze.
     */
    $oasisOwner = (int)$database->getOasisField(
        $oasisWref,
        'owner'
    );

    $oasisConquered = (int)$database->getOasisField(
        $oasisWref,
        'conqured'
    );

    if (
        $oasisWref > 0 &&
        $oasisOwner === (int)$session->uid &&
        $oasisConquered === (int)$village->wid
    ) {
        /*
         * FOARTE IMPORTANT:
         *
         * Returnam doar trupele din OAZA SELECTATA.
         *
         * returnTroops($village->wid, 1) NU trebuie folosit aici,
         * deoarece acela proceseaza toate oazele satului.
         *
         * Functia are lock per oasis si re-citeste DB dupa lock.
         */
        $units->returnOasisTroops($oasisWref);

        /*
         * Dupa ce reinforcement-urile au fost returnate,
         * oaza este eliberata.
         */
        $database->removeOases($oasisWref);
    }

    if (!empty($id)) {
        header("Location: build.php?id=" . $id . "&land");
    } else {
        header("Location: hero.php?t4tab=land");
    }
    exit;
	}

	// Explicit lookup, instead of the original repetitive switch:
	// each oasis type => which resources get bonus and how much.
	// Identical behavior to the original (same alt/title on each icon,
	// including the existing asymmetry: for wood, alt uses TZ_WOOD but
	// title uses LUMBER - kept exactly as in the original source).
	$oasisResourceIcons = [
		'wood' => ['class' => 'r1', 'alt' => TZ_WOOD, 'title' => LUMBER],
		'clay' => ['class' => 'r2', 'alt' => CLAY,    'title' => CLAY],
		'iron' => ['class' => 'r3', 'alt' => IRON,    'title' => IRON],
		'crop' => ['class' => 'r4', 'alt' => CROP,    'title' => CROP],
	];

	$oasisTypeBonuses = [
		1  => [['wood', 25]],
		2  => [['wood', 25]],
		3  => [['wood', 25], ['crop', 25]],
		4  => [['clay', 25]],
		5  => [['clay', 25]],
		6  => [['clay', 25], ['crop', 25]],
		7  => [['iron', 25]],
		8  => [['iron', 25]],
		9  => [['iron', 25], ['crop', 25]],
		10 => [['crop', 25]],
		11 => [['crop', 25]],
		12 => [['crop', 50]],
	];

	// Replace the original switch with 12 identical cases as the structure.
	// Unknown types => empty string, just like the lack of a 'default' in the original switch.
	$renderOasisBonus = function ($type) use ($oasisResourceIcons, $oasisTypeBonuses, $wwMultiplier, $wwLevel) {
		if (!isset($oasisTypeBonuses[$type])) {
			return '';
		}

		$html = '';
		foreach ($oasisTypeBonuses[$type] as $bonus) {
			[$resource, $percent] = $bonus;
			$icon = $oasisResourceIcons[$resource];
			// procentul efectiv (bonusul fizic al oazei x multiplicatorul Waterworks)
			$effective = $percent * $wwMultiplier;
			// afisez cifra rotunda daca e intreaga, altfel o zecimala (25 -> 25, 37.5 -> 37.5)
			$effStr = ($effective == floor($effective)) ? (string)(int)$effective : rtrim(rtrim(number_format($effective, 1, '.', ''), '0'), '.');
			$html .= '<img class="' . $icon['class'] . '" src="img/x.gif" alt="' . $icon['alt'] . '" title="' . $icon['title'] . '" />+' . $effStr . '%';
			// cand Waterworks e activ, arat si bonusul de baza barat pentru context
			if ($wwLevel > 0 && $effective != $percent) {
				$html .= ' <span class="ww_base">(' . $percent . '%)</span>';
			}
		}

		return $html;
	};
	$mansionLevel = 0;
	for ($i = 19; $i <= 38; $i++) {
		if (isset($village->resarray['f' . $i . 't']) && (int)$village->resarray['f' . $i . 't'] == 37) {
			$mansionLevel = max($mansionLevel, (int)$village->resarray['f' . $i]);
		}
	}
	$hero = $database->getHero($session->uid, 0, false);
	$heroLevel = (!empty($hero) && isset($hero[0]['level'])) ? (int)$hero[0]['level'] : 0;

	$mansionSlots = floor(($mansionLevel - 5) / 5);
	$heroSlots    = floor(($heroLevel - 5) / 5);
	$maxOases     = min(3, max(0, (int)$mansionSlots, (int)$heroSlots));
	$usedOases    = $database->VillageOasisCount($village->wid);
	$delBase      = (!empty($id)) ? "build.php?gid=37&amp;id=" . $id . "&amp;land" : "hero.php?t4tab=land";
?>
<div style="margin: 6px 0 12px 0; padding: 8px 12px; background: #fdfdfd; border: 1px solid #d4d4d4; border-radius: 4px; font-size: 11px; line-height: 1.5;">
	<b><?php echo defined('OASES') ? OASES : 'Oases'; ?>:</b>
	<b><?php echo $usedOases; ?></b> / <b><?php echo $maxOases; ?></b> <?php echo defined('TZ_OASIS_SLOTS_USED') ? TZ_OASIS_SLOTS_USED : 'slots used'; ?>
	<span style="color: #666;">
		(<?php echo defined('HERO') ? HERO : 'Hero'; ?> Lv. <?php echo $heroLevel; ?><?php if ($mansionLevel > 0) { echo ', ' . (defined('HEROSMANSION') ? HEROSMANSION : "Hero's Mansion") . ' Lv. ' . $mansionLevel; } ?>)
	</span>
	<br />
	<span style="color: #555;">
		<?php if ($maxOases < 3): ?>
			<?php
			$nextSlotLevel = ($maxOases === 0) ? 10 : (($maxOases === 1) ? 15 : 20);
			echo "Next oasis slot unlocks at Hero or Mansion Level <b>{$nextSlotLevel}</b>.";
			?>
		<?php else: ?>
			Maximum village oasis capacity reached (3/3).
		<?php endif; ?>
		<?php if ($mansionLevel == 0): ?>
			&mdash; <i>Hero's Mansion is optional (Hero level unlocks oasis slots!).</i>
		<?php endif; ?>
	</span>
</div>

<table id="oases" cellpadding="1" cellspacing="1">
<thead><tr>
<th colspan="4"><?php echo OASES; ?></th>
</tr>
<tr>
<td><?php echo NAME; ?></td>
<td><?php echo COORDINATES; ?></td>
<td><?php echo LOYALTY; ?></td>
<td><?php echo RESOURCES; ?></td>
</tr></thead>
<tbody>

<?php
if (!empty($oasisarray)) {
	foreach ($oasisarray as $oasis) {
		$oasiscoor = $database->getCoor($oasis['wref']);
?>
<tr>
<td class="nam">
<a href="<?php echo $delBase; ?>&amp;c=<?php echo $generator->getMapCheck($oasis['wref']); ?>&amp;del=<?php echo $oasis['wref']; ?>"><img class="del" src="img/x.gif" alt="<?php echo DELETE; ?>" title="<?php echo DELETE; ?>"></a>
<a href="karte.php?d=<?php echo $oasis['wref']; ?>&c=<?php echo $generator->getMapCheck($oasis['wref']) ?>"><?php echo $oasis['name']; ?></a>
</td>
<td class="aligned_coords">
<div class="cox">(<?php echo $oasiscoor['x']; ?></div>
<div class="pi">|</div>
<div class="coy"><?php echo $oasiscoor['y']; ?>)</div>
</td>
<td class="zp"><?php echo floor($oasis['loyalty']); ?>%</td>
<td class="res"><?php echo $renderOasisBonus($oasis['type']); ?></td>
</tr>
<?php
	}
} else {
?>
<tr>
<td class="none" colspan="4"><?php echo NO_OASIS; ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<?php if ($wwLevel > 0): ?>
<p class="ww_summary">
	<img class="building g45" src="img/x.gif" alt="<?php echo WATERWORKS; ?>" title="<?php echo WATERWORKS; ?>" />
	<?php echo WATERWORKS; ?> <?php echo LEVEL; ?> <?php echo $wwLevel; ?> &mdash;
	<?php echo CURRENT_BONUS; ?> <b>+<?php echo (int)round($bid45[$wwLevel]['attri'] * 100); ?>%</b>
	<?php echo WATERWORKS_HINT; ?>
</p>
<?php endif; ?>

