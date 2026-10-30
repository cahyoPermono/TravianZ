<?php

#################################################################################
##  Filename       : tests/BotNusantaraParanormalNaturalTest.php               ##
##  Type           : Unit Test for Natural Nusantara Bot Paranormal Mechanics  ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';
require_once dirname(__DIR__) . '/GameEngine/Paranormal.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running BotNusantaraParanormalNaturalTest ===" . PHP_EOL;

// Mock database to simulate world, building, resource, and paranormal tables
class MockParanormalDatabase {
    public $users = [];
    public $vdata = [];
    public $fdata = [];
    public $paranormal = [];
    public $queries = [];

    public function escape($str) {
        return addslashes($str);
    }

    public function query($sql) {
        $this->queries[] = $sql;

        // Simulate UPDATE fdata
        if (preg_match('/UPDATE\s+.*fdata\s+SET\s+(.+)\s+WHERE\s+vref\s*=\s*(\d+)/i', $sql, $m)) {
            $wid = (int)$m[2];
            $assignments = explode(',', $m[1]);
            foreach ($assignments as $a) {
                if (preg_match('/(\w+)\s*=\s*(\d+)/', trim($a), $sub)) {
                    $this->fdata[$wid][$sub[1]] = (int)$sub[2];
                }
            }
            return true;
        }

        // Simulate UPDATE paranormal
        if (preg_match('/UPDATE\s+[`\w]*paranormal[`\w]*\s+SET\s+(.+)\s+WHERE\s+[`\w]*vref[`\w]*\s*=\s*(\d+)/i', $sql, $m)) {
            $wid = (int)$m[2];
            $assignments = explode(',', $m[1]);
            foreach ($assignments as $a) {
                $trimmed = trim($a);
                if (preg_match('/`?(\w+)`?\s*=\s*`?(\w+)`?\s*\+\s*(\d+)/', $trimmed, $sub)) {
                    $field = $sub[1];
                    $inc = (int)$sub[3];
                    $this->paranormal[$wid][$field] = ($this->paranormal[$wid][$field] ?? 0) + $inc;
                } elseif (preg_match('/`?(\w+)`?\s*=\s*(\d+)/', $trimmed, $sub)) {
                    $field = $sub[1];
                    $val = (int)$sub[2];
                    $this->paranormal[$wid][$field] = $val;
                }
            }
            return true;
        }

        // Simulate SELECT FROM paranormal
        if (preg_match('/SELECT\s+.*FROM\s+[`\w]*paranormal[`\w]*\s+WHERE\s+[`\w]*vref[`\w]*\s*=\s*(\d+)/i', $sql, $m)) {
            $wid = (int)$m[1];
            if (isset($this->paranormal[$wid])) {
                return [$this->paranormal[$wid]];
            }
            return [];
        }

        return true;
    }

    public function query_return($sql) {
        $this->queries[] = $sql;
        if (preg_match('/SELECT\s+.*FROM\s+[`\w]*paranormal[`\w]*\s+WHERE\s+[`\w]*vref[`\w]*\s*=\s*(\d+)/i', $sql, $m)) {
            $wid = (int)$m[1];
            if (isset($this->paranormal[$wid])) {
                return [$this->paranormal[$wid]];
            }
            return [];
        }
        return [];
    }

    public function getResourceLevel($wid, $use_cache = true) {
        return $this->fdata[$wid] ?? null;
    }

    public function getVillageFields($wid, $fields, $use_cache = true) {
        return $this->vdata[$wid] ?? null;
    }

    public function modifyResource($vid, $wood, $clay, $iron, $crop, $mode, $setLastupdate = false) {
        if (!isset($this->vdata[$vid])) return false;
        $sign = (!$mode ? -1 : 1);
        $this->vdata[$vid]['wood'] = max(0, $this->vdata[$vid]['wood'] + ($sign * $wood));
        $this->vdata[$vid]['clay'] = max(0, $this->vdata[$vid]['clay'] + ($sign * $clay));
        $this->vdata[$vid]['iron'] = max(0, $this->vdata[$vid]['iron'] + ($sign * $iron));
        $this->vdata[$vid]['crop'] = max(0, $this->vdata[$vid]['crop'] + ($sign * $crop));
        return true;
    }
}

$mockDb = new MockParanormalDatabase();
$GLOBALS['database'] = $mockDb;

// ------------------------------------------------------------------------------
// TEST 1: Building Prerequisites for Sanggar Paranormal (GID 51)
// ------------------------------------------------------------------------------
echo "Test 1: Sanggar Paranormal Building Prerequisites... ";

// Case 1A: Roman bot (tribe = 1) must never build Sanggar Paranormal
$romanBot = [
    'id' => 10,
    'wref' => 1001,
    'tribe' => 1,
    'pop' => 80,
    'wood' => 2000, 'clay' => 2000, 'iron' => 2000, 'crop' => 2000,
    'created' => time() - (86400 * 3), // 3 days old
];
$fdataRoman = ['vref' => 1001];
for ($i = 1; $i <= 40; $i++) { $fdataRoman['f' . $i] = 3; $fdataRoman['f' . $i . 't'] = 0; }
$fdataRoman['f26'] = 5; $fdataRoman['f26t'] = 15; // MB lvl 5
$fdataRoman['f31'] = 5; $fdataRoman['f31t'] = 23; // Cranny lvl 5
$fdataRoman['f38'] = 0; $fdataRoman['f38t'] = 0;
$mockDb->fdata[1001] = $fdataRoman;
$mockDb->vdata[1001] = $romanBot;

// Run processBuild multiple times to bypass pacing roll
$builtGID51Roman = false;
for ($r = 0; $r < 25; $r++) {
    $res = BotAI::processBuild($romanBot);
    if ($res && strpos($res, 'Sanggar Paranormal') !== false) {
        $builtGID51Roman = true;
        break;
    }
}
assertTrue(!$builtGID51Roman, "Roman bot must never build Sanggar Paranormal");

// Case 1B: Nusantara bot (tribe = 10) with MB < 3 cannot build Sanggar Paranormal
$nusantaraBot = [
    'id' => 20,
    'wref' => 2001,
    'tribe' => 10,
    'pop' => 50,
    'wood' => 2000, 'clay' => 2000, 'iron' => 2000, 'crop' => 2000,
    'created' => time() - (86400 * 2), // 2 days old
];
$fdataNus = ['vref' => 2001];
for ($i = 1; $i <= 40; $i++) { $fdataNus['f' . $i] = 2; $fdataNus['f' . $i . 't'] = 0; }
$fdataNus['f26'] = 2; $fdataNus['f26t'] = 15; // MB lvl 2 (prerequisite is 3)
$fdataNus['f31'] = 3; $fdataNus['f31t'] = 23; // Cranny lvl 3
$fdataNus['f38'] = 0; $fdataNus['f38t'] = 0;
$mockDb->fdata[2001] = $fdataNus;
$mockDb->vdata[2001] = $nusantaraBot;

$builtPrematureMB = false;
for ($r = 0; $r < 25; $r++) {
    $mockDb->fdata[2001]['f26'] = 2; // keep MB at 2 for this prerequisite test
    $res = BotAI::processBuild($nusantaraBot);
    if ($res && strpos($res, 'Sanggar Paranormal') !== false) {
        $builtPrematureMB = true;
        break;
    }
}
assertTrue(!$builtPrematureMB, "Nusantara bot with MB < 3 must NOT build Sanggar Paranormal");

// Case 1C: Nusantara bot with MB >= 3 and Cranny < 3 naturally upgrades Cranny first
$fdataNus['f26'] = 3; // MB lvl 3
$fdataNus['f31'] = 1; // Cranny lvl 1 (needs lvl 3)
$mockDb->fdata[2001] = $fdataNus;
$mockDb->vdata[2001] = $nusantaraBot;

$upgradedCranny = false;
for ($r = 0; $r < 25; $r++) {
    $res = BotAI::processBuild($nusantaraBot);
    if ($res && strpos($res, 'Cranny') !== false && strpos($res, 'Prerequisite') !== false) {
        $upgradedCranny = true;
        break;
    }
}
assertTrue($upgradedCranny, "Nusantara bot must naturally prioritize upgrading Cranny to lvl 3 for Sanggar Paranormal");

// Case 1D: Nusantara bot with age < 1.5 days does not build GID 51 prematurely
$youngNusantaraBot = [
    'id' => 21,
    'wref' => 2002,
    'tribe' => 10,
    'pop' => 30,
    'wood' => 2000, 'clay' => 2000, 'iron' => 2000, 'crop' => 2000,
    'created' => time() - (86400 * 0.5), // 0.5 day old
];
$fdataYoung = ['vref' => 2002];
for ($i = 1; $i <= 40; $i++) { $fdataYoung['f' . $i] = 2; $fdataYoung['f' . $i . 't'] = 0; }
$fdataYoung['f26'] = 3; $fdataYoung['f26t'] = 15; // MB lvl 3
$fdataYoung['f31'] = 3; $fdataYoung['f31t'] = 23; // Cranny lvl 3
$fdataYoung['f38'] = 0; $fdataYoung['f38t'] = 0;
$mockDb->fdata[2002] = $fdataYoung;
$mockDb->vdata[2002] = $youngNusantaraBot;

$builtDay0 = false;
for ($r = 0; $r < 25; $r++) {
    $mockDb->fdata[2002]['f38'] = 0;
    $res = BotAI::processBuild($youngNusantaraBot);
    if ($res && strpos($res, 'Sanggar Paranormal') !== false) {
        $builtDay0 = true;
        break;
    }
}
assertTrue(!$builtDay0, "Day 0.5 Nusantara bot must NOT construct Sanggar Paranormal prematurely before day 1.5 maturity");

// Case 1E: Nusantara bot with insufficient resources cannot build GID 51
$poorNusantaraBot = [
    'id' => 22,
    'wref' => 2003,
    'tribe' => 10,
    'pop' => 50,
    'wood' => 50, 'clay' => 50, 'iron' => 50, 'crop' => 50, // Less than 150/120/160/80
    'created' => time() - (86400 * 2),
];
$mockDb->fdata[2003] = $fdataYoung;
$mockDb->fdata[2003]['vref'] = 2003;
$mockDb->vdata[2003] = $poorNusantaraBot;

$builtPoor = false;
for ($r = 0; $r < 25; $r++) {
    $mockDb->fdata[2003]['f38'] = 0;
    $res = BotAI::processBuild($poorNusantaraBot);
    if ($res && strpos($res, 'Sanggar Paranormal') !== false) {
        $builtPoor = true;
        break;
    }
}
assertTrue(!$builtPoor, "Nusantara bot with insufficient resources cannot build Sanggar Paranormal");

// Case 1F: All prerequisites met -> Successfully constructs Sanggar Paranormal on slot 38, deducting resources
$readyBot = [
    'id' => 23,
    'wref' => 2004,
    'tribe' => 10,
    'pop' => 60,
    'wood' => 500, 'clay' => 500, 'iron' => 500, 'crop' => 500,
    'created' => time() - (86400 * 2), // 2 days old
];
$fdataReady = ['vref' => 2004];
for ($i = 1; $i <= 40; $i++) { $fdataReady['f' . $i] = 2; $fdataReady['f' . $i . 't'] = 0; }
$fdataReady['f26'] = 3; $fdataReady['f26t'] = 15; // MB lvl 3
$fdataReady['f31'] = 3; $fdataReady['f31t'] = 23; // Cranny lvl 3
$fdataReady['f38'] = 0; $fdataReady['f38t'] = 0;
$mockDb->fdata[2004] = $fdataReady;
$mockDb->vdata[2004] = $readyBot;

$constructedMsg = null;
for ($r = 0; $r < 30; $r++) {
    $res = BotAI::processBuild($readyBot);
    if ($res && strpos($res, 'Constructed Sanggar Paranormal (slot 38) lvl 1') !== false) {
        $constructedMsg = $res;
        break;
    }
}
assertTrue($constructedMsg !== null, "Nusantara bot should construct Sanggar Paranormal lvl 1 on slot 38");
assertEqual(1, (int)$mockDb->fdata[2004]['f38'], "Slot 38 level must now be 1");
assertEqual(51, (int)$mockDb->fdata[2004]['f38t'], "Slot 38 type must be 51 (Sanggar Paranormal)");
assertEqual(350, (int)$mockDb->vdata[2004]['wood'], "Wood should be reduced by 150 (500 - 150 = 350)");
assertEqual(380, (int)$mockDb->vdata[2004]['clay'], "Clay should be reduced by 120 (500 - 120 = 380)");
assertEqual(340, (int)$mockDb->vdata[2004]['iron'], "Iron should be reduced by 160 (500 - 160 = 340)");
assertEqual(420, (int)$mockDb->vdata[2004]['crop'], "Crop should be reduced by 80 (500 - 80 = 420)");

echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: Natural Astral Spirit Summoning (Pocong, Kuntilanak, Genderuwo)
// ------------------------------------------------------------------------------
echo "Test 2: Natural Astral Spirit Summoning & Capacity Limits... ";

$widSpirits = 3001;
$botSpirits = [
    'id' => 30,
    'wref' => $widSpirits,
    'tribe' => 10,
    'pop' => 120,
    'wood' => 2000, 'clay' => 2000, 'iron' => 2000, 'crop' => 2000,
    'created' => time() - (86400 * 3),
];

// 2A: Cannot summon if Sanggar Paranormal is not built
$mockDb->fdata[$widSpirits] = ['vref' => $widSpirits, 'f38' => 0, 'f38t' => 0];
$mockDb->vdata[$widSpirits] = $botSpirits;
$mockDb->paranormal[$widSpirits] = ['vref' => $widSpirits, 'pocong' => 0, 'kuntilanak' => 0, 'genderuwo' => 0, 'tuyul_until' => 0];

$noBuildingRes = BotAI::processParanormal($botSpirits);
assertTrue($noBuildingRes === null, "Cannot summon spirits if Sanggar Paranormal is not built");

// 2B: Cannot summon if resources are below safe reserve (< 100)
$mockDb->fdata[$widSpirits]['f38'] = 1;
$mockDb->fdata[$widSpirits]['f38t'] = 51; // Sanggar Lvl 1 (Cap: 10 slots)
$mockDb->vdata[$widSpirits]['wood'] = 80; // Below reserve (100)
$mockDb->vdata[$widSpirits]['clay'] = 80;
$mockDb->vdata[$widSpirits]['iron'] = 80;
$mockDb->vdata[$widSpirits]['crop'] = 80;

$starvingRes = BotAI::processParanormal($botSpirits);
assertTrue($starvingRes === null, "Cannot summon spirits if village resources are below safe reserve");

// 2C: Natural summoning with sufficient surplus resources
$mockDb->vdata[$widSpirits]['wood'] = 1000;
$mockDb->vdata[$widSpirits]['clay'] = 1000;
$mockDb->vdata[$widSpirits]['iron'] = 1000;
$mockDb->vdata[$widSpirits]['crop'] = 1000;

$summonSuccess = false;
for ($s = 0; $s < 30; $s++) {
    $res = BotAI::processParanormal($botSpirits);
    if ($res && strpos($res, 'Summoned natural astral spirit') !== false) {
        $summonSuccess = true;
        break;
    }
}
assertTrue($summonSuccess, "Bot should naturally summon astral spirits when capacity and resources permit");
$spiritsNow = $mockDb->paranormal[$widSpirits];
$totSpirits = (int)$spiritsNow['pocong'] + (int)$spiritsNow['kuntilanak'] + (int)$spiritsNow['genderuwo'];
assertTrue($totSpirits >= 1 && $totSpirits <= 2, "Should summon in small natural batches of 1-2 spirits (got $totSpirits)");
assertTrue($mockDb->vdata[$widSpirits]['wood'] < 1000, "Genuine wood resources must have been deducted");
assertTrue($mockDb->vdata[$widSpirits]['crop'] < 1000, "Genuine crop resources must have been deducted");

// 2D: Cannot summon when maximum slot capacity is reached
$mockDb->paranormal[$widSpirits]['pocong'] = 10; // 10 slots at level 1 = 100% full
$capFullRes = BotAI::processParanormal($botSpirits);
assertTrue($capFullRes === null, "Cannot summon when spirit slot capacity is at 100%");

// 2E: Genderuwo requires Sanggar Paranormal Level >= 3 and 2 slots
$mockDb->fdata[$widSpirits]['f38'] = 3; // Lvl 3 (Cap: 30 slots)
$mockDb->paranormal[$widSpirits] = ['vref' => $widSpirits, 'pocong' => 0, 'kuntilanak' => 0, 'genderuwo' => 0, 'tuyul_until' => 0];
$mockDb->vdata[$widSpirits] = [
    'wref' => $widSpirits,
    'wood' => 5000, 'clay' => 5000, 'iron' => 5000, 'crop' => 5000,
];

$genderuwoFound = false;
for ($s = 0; $s < 50; $s++) {
    $res = BotAI::processParanormal($botSpirits);
    if ($res && strpos($res, 'Genderuwo') !== false) {
        $genderuwoFound = true;
        break;
    }
}
assertTrue($genderuwoFound, "Nusantara bot with Sanggar Paranormal lvl >= 3 can summon heavy Genderuwo defender");

echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Khodam Tuyul Natural Ritual Activation (+15% Raid Multiplier)
// ------------------------------------------------------------------------------
echo "Test 3: Khodam Tuyul Ritual Natural Activation... ";

$widTuyul = 4001;
$botTuyul = [
    'id' => 40,
    'wref' => $widTuyul,
    'tribe' => 10,
    'pop' => 180,
    'wood' => 4000, 'clay' => 4000, 'iron' => 4000, 'crop' => 4000, // Rich surplus
    'created' => time() - (86400 * 4),
];
$mockDb->fdata[$widTuyul] = ['vref' => $widTuyul, 'f38' => 3, 'f38t' => 51]; // Sanggar lvl 3
$mockDb->vdata[$widTuyul] = $botTuyul;
$mockDb->paranormal[$widTuyul] = ['vref' => $widTuyul, 'pocong' => 5, 'kuntilanak' => 5, 'genderuwo' => 2, 'tuyul_until' => 0];

$tuyulActivated = false;
for ($t = 0; $t < 40; $t++) {
    $res = BotAI::processParanormal($botTuyul);
    if ($res && strpos($res, 'Khodam Tuyul') !== false) {
        $tuyulActivated = true;
        break;
    }
}
assertTrue($tuyulActivated, "Bot should naturally activate Khodam Tuyul ritual with abundant surplus resources");
assertTrue($mockDb->paranormal[$widTuyul]['tuyul_until'] > time(), "tuyul_until must be in the future");

// Verify battle raid multiplier applies Tuyul +15%
$multiplier = Paranormal::getRaidMultiplier($widTuyul);
assertEqual(1.15, $multiplier, "Paranormal::getRaidMultiplier must return 1.15 while Tuyul is active");

echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 4: getParanormalStatus & Diagnostics
// ------------------------------------------------------------------------------
echo "Test 4: getParanormalStatus Diagnostic Reporting... ";

$status = BotAI::getParanormalStatus($widTuyul);
assertEqual(3, $status['level'], "Status level must be 3");
assertEqual(30, $status['max_cap'], "Status max cap must be 30 slots");
assertTrue($status['tuyul_active'], "Tuyul must be reported as active");
assertTrue($status['used_slots'] > 0, "Used slots must be greater than 0");
assertTrue($status['free_slots'] > 0, "Free slots must be greater than 0");

echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 5: Overgrown Rebalance Normalization (Anti-sudden Cheating)
// ------------------------------------------------------------------------------
echo "Test 5: Village Rebalance & Anti-Overflow Normalization... ";

$widNorm = 5001;
$botNorm = [
    'id' => 50,
    'wref' => $widNorm,
    'tribe' => 10,
    'pop' => 900, // Overgrown
    'created' => time() - (86400 * 2), // 2 days old
];
// Overgrown spirits: 80 spirits in a 2-day-old village (unnatural)
$mockDb->paranormal[$widNorm] = [
    'vref' => $widNorm,
    'pocong' => 40,
    'kuntilanak' => 40,
    'genderuwo' => 10,
    'tuyul_until' => 0
];
$mockDb->fdata[$widNorm] = ['vref' => $widNorm];
for ($i = 1; $i <= 40; $i++) { $mockDb->fdata[$widNorm]['f' . $i] = 10; $mockDb->fdata[$widNorm]['f' . $i . 't'] = 0; }

// Run rebalance
BotAI::rebalanceVillageBuildings($widNorm, 10, 2.0);

$normFdata = $mockDb->fdata[$widNorm];
assertTrue((int)$normFdata['f38'] > 0, "Sanggar Paranormal must be maintained at natural level for 2-day-old Nusantara bot");
assertEqual(51, (int)$normFdata['f38t'], "Slot 38 type must be 51");

$normSpirits = $mockDb->paranormal[$widNorm];
$normUsed = Paranormal::getUsedSlots($normSpirits);
$capAtLvl = Paranormal::getMaxCapacity((int)$normFdata['f38']);
assertTrue($normUsed <= $capAtLvl, "Overgrown spirits must be trimmed down to building slot capacity ($normUsed <= $capAtLvl)");

echo "PASS" . PHP_EOL;

echo "=== All BotNusantaraParanormalNaturalTest tests passed successfully! ===" . PHP_EOL;
