<?php
include_once("GameEngine/Generator.php");
$start_timer = $generator->pageLoadTimeStart();

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : warsim.php                                                ##
##  Type           : Attack Simulator File                                     ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Dzoki (Original)                                          ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  URLs:          : https://travianz.org                    		           ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Village.php");
include_once("GameEngine/Data/unitdata.php");
AccessLogger::logRequest();

$battle->procSim($_POST);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?php echo SERVER_NAME ?> - Combat Simulator</title>
	<link rel="shortcut icon" href="favicon.ico"/>
	<meta http-equiv="cache-control" content="max-age=0" />
	<meta http-equiv="pragma" content="no-cache" />
	<meta http-equiv="expires" content="0" />
	<meta http-equiv="imagetoolbar" content="no" />
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<script src="mt-full.js?0faab" type="text/javascript"></script>
	<script src="unx.js?f4b7h" type="text/javascript"></script>
	<script src="new.js?0faab" type="text/javascript"></script>
	<link href="<?php echo GP_LOCATE; ?>lang/en/lang.css?f4b7d" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?f4b7i" rel="stylesheet" type="text/css" />
	<link href="css/mobile_engine.css?v=20261009_m2" rel="stylesheet" type="text/css" />
	<?php
	// GP_LOCATE contine deja pachetul efectiv: alegerea jucatorului cand
	// e permisa si valida, altfel pachetul serverului (vezi config.php).
	echo "
	<link href='".GP_LOCATE."travian.css?e21d2' rel='stylesheet' type='text/css' />
	<link href='".GP_LOCATE."lang/en/lang.css?e21d2' rel='stylesheet' type='text/css' />";
	?>
	<script type="text/javascript">

		window.addEvent('domready', start);
	</script>
</head>


<body class="v35 ie ie8">
<div class="wrapper">
<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" />
<div id="dynamic_header">
	</div>
<?php include("Templates/header.tpl"); ?>
<div id="mid">
<?php include("Templates/menu.tpl"); ?>
<div id="content"  class="warsim">
<h1>Combat simulator</h1>
<form action="warsim.php" method="post">
<?php
if(isset($_POST['result'])) {
	$target = isset($_POST['target'])? $_POST['target'] : array();
	$tribe = isset($_POST['mytribe'])? $_POST['mytribe'] : $session->tribe;
	include("Templates/Simulator/res_a".(int)$tribe.".tpl");
    foreach($target as $tar) {
        include("Templates/Simulator/res_d".(int)$tar.".tpl");
    }
    echo "<p>Type of attack: <b>";
    echo $form->getValue('ktyp') == 0 ? "Normal" : "Raid";
    echo "</b></p>";
    echo "<p>";
	if (isset($_POST['result'][7]) && isset($_POST['result'][8])){
		if ($form->getValue('ktyp') == 1) {
			echo "Hint: The ram does not work during a raid.<br>";
		}elseif ($_POST['result'][7] == 0){
			echo "Damage done by ram: from level <b>".$form->getValue('walllevel')."</b> to level <b>0</b></p>";
		}elseif ($_POST['result'][7] == $_POST['result'][8]){
			echo "Damage done by ram: from level <b>".$form->getValue('walllevel')."</b> to level <b>".$form->getValue('walllevel')."</b></p>";
		}else{
			echo "Damage done by ram: from level <b>".$form->getValue('walllevel')."</b> to level <b>".(int)$_POST['result'][7]."</b></p>";
		}
	}

	if (isset($_POST['result'][3]) && isset($_POST['result'][4])){
		if ($form->getValue('ktyp') == 1) {
			echo "Hint: The catapult does not shoot during a raid.</p>";
		}elseif ($_POST['result'][3] == 0){
			echo "Damage done by catapult: from level <b>".$form->getValue('kata')."</b> to level <b>0</b></p>";
		}elseif ($_POST['result'][3] == $_POST['result'][4]){
			echo "Damage done by catapult: from level <b>".$form->getValue('kata')."</b> to level <b>".$form->getValue('kata')."</b></p></p>";
		}else{
			echo "Damage done by catapult: from level <b>".$form->getValue('kata')."</b> to level <b>".(int)$_POST['result'][3]."</b></p>";
		}
	}

	if (!empty($_POST['result']['hero']) && !empty($_POST['result']['hero']['sent'])) {
		$heroRes = $_POST['result']['hero'];
		$heroDead = !empty($heroRes['dead']);
		$heroDmg = (int)$heroRes['damage'];
		$startHp = (int)$heroRes['start_hp'];
		$remainHp = (int)$heroRes['remain_hp'];
		?>
		<div id="hero_sim_result" style="margin: 10px 0 14px 0; padding: 10px 14px; border: 1px solid <?php echo $heroDead ? '#d9534f' : '#7db72f'; ?>; background: <?php echo $heroDead ? '#fff5f5' : '#f4faec'; ?>; border-radius: 4px; max-width: 650px;">
			<div style="font-size: 13px; font-weight: bold; color: <?php echo $heroDead ? '#c0392b' : '#27ae60'; ?>; margin-bottom: 6px;">
				<img src="img/x.gif" class="unit uhero" alt="" style="vertical-align: middle; margin-right: 4px;" />
				Hasil Simulasi Hero: <?php echo $heroDead ? '<span style="color:#c0392b;">GUGUR (Meninggal di Pertempuran)</span>' : '<span style="color:#27ae60;">SELAMAT (Bertahan Hidup)</span>'; ?>
			</div>
			<table style="width: 100%; font-size: 11px; border-collapse: collapse;">
				<tr>
					<td style="padding: 2px 4px; width: 45%;">Kekuatan Serang Hero:</td>
					<td style="padding: 2px 4px;"><b><?php echo number_format($heroRes['h_off']); ?></b> (+<?php echo (int)$heroRes['h_off_bonus']; ?>% off bonus)</td>
				</tr>
				<tr>
					<td style="padding: 2px 4px;">Pengurangan Darah (Damage):</td>
					<td style="padding: 2px 4px;">
						<b style="color: <?php echo $heroDead ? '#c0392b' : '#d35400'; ?>;">-<?php echo $heroDmg; ?>% HP</b>
						<?php if (!empty($heroRes['raw_damage']) && $heroRes['raw_damage'] > $heroDmg): ?>
							<span style="color: #666;">(Armor meredam <?php echo (int)($heroRes['raw_damage'] - $heroDmg); ?>% damage)</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding: 2px 4px;">Estimasi Sisa Darah (Health):</td>
					<td style="padding: 2px 4px;">
						<?php echo $startHp; ?>% HP &rarr; 
						<b style="color: <?php echo $heroDead ? '#c0392b' : ($remainHp < 30 ? '#d35400' : '#27ae60'); ?>;"><?php echo $remainHp; ?>% HP</b>
						<?php if ($heroDead): ?>
							<span style="color: #c0392b; font-size: 10px; margin-left: 4px;">(Hero gugur karena luka melebihi darah atau seluruh pasukan terbunuh)</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}

if (!empty($_GET['target'])) {
    // this only works for Nature, as GET links like this one will come from an oasis
    if (!$_GET['target'] != 4) {
        $_GET['target'] = 4;
    }

    // fill-in session value-array data
    foreach ($_GET as $key => $value) {
        if ($key[0] === 'u' && is_numeric($value)) {
            $form->setValue('a2_' . substr($key, 1), $value);
        }
    }


}

$target = isset($_POST['target'])? $_POST['target'] : (!empty($_GET['target']) ? array((int) $_GET['target']) : array());
$tribe = isset($_POST['mytribe'])? $_POST['mytribe'] : $session->tribe;
if(count($target) > 0) {
	$myHero = null;
	if (!empty($session->uid) && class_exists('Units')) {
		$unitsObj = isset($GLOBALS['units']) && is_object($GLOBALS['units']) ? $GLOBALS['units'] : new Units();
		$myHero = $unitsObj->Hero($session->uid);
	}
	if ($myHero && !empty($myHero['name'])) {
		$heroAtkVal = (int)$myHero['atk'];
		$heroBonVal = round(($myHero['ob'] - 1) * 100);
		$heroHpVal  = (int)$myHero['health'];
		?>
		<div style="margin: 6px 0 10px 0; padding: 6px 10px; background: #fdfaf2; border: 1px dashed #d4af37; border-radius: 4px; font-size: 11px;">
			<img src="img/x.gif" class="unit uhero" alt="" style="vertical-align: middle; margin-right: 4px;" />
			<b>Hero Anda (<?php echo htmlspecialchars($myHero['name']); ?>):</b> 
			Power: <b><?php echo number_format($heroAtkVal); ?></b> | 
			Bonus: <b>+<?php echo $heroBonVal; ?>%</b> | 
			Darah: <b><?php echo $heroHpVal; ?>%</b>
			<a href="javascript:void(0);" onclick="fillMyHero(<?php echo $heroAtkVal; ?>, <?php echo $heroBonVal; ?>, <?php echo $heroHpVal; ?>);" style="margin-left: 8px; color: #1e6bb8; font-weight: bold; text-decoration: underline;">[Gunakan Statistik Hero Saya ke Form]</a>
		</div>
		<script type="text/javascript">
		function fillMyHero(atk, offBonus, health) {
			var hOff = document.getElementsByName('h_off')[0];
			var hBonus = document.getElementsByName('h_off_bonus')[0];
			var hHp = document.getElementsByName('h_hp')[0];
			if (hOff) hOff.value = atk;
			if (hBonus) hBonus.value = offBonus;
			if (hHp) hHp.value = health;
		}
		</script>
		<?php
	}
	include("Templates/Simulator/att_".(int)$tribe.".tpl");
	echo "<table id=\"defender\" class=\"fill_in\" cellpadding=\"1\" cellspacing=\"1\">

	<thead>
		<tr>
			<th>
				Defender <span class=\"small\"></span>
			</th>
		</tr>
	</thead>";
	foreach($target as $tar) {
		include("Templates/Simulator/def_".(int)$tar.".tpl");
	}
	include("Templates/Simulator/def_end.tpl");
	echo "<div class=\"clear\"></div>";
}

/**
 * Ce bonusuri PROPRII a folosit simularea.
 *
 * Simulatorul preia automat itemele echipate ale eroului si bonusurile
 * aliantei. Fara acest panou le-ar aplica in tacere, iar jucatorul n-ar avea
 * cum sa stie daca rezultatul le include sau nu.
 */
$wsLines = array();

if (class_exists('HeroBattleBonus') && HeroBattleBonus::enabled() && !empty($session->uid)) {

    $wsStrength = (int) HeroBattleBonus::statBonus($session->uid);

    if ($wsStrength > 0) {
        $wsLines[] = (defined('TZ_WS_ITEM_STRENGTH') ? TZ_WS_ITEM_STRENGTH : 'Equipment strength')
            . ': <b>+' . number_format($wsStrength) . '</b>';
    }

    $wsMap = HeroBattleBonus::unitBonusMap($session->uid);

    if (!empty($wsMap)) {
        foreach ($wsMap as $wsUnit => $wsPer) {
            // Numele unitatilor sunt constante de limba (U1..U90), nu campuri
            // in unitdata.php - acolo sunt doar valorile de lupta.
            $wsConst = 'U' . (int) $wsUnit;
            $wsName  = defined($wsConst) ? constant($wsConst) : $wsConst;

            $wsLines[] = (defined('TZ_WS_ITEM_UNIT') ? TZ_WS_ITEM_UNIT : 'Weapon bonus')
                . ': <b>+' . (int) $wsPer . '</b> / ' . htmlspecialchars($wsName, ENT_QUOTES, 'UTF-8');
        }
    }

    $wsBon = HeroBattleBonus::bonuses($session->uid);

    if ($wsBon && defined('HB_VS_NATARS') && !empty($wsBon[HB_VS_NATARS])) {
        $wsLines[] = (defined('TZ_WS_VS_NATARS') ? TZ_WS_VS_NATARS : 'Against Natars')
            . ': <b>+' . (int) $wsBon[HB_VS_NATARS] . '%</b>';
    }
}

if (class_exists('AllianceBonus') && AllianceBonus::enabled() && !empty($session->uid)) {
    $wsMetal = AllianceBonus::multiplier($session->uid, AllianceBonus::METALLURGY);

    if ($wsMetal > 1.0) {
        $wsLines[] = (defined('ALLYBONUS_METALLURGY') ? ALLYBONUS_METALLURGY : 'Metallurgy')
            . ': <b>+' . round(($wsMetal - 1) * 100) . '%</b>';
    }
}

if ($wsLines) {
?>
<style>
#ws_own{margin:6px 0 10px 0;padding:5px 9px;border-left:3px solid #7db72f;
    background:#f4faec;font-size:11px;color:#444;max-width:600px}
#ws_own .ws_t{font-weight:bold;color:#333;margin-right:4px}
#ws_own span.ws_i{display:inline-block;margin-right:12px;white-space:nowrap}
</style>
<div id="ws_own">
    <span class="ws_t"><?php echo defined('TZ_WS_APPLIED') ? TZ_WS_APPLIED
        : 'Automatically included in this simulation:'; ?></span>
    <?php foreach ($wsLines as $wsLine) { ?>
        <span class="ws_i"><?php echo $wsLine; ?></span>
    <?php } ?>
</div>
<?php
}
?>
<table id="select" cellpadding="1" cellspacing="1">
<thead><tr>
	<td>Attacker</td>
	<td>Defender</td>
	<td>Type of attack</td>
</tr></thead>
<tbody><tr>
	<td>
		<label><input class="radio" type="radio" name="a1_v" value="1" <?php if($tribe == 1) { echo "checked"; } ?>/> Romans</label><br/>
		<label><input class="radio" type="radio" name="a1_v" value="2" <?php if($tribe == 2) { echo "checked"; } ?>/> Teutons</label><br/>
		<label><input class="radio" type="radio" name="a1_v" value="3" <?php if($tribe == 3) { echo "checked"; } ?>/> Gauls</label><br/>
		<?php if (defined('NEW_FUNCTION_TRIBE_HUNS') && NEW_FUNCTION_TRIBE_HUNS) { ?><label><input class="radio" type="radio" name="a1_v" value="6" <?php if($tribe == 6) { echo "checked"; } ?>/> Huns</label><br/><?php } ?>
		<?php if (defined('NEW_FUNCTION_TRIBE_EGIPTEANS') && NEW_FUNCTION_TRIBE_EGIPTEANS) { ?><label><input class="radio" type="radio" name="a1_v" value="7" <?php if($tribe == 7) { echo "checked"; } ?>/> Egyptians</label><br/><?php } ?>
		<?php if (defined('NEW_FUNCTION_TRIBE_SPARTANS') && NEW_FUNCTION_TRIBE_SPARTANS) { ?><label><input class="radio" type="radio" name="a1_v" value="8" <?php if($tribe == 8) { echo "checked"; } ?>/> Spartans</label><br/><?php } ?>
		<?php if (defined('NEW_FUNCTION_TRIBE_VIKINGS') && NEW_FUNCTION_TRIBE_VIKINGS) { ?><label><input class="radio" type="radio" name="a1_v" value="9" <?php if($tribe == 9) { echo "checked"; } ?>/> Vikings</label><br/><?php } ?>
		<?php if (!defined('NEW_FUNCTION_TRIBE_NUSANTARA') || NEW_FUNCTION_TRIBE_NUSANTARA) { ?><label><input class="radio" type="radio" name="a1_v" value="10" <?php if($tribe == 10) { echo "checked"; } ?>/> <?php echo defined('TRIBE10') ? TRIBE10 : 'Nusantara'; ?></label><br/><?php } ?>
		<label><input class="radio" type="radio" name="a1_v" value="5" <?php if($tribe == 5) { echo "checked"; } ?>/> Natars</label>
	</td><td>
		<label><input class="check" type="checkbox" name="a2_v1" value="1" <?php if(in_array(1,$target)) { echo "checked"; } ?>/> Romans</label><br/>
		<label><input class="check" type="checkbox" name="a2_v2" value="1" <?php if(in_array(2,$target)) { echo "checked"; } ?>/> Teutons</label><br/>
		<label><input class="check" type="checkbox" name="a2_v3" value="1" <?php if(in_array(3,$target)) { echo "checked"; } ?>/> Gauls</label><br/>
		<label><input class="check" type="checkbox" name="a2_v4" value="1" <?php if(in_array(4,$target)) { echo "checked"; } ?>/> Nature</label><br/>
		<label><input class="check" type="checkbox" name="a2_v5" value="1" <?php if(in_array(5,$target)) { echo "checked"; } ?>/> Natars</label><br/>
		<?php if (defined('NEW_FUNCTION_TRIBE_HUNS') && NEW_FUNCTION_TRIBE_HUNS) { ?><label><input class="check" type="checkbox" name="a2_v6" value="1" <?php if(in_array(6,$target)) { echo "checked"; } ?>/> Huns</label><br/><?php } ?>
		<?php if (defined('NEW_FUNCTION_TRIBE_EGIPTEANS') && NEW_FUNCTION_TRIBE_EGIPTEANS) { ?><label><input class="check" type="checkbox" name="a2_v7" value="1" <?php if(in_array(7,$target)) { echo "checked"; } ?>/> Egyptians</label><br/><?php } ?>
		<?php if (defined('NEW_FUNCTION_TRIBE_SPARTANS') && NEW_FUNCTION_TRIBE_SPARTANS) { ?><label><input class="check" type="checkbox" name="a2_v8" value="1" <?php if(in_array(8,$target)) { echo "checked"; } ?>/> Spartans</label><br/><?php } ?>
		<?php if (defined('NEW_FUNCTION_TRIBE_VIKINGS') && NEW_FUNCTION_TRIBE_VIKINGS) { ?><label><input class="check" type="checkbox" name="a2_v9" value="1" <?php if(in_array(9,$target)) { echo "checked"; } ?>/> Vikings</label><br/><?php } ?>
		<?php if (!defined('NEW_FUNCTION_TRIBE_NUSANTARA') || NEW_FUNCTION_TRIBE_NUSANTARA) { ?><label><input class="check" type="checkbox" name="a2_v10" value="1" <?php if(in_array(10,$target)) { echo "checked"; } ?>/> <?php echo defined('TRIBE10') ? TRIBE10 : 'Nusantara'; ?></label><?php } ?>
		</td><td>
		<label><input class="radio" type="radio" name="ktyp" value="0" <?php if($form->getValue('ktyp') == 0 || $form->getValue('ktyp') == "") { echo "checked"; } ?>/> normal</label><br/>

		<label><input class="radio" type="radio" name="ktyp" value="1" <?php if($form->getValue('ktyp') == 1) { echo "checked"; } ?>/> raid</label><br/>
		<label><input type="hidden" name="uid" value="<?php echo $session->uid; ?>" /></label>
	</td>
</tr></tbody>
</table>

<p class="btn"><button value="ok" name="s1" id="btn_ok" class="trav_buttons" alt="OK" /> OK </button></p>
</form>
</div>
<br /><br /><br /><br /><div id="side_info">
<?php
include("Templates/multivillage.tpl");
include("Templates/quest.tpl");
include("Templates/news.tpl");
if(!NEW_FUNCTIONS_DISPLAY_LINKS) {
	echo "<br><br><br><br>";
	include("Templates/links.tpl");
}
?>
</div>
<div class="clear"></div>
</div>
<div class="footer-stopper"></div>
<div class="clear"></div>

<?php
include("Templates/footer.tpl");
include("Templates/res.tpl");
?>
<div id="stime">
<div id="ltime">
<div id="ltimeWrap">
<?php echo CALCULATED_IN;?> <b><?php
echo round(($generator->pageLoadTimeEnd()-$start_timer)*1000);
?></b> ms

<br /><?php echo SERVER_TIME;?> <span id="tp1" class="b"><?php echo date('H:i:s'); ?></span>
</div>
	</div>
</div>

<div id="ce"></div>
</body>
</html>
