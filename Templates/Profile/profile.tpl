<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Project:       TravianZ      					       		 		  	   ##
##  Version:       06.05.2026 						       	 				   ##
##  Filename       profile.tpl                                                 ##
##  Refactored by  Shadow					                                   ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##  URLs:          http://travianz.org						       	 		   ##
##  Source code:   http://github.com/Shadowss/TravianZ/         	       	   ##
##                                                                             ##
#################################################################################

// =========================
// MEDALS LOAD
// =========================

$varmedal = $database->getProfileMedal($session->uid);

?>
 
 
<!-- =========================
     PAGE HEADER
========================= -->

<h1><?php echo PLAYER_PROFILE; ?></h1>
<?php include("menu.tpl"); ?>



<form action="spieler.php" method="POST">
<input type="hidden" name="ft" value="p1" />
<input type="hidden" name="id" value="<?php echo isset($id) ? (int)$id : 0; ?>" />

<table cellpadding="1" cellspacing="1" id="edit">

<thead>
<tr>
    <th colspan="3"><?php echo PLAYER; ?> <?php echo htmlspecialchars($session->username, ENT_QUOTES, 'UTF-8'); ?></th>
</tr>
<tr>
    <td colspan="2"><?php echo DETAIL; ?></td>
    <td><?php echo DESCRIPTION; ?></td>
</tr>
</thead>

<tbody>

<tr>
<td colspan="2" class="empty"></td>
<td class="empty"></td>
</tr>

<?php
// =========================
// BIRTHDAY SAFE PARSE
// =========================
$birthday = $session->userinfo['birthday'] ?? 0;
$bday = ($birthday != 0) ? explode("-", $birthday) : array('', '', '');
?>

<tr>
<th><?php echo BDAY; ?></th>
<td class="birth">

<input tabindex="1" class="text day" type="text" name="tag"
value="<?php echo $bday[2] ?? ''; ?>" maxlength="2" />

<select tabindex="2" name="monat" class="dropdown">
<option value="0"></option>

<?php
$months = [
1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'June',
7=>'July',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'
];

foreach ($months as $k => $v) {
    $sel = (isset($bday[1]) && $bday[1] == $k) ? 'selected' : '';
    echo "<option value='$k' $sel>$v</option>";
}
?>

</select>

<input tabindex="3" type="text" name="jahr"
value="<?php echo $bday[0] ?? ''; ?>"
maxlength="4" class="text year">

</td>

<!-- DESCRIPTION RIGHT -->
<td rowspan="<?php echo 7 + count($database->getProfileVillages($session->uid)); ?>" class="desc1">
<textarea tabindex="7" name="be1" maxlength="3000"><?= htmlspecialchars($session->userinfo['desc2'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
</td>
</tr>

<!-- =========================
     GENDER
========================= -->
<tr>
<th><?php echo GENDER; ?></th>
<td class="gend">

<label><input class="radio" type="radio" name="mw" value="0"
<?php if (($session->userinfo['gender'] ?? 0) == 0) echo "checked"; ?>> n/a</label>

<label><input class="radio" type="radio" name="mw" value="1"
<?php if (($session->userinfo['gender'] ?? 0) == 1) echo "checked"; ?>> m</label>

<label><input class="radio" type="radio" name="mw" value="2"
<?php if (($session->userinfo['gender'] ?? 0) == 2) echo "checked"; ?>> f</label>

</td>
</tr>

<!-- LOCATION -->
<tr>
<th><?php echo LOCATION; ?></th>
<td>
<input tabindex="5" type="text" name="ort"
value="<?= htmlspecialchars($session->userinfo['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
maxlength="30" class="text">
</td>
</tr>

<tr><td colspan="2" class="empty"></td></tr>

<?php
// =========================
// VILLAGES LIST (SAFE BUT ALLOWS ')
// =========================
$varray = $database->getProfileVillages($session->uid);

for ($i = 0; $i < count($varray); $i++):
?>
<tr>
<th><?php echo VILLAGE_NAME; ?></th>
<td>
<input tabindex="6" type="text"
name="dname<?php echo $i; ?>"
value="<?= htmlspecialchars($varray[$i]['name'], ENT_QUOTES, 'UTF-8') ?>"
maxlength="30" class="text">
</td>
</tr>
<?php endfor; ?>

<!-- DESCRIPTION LEFT -->
<tr>
<td colspan="2" class="desc2">
<textarea tabindex="8" name="be2" maxlength="3000"><?= htmlspecialchars($session->userinfo['desc1'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
</td>
</tr>

</tbody>
</table>

<!-- =========================
     MEDALS TABLE
========================= -->
<p>
<table cellspacing="1" cellpadding="2" class="tbg">

<tr><td class="rbg" colspan="4"><?php echo MEDALS; ?></td></tr>

<tr>
<td><?php echo CATEGORY; ?></td>
<td><?php echo RANK; ?></td>
<td><?php echo WEEK; ?></td>
<td><?php echo BB_CODE; ?></td>
</tr>

<?php
// =========================
// DYNAMIC MEDALS
// =========================
foreach ($varmedal as $medal) {

    $titel = "Bonus";

    switch ($medal['categorie']) {
        case "1": $titel="Attacker of the Week"; break;
        case "2": $titel="Defender of the Week"; break;
        case "3": $titel="Pop Climber of the week"; break;
        case "4": $titel="Robber of the week"; break;
        case "5": $titel="Top 10 Attack+Def"; break;
        case "6": $titel="Top Attack streak ".$medal['points']; break;
        case "7": $titel="Top Def streak ".$medal['points']; break;
        case "8": $titel="Top Pop streak ".$medal['points']; break;
        case "9": $titel="Top Rob streak ".$medal['points']; break;
        case "10": $titel="Rank Climber"; break;
        case "11": $titel="Rank streak ".$medal['points']; break;
        case "12": $titel="Top 10 Attack"; break;
        case "13": $titel="Top 10 Def"; break;
        case "14": $titel="Top 10 Pop"; break;
        case "15": $titel="Top 10 Rob"; break;
        case "16": $titel="Top 10 Rank"; break;
    }

    echo "<tr>
    <td>$titel</td>
    <td>{$medal['plaats']}</td>
    <td>{$medal['week']}</td>
    <td><a href='#' onclick=\"insertMedal('[#{$medal['id']}]'); return false;\">[#{$medal['id']}]</a></td>
    </tr>";
}
?>

<tr>
<td><?php echo BEGINPRO; ?></td><td></td><td></td>
<td><a href="#" onclick="insertMedal('[#0]'); return false;">[#0]</a></td>
</tr>

<?php if (NEW_FUNCTIONS_MEDAL_3YEAR): ?>
<tr><td>veteran</td><td></td><td></td><td><a href="#" onclick="insertMedal('[#g2300]'); return false;">[#g2300]</a></td></tr>
<?php endif; ?>

<?php if (NEW_FUNCTIONS_MEDAL_5YEAR): ?>
<tr><td>veteran_5a</td><td></td><td></td><td><a href="#" onclick="insertMedal('[#g2301]'); return false;">[#g2301]</a></td></tr>
<?php endif; ?>

<?php if (NEW_FUNCTIONS_MEDAL_10YEAR): ?>
<tr><td>veteran_10a</td><td></td><td></td><td><a href="#" onclick="insertMedal('[#g2302]'); return false;">[#g2302]</a></td></tr>
<?php endif; ?>

<?php
// =========================
// TRIBE MEDALS
// =========================

$tribeMedals = [];

if (defined('NEW_FUNCTIONS_TRIBE_IMAGES') && NEW_FUNCTIONS_TRIBE_IMAGES) {
    $tribeMedals[1] = ['Romans', 'roman'];
    $tribeMedals[2] = ['Teutons', 'teuton'];
    $tribeMedals[3] = ['Gauls', 'gaul'];
}

if (defined('NEW_FUNCTION_TRIBE_HUNS') && NEW_FUNCTION_TRIBE_HUNS) {
    $tribeMedals[6] = ['Huns', 'huns'];
}

if (defined('NEW_FUNCTION_TRIBE_EGIPTEANS') && NEW_FUNCTION_TRIBE_EGIPTEANS) {
    $tribeMedals[7] = ['Egyptians', 'egyptians'];
}

if (defined('NEW_FUNCTION_TRIBE_SPARTANS') && NEW_FUNCTION_TRIBE_SPARTANS) {
    $tribeMedals[8] = ['Spartans', 'spartans'];
}

if (defined('NEW_FUNCTION_TRIBE_VIKINGS') && NEW_FUNCTION_TRIBE_VIKINGS) {
    $tribeMedals[9] = ['Vikings', 'vikings'];
}

$tribe = $session->userinfo['tribe'] ?? 0;

if (isset($tribeMedals[$tribe])) {
    [$name, $tag] = $tribeMedals[$tribe];

    echo "<tr><td>Tribe {$name}</td><td></td><td></td>
    <td><a href='#' onclick=\"insertMedal('[#{$tag}]'); return false;\">[#{$tag}]</a></td></tr>";
}

// =========================
// MHS MEDALS
// =========================
if(defined('NEW_FUNCTIONS_MHS_IMAGES') && NEW_FUNCTIONS_MHS_IMAGES){

    if(($session->userinfo['access'] ?? 0) == 9){

        echo "<tr><td>".ADMIN1."</td><td></td><td></td>
        <td><a href='#' onclick=\"insertMedal('[#MULTIHUNTER]'); return false;\">[#MULTIHUNTER]</a></td></tr>";

        echo "<tr><td>".ADMIN1."</td><td></td><td></td>
        <td><a href='#' onclick=\"insertMedal('[#MH]'); return false;\">[#MH]</a></td></tr>";

        echo "<tr><td>".ADMIN1."</td><td></td><td></td>
        <td><a href='#' onclick=\"insertMedal('[#TEAM]'); return false;\">[#TEAM]</a></td></tr>";

    } elseif(($session->userinfo['access'] ?? 0) == 8){

        echo "<tr><td>".MULTIH1."</td><td></td><td></td>
        <td><a href='#' onclick=\"insertMedal('[#MULTIHUNTER]'); return false;\">[#MULTIHUNTER]</a></td></tr>";

        echo "<tr><td>".MULTIH1."</td><td></td><td></td>
        <td><a href='#' onclick=\"insertMedal('[#MH]'); return false;\">[#MH]</a></td></tr>";

        echo "<tr><td>".MULTIH1."</td><td></td><td></td>
        <td><a href='#' onclick=\"insertMedal('[#TEAM]'); return false;\">[#TEAM]</a></td></tr>";
    }
}

// =========================
// SHADOW SPECIAL
// =========================
if(($session->userinfo['username'] ?? '') == "Shadow"){

    echo "<tr><td>Shadow</td><td></td><td></td>
    <td><a href='#' onclick=\"insertMedal('[#SHADOW]'); return false;\">[#SHADOW]</a></td></tr>";

    echo "<tr><td>Shadow</td><td></td><td></td>
    <td><a href='#' onclick=\"insertMedal('[#MH]'); return false;\">[#MH]</a></td></tr>";

    echo "<tr><td>Shadow</td><td></td><td></td>
    <td><a href='#' onclick=\"insertMedal('[#TEAM]'); return false;\">[#TEAM]</a></td></tr>";

    echo "<tr><td>Shadow</td><td></td><td></td>
    <td><a href='#' onclick=\"insertMedal('[#EVENT]'); return false;\">[#EVENT]</a></td></tr>";
}

// =========================
// SPECIAL MEDALS
// =========================
if(defined('NEW_FUNCTIONS_SPECIAL_MEDALS_SYSTEM') && NEW_FUNCTIONS_SPECIAL_MEDALS_SYSTEM){
    $uid = (int)$session->uid;

    // 1. Artefact - CORECTAT coloanele (4 td-uri)
    $arte = $database->query("SELECT 1 FROM ".TB_PREFIX."artefacts WHERE owner = $uid LIMIT 1");
    if($arte && $arte->num_rows > 0){
        echo "<tr>
                <td>Artefact Holder</td>
                <td></td>
                <td></td>
                <td><a href='#' onclick=\"insertMedal('[#ARTEFACT]'); return false;\">[#ARTEFACT]</a></td>
              </tr>";
    }

    // 2. WW - CORECTAT la f99 (la tine WW e in f99, nu f40)
    $ww = $database->query("SELECT f.f99 FROM ".TB_PREFIX."vdata v
        INNER JOIN ".TB_PREFIX."fdata f ON f.vref = v.wref
        WHERE v.owner = $uid AND f.f99t = 40 AND f.f99 > 0 LIMIT 1");
    if($ww && $ww->num_rows > 0){
        echo "<tr>
                <td>WW Builder</td>
                <td></td>
                <td></td>
                <td><a href='#' onclick=\"insertMedal('[#WWBUILDER]'); return false;\">[#WWBUILDER]</a></td>
              </tr>";

        $lvl = (int)$ww->fetch_assoc()['f99'];
        if($lvl >= 100){
            echo "<tr>
                    <td>WW Winner</td>
                    <td></td>
                    <td></td>
                    <td><a href='#' onclick=\"insertMedal('[#WINNERWW]'); return false;\">[#WINNERWW]</a></td>
                  </tr>";
        }
    }

    // 3. GREATSTORE - DOAR 38 si 39 nivel 20
    $hasGreatStore = false;
    $qgs = $database->query("SELECT f.* FROM ".TB_PREFIX."fdata f 
                             JOIN ".TB_PREFIX."vdata v ON v.wref=f.vref 
                             WHERE v.owner=$uid");
    if($qgs){
        while($f = $qgs->fetch_assoc()){
            $hasWh = $hasGr = false;
            for($i=1; $i<=99; $i++){
                if(!isset($f["f{$i}t"])) continue;
                $type = (int)$f["f{$i}t"];
                $lvl  = (int)$f["f{$i}"];
                if($type == 38 && $lvl == 20) $hasWh = true; // Great Warehouse
                if($type == 39 && $lvl == 20) $hasGr = true; // Great Granary
            }
            if($hasWh && $hasGr){ $hasGreatStore = true; break; }
        }
    }
    if($hasGreatStore){
        echo "<tr>
                <td>Great Store</td>
                <td></td>
                <td></td>
                <td><a href='#' onclick=\"insertMedal('[#GREATSTORE]'); return false;\">[#GREATSTORE]</a></td>
              </tr>";
    }
	
	// 4. WALLMASTER - 5 sate cu zid 20 (31/32/33), fara f40/f99
	
	$wallCount = 0;
	$qw = $database->query("SELECT f.f40, f.f40t FROM ".TB_PREFIX."fdata f 
                        JOIN ".TB_PREFIX."vdata v ON v.wref=f.vref 
                        WHERE v.owner=$uid");
	if($qw){
    while($r = $qw->fetch_assoc()){
        if((int)$r['f40'] == 20 && in_array((int)$r['f40t'], [31,32,33,42,43,47,50])){
            $wallCount++;
        }
    }
	}
	if($wallCount >= 3){
    echo "<tr>
            <td>Wall Master</td>
            <td></td>
            <td></td>
            <td><a href='#' onclick=\"insertMedal('[#WALLMASTER]'); return false;\">[#WALLMASTER]</a></td>
          </tr>";
	}

	// 5. HERO100 - erou nivel 99+
    $h100 = $database->query("SELECT 1 FROM ".TB_PREFIX."hero WHERE uid=$uid AND level>=99 LIMIT 1");
    if($h100 && $h100->num_rows){
        echo "<tr>
                <td>Hero 99+</td>
                <td></td>
                <td></td>
                <td><a href='#' onclick=\"insertMedal('[#HERO100]'); return false;\">[#HERO100]</a></td>
              </tr>";
    }
}

// ==========================================
// PVE & ACHIEVEMENT MEDALS SYSTEM
// ==========================================
$achUid = (int)$session->uid;
if ($achUid > 0) {
    // 1. Bandit Camps & World Boss
    $qAchBandit = $database->query("
        SELECT 
            COUNT(*) as total_camps, 
            SUM(CASE WHEN tier = 4 THEN 1 ELSE 0 END) as boss_camps 
        FROM " . TB_PREFIX . "bandit_camps 
        WHERE cleared_by = $achUid
    ");
    $achBanditData = ($qAchBandit && $r = $qAchBandit->fetch_assoc()) ? $r : ['total_camps' => 0, 'boss_camps' => 0];
    $achBanditCamps = (int)($achBanditData['total_camps'] ?? 0);
    $achBossCamps   = (int)($achBanditData['boss_camps'] ?? 0);

    if ($achBanditCamps >= 10) {
        echo "<tr>
                <td>Bandit Hunter</td>
                <td>{$achBanditCamps} camps</td>
                <td>Tier 1</td>
                <td><a href='#' onclick=\"insertMedal('[#BANDITHUNTER]'); return false;\">[#BANDITHUNTER]</a></td>
              </tr>";
    }

    if ($achBanditCamps >= 30) {
        echo "<tr>
                <td>Bandit Slayer</td>
                <td>{$achBanditCamps} camps</td>
                <td>Tier 2</td>
                <td><a href='#' onclick=\"insertMedal('[#BANDITSLAYER]'); return false;\">[#BANDITSLAYER]</a></td>
              </tr>";
    }

    if ($achBanditCamps >= 75) {
        echo "<tr>
                <td>Bandit Nemesis</td>
                <td>{$achBanditCamps} camps</td>
                <td>Tier 3</td>
                <td><a href='#' onclick=\"insertMedal('[#BANDITNEMESIS]'); return false;\">[#BANDITNEMESIS]</a></td>
              </tr>";
    }

    if ($achBossCamps >= 1) {
        echo "<tr>
                <td>World Boss Slayer</td>
                <td>{$achBossCamps} boss defeated</td>
                <td>Special</td>
                <td><a href='#' onclick=\"insertMedal('[#BOSSSLAYER]'); return false;\">[#BOSSSLAYER]</a></td>
              </tr>";
    }

    // 2. Boss Raider (Damage Dealt)
    $qAchDmg = $database->query("SELECT SUM(damage_dealt) as total_dmg FROM " . TB_PREFIX . "bandit_camp_attacks WHERE uid = $achUid");
    $achBossDmg = (int)(($qAchDmg && $r = $qAchDmg->fetch_assoc()) ? $r['total_dmg'] : 0);
    if ($achBossDmg >= 50000) {
        echo "<tr>
                <td>Grand Vanguard</td>
                <td>" . number_format($achBossDmg) . " dmg</td>
                <td>Raid</td>
                <td><a href='#' onclick=\"insertMedal('[#BOSSRAIDER]'); return false;\">[#BOSSRAIDER]</a></td>
              </tr>";
    }

    // 3. Oasis Master (3+ oases)
    $qAchOasis = $database->query("SELECT COUNT(*) as c FROM " . TB_PREFIX . "odata WHERE owner = $achUid");
    $achOasisCount = (int)(($qAchOasis && $r = $qAchOasis->fetch_assoc()) ? $r['c'] : 0);
    if ($achOasisCount >= 3) {
        echo "<tr>
                <td>Oasis Master</td>
                <td>{$achOasisCount} oases</td>
                <td>Wild</td>
                <td><a href='#' onclick=\"insertMedal('[#OASISMASTER]'); return false;\">[#OASISMASTER]</a></td>
              </tr>";
    }

    // 4. Grand Warlord (20,000+ army)
    $qAchArmy = $database->query("
        SELECT MAX(u1+u2+u3+u4+u5+u6+u7+u8+u9+u10) as max_army 
        FROM " . TB_PREFIX . "units u
        JOIN " . TB_PREFIX . "vdata v ON v.wref = u.vref
        WHERE v.owner = $achUid
    ");
    $achMaxArmy = (int)(($qAchArmy && $r = $qAchArmy->fetch_assoc()) ? $r['max_army'] : 0);
    if ($achMaxArmy >= 20000) {
        echo "<tr>
                <td>Grand Warlord</td>
                <td>" . number_format($achMaxArmy) . " troops</td>
                <td>Military</td>
                <td><a href='#' onclick=\"insertMedal('[#WARLORD]'); return false;\">[#WARLORD]</a></td>
              </tr>";
    }

    // 5. Master of Whispers (2,000+ scouts)
    $qAchScout = $database->query("
        SELECT MAX(u4+u14+u24+u54+u64+u74+u84+u94) as max_scouts
        FROM " . TB_PREFIX . "units u
        JOIN " . TB_PREFIX . "vdata v ON v.wref = u.vref
        WHERE v.owner = $achUid
    ");
    $achMaxScouts = (int)(($qAchScout && $r = $qAchScout->fetch_assoc()) ? $r['max_scouts'] : 0);
    if ($achMaxScouts >= 2000) {
        echo "<tr>
                <td>Master of Whispers</td>
                <td>" . number_format($achMaxScouts) . " scouts</td>
                <td>Intel</td>
                <td><a href='#' onclick=\"insertMedal('[#SCOUTMASTER]'); return false;\">[#SCOUTMASTER]</a></td>
              </tr>";
    }

    // 6. Imperium (5+ settlements)
    $qAchVil = $database->query("SELECT COUNT(*) as total_vils FROM " . TB_PREFIX . "vdata WHERE owner = $achUid");
    $achTotalVils = (int)(($qAchVil && $r = $qAchVil->fetch_assoc()) ? $r['total_vils'] : 0);
    if ($achTotalVils >= 5) {
        echo "<tr>
                <td>Imperium</td>
                <td>{$achTotalVils} villages</td>
                <td>Empire</td>
                <td><a href='#' onclick=\"insertMedal('[#IMPERIUM]'); return false;\">[#IMPERIUM]</a></td>
              </tr>";
    }

    // 7. Metropolis (1,000+ pop)
    $qAchMetro = $database->query("SELECT MAX(pop) as max_pop FROM " . TB_PREFIX . "vdata WHERE owner = $achUid");
    $achMaxPop = (int)(($qAchMetro && $r = $qAchMetro->fetch_assoc()) ? $r['max_pop'] : 0);
    if ($achMaxPop >= 1000) {
        echo "<tr>
                <td>Metropolis</td>
                <td>" . number_format($achMaxPop) . " pop</td>
                <td>City</td>
                <td><a href='#' onclick=\"insertMedal('[#METROPOLIS]'); return false;\">[#METROPOLIS]</a></td>
              </tr>";
    }

    // 8. Economy Tycoon (200,000+ storage)
    $qAchTycoon = $database->query("SELECT MAX(maxstore + maxcrop) as max_storage FROM " . TB_PREFIX . "vdata WHERE owner = $achUid");
    $achMaxStorage = (int)(($qAchTycoon && $r = $qAchTycoon->fetch_assoc()) ? $r['max_storage'] : 0);
    if ($achMaxStorage >= 200000) {
        echo "<tr>
                <td>Economy Tycoon</td>
                <td>" . number_format($achMaxStorage) . " cap</td>
                <td>Economy</td>
                <td><a href='#' onclick=\"insertMedal('[#TYCOON]'); return false;\">[#TYCOON]</a></td>
              </tr>";
    }

    // 9. Hero Veteran (Hero Level >= 50)
    $qAchHero50 = $database->query("SELECT level FROM " . TB_PREFIX . "hero WHERE uid = $achUid LIMIT 1");
    $achHeroLvl = (int)(($qAchHero50 && $r = $qAchHero50->fetch_assoc()) ? $r['level'] : 0);
    if ($achHeroLvl >= 50) {
        echo "<tr>
                <td>Hero Veteran</td>
                <td>Level {$achHeroLvl}</td>
                <td>Hero</td>
                <td><a href='#' onclick=\"insertMedal('[#HERO50]'); return false;\">[#HERO50]</a></td>
              </tr>";
    }

    // 10. Grand Forge Master (Tier 3 or enhanced item)
    $qAchForge = $database->query("SELECT COUNT(*) as c FROM " . TB_PREFIX . "hero_items WHERE uid = $achUid AND (itemid % 3 = 0 OR stat_value > 0) AND slot > 0");
    $achForgeItems = (int)(($qAchForge && $r = $qAchForge->fetch_assoc()) ? $r['c'] : 0);
    if ($achForgeItems >= 1) {
        echo "<tr>
                <td>Forge Master</td>
                <td>{$achForgeItems} items</td>
                <td>Equipment</td>
                <td><a href='#' onclick=\"insertMedal('[#FORGEMASTER]'); return false;\">[#FORGEMASTER]</a></td>
              </tr>";
    }

    // 11. Alliance Philanthropist (500,000+ donated resources)
    $qAchDonation = $database->query("SELECT SUM(amount) as total_donated FROM " . TB_PREFIX . "alliance_donation WHERE uid = $achUid");
    $achTotalDonated = (int)(($qAchDonation && $r = $qAchDonation->fetch_assoc()) ? $r['total_donated'] : 0);
    if ($achTotalDonated >= 500000) {
        echo "<tr>
                <td>Alliance Philanthropist</td>
                <td>" . number_format($achTotalDonated) . " res</td>
                <td>Alliance</td>
                <td><a href='#' onclick=\"insertMedal('[#PHILANTHROPIST]'); return false;\">[#PHILANTHROPIST]</a></td>
              </tr>";
    }
}
?>

</table>
</p>

<!-- JS -->
<script>
let lastActiveProfileTextarea = 'be1';
document.addEventListener('DOMContentLoaded', function() {
    const t1 = document.querySelector('textarea[name="be1"]');
    const t2 = document.querySelector('textarea[name="be2"]');
    if (t1) t1.addEventListener('focus', function() { lastActiveProfileTextarea = 'be1'; });
    if (t2) t2.addEventListener('focus', function() { lastActiveProfileTextarea = 'be2'; });
});

function insertMedal(code) {
    const textarea = document.querySelector('textarea[name="' + lastActiveProfileTextarea + '"]') || document.querySelector('textarea[name="be1"]');
    if (!textarea) return;

    textarea.focus();

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;

    textarea.value =
        textarea.value.substring(0, start) +
        code +
        textarea.value.substring(end);

    textarea.selectionStart = textarea.selectionEnd = start + code.length;
}
</script>

<p class="btn">
<input type="image" name="s1" id="btn_ok"
class="dynamic_img" src="img/x.gif" alt="OK">
</p>

</form>
