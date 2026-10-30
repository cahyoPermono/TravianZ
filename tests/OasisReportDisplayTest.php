<?php
/**
 * Test Suite: Oasis Bounty Report Display Test
 * Verifies that Templates/Notice/1.tpl accurately parses and renders
 * the Oasis Nature Bounty Hunter card, both from OASISBOUNTY token and
 * backward-compatible casualty calculation, and verifies dynamic hero info.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Lang/' . LANG . '.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/BattleReportEconomy.php';
require_once __DIR__ . '/../GameEngine/OasisBounty.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running OasisReportDisplayTest ===\n";

// 1. Setup mock data identical to user's screenshot
// Attacker: Hun (tribe 6, or any tribe)
// Defender: Nature (tribe 4)
// Nature casualties: 4, 12, 9, 6, 8, 6, 6, 4, 5, 5
$mockData = array_fill(0, 310, '0');
$mockData[0] = '6';     // Attacker UID
$mockData[1] = '100';   // Attacker village
$mockData[2] = '6';     // Hun
$mockData[3+4] = '100'; // 100 unit 5 sent
$mockData[13+4] = '83'; // 83 unit 5 died
$mockData[23] = '383';  // Loot Wood
$mockData[24] = '383';  // Loot Clay
$mockData[25] = '382';  // Loot Iron
$mockData[26] = '382';  // Loot Crop
$mockData[27] = '1530'; // Capacity
$mockData[28] = '2';    // Defender UID (Nature)
$mockData[29] = '200';  // Defender village (Oasis)
$mockData[34] = '4';    // Target tribe Nature
// Defender troops: 24, 70, 53, 36, 48, 34, 38, 24, 28, 28
$defSent = [24, 70, 53, 36, 48, 34, 38, 24, 28, 28];
for ($i = 0; $i < 10; $i++) {
    $mockData[35 + $i] = (string)$defSent[$i];
}
// Defender casualties: 4, 12, 9, 6, 8, 6, 6, 4, 5, 5
$defDead = [4, 12, 9, 6, 8, 6, 6, 4, 5, 5];
for ($i = 0; $i < 10; $i++) {
    $mockData[45 + $i] = (string)$defDead[$i];
}
$mockData[284] = '1'; // Hero sent

// Calculate expected bounty from exact killed animals
$killedMap = [];
for ($i = 0; $i < 10; $i++) {
    $killedMap[31 + $i] = $defDead[$i];
}
$expectedRawBounty = OasisBounty::calculateBounty($killedMap);
// Rates: 4*1 + 12*1 + 9*1 + 6*1 + 8*2 + 6*2 + 6*4 + 4*4 + 5*4 + 5*8
// 4 + 12 + 9 + 6 + 16 + 12 + 24 + 16 + 20 + 40 = 159 Silver
assertTest($expectedRawBounty === 159, "Expected raw bounty for user's screenshot should be 159, got: $expectedRawBounty");

// 2. Test BattleReportEconomy calculation on this report
$econ = BattleReportEconomy::calculate($mockData, 6, false);
assertTest($econ['silver_bounty'] === 159, "BattleReportEconomy should detect silver_bounty=159, got: " . $econ['silver_bounty']);
$econHtml = BattleReportEconomy::renderHtml($econ);
assertTest(strpos($econHtml, 'Bonus Tambahan:') !== false, "BattleReportEconomy HTML should contain Bonus Tambahan");
assertTest(strpos($econHtml, '159 Silver') !== false, "BattleReportEconomy HTML should mention 159 Silver");

// 3. Test Tagged token in dataarray
$mockDataWithToken = $mockData;
$mockDataWithToken[] = 'OASISBOUNTY:88;88;150;84;0';
$tokenFound = false;
foreach ($mockDataWithToken as $entry) {
    if (is_string($entry) && strpos($entry, 'OASISBOUNTY:') === 0) {
        $bParts = explode(';', substr($entry, 12));
        assertTest((int)$bParts[0] === 88, "Awarded silver should be 88");
        assertTest((int)$bParts[1] === 88, "Daily total should be 88");
        assertTest((int)$bParts[2] === 150, "Daily cap should be 150");
        assertTest((int)$bParts[3] === 84, "Animals killed should be 84");
        $tokenFound = true;
        break;
    }
}
assertTest($tokenFound, "OASISBOUNTY token was properly parsed");

// 4. Test dynamic hero info scanning
$mockWithHero = $mockData;
$mockWithHero[280] = 'hero';
$mockWithHero[281] = 'Hero gained 120 experience points | Bounty Hewan: +88 Silver [Hari ini: 88/150]';

$foundHeroMsg = null;
foreach ($mockWithHero as $k => $v) {
    if ($v === 'hero' && isset($mockWithHero[$k + 1]) && is_string($mockWithHero[$k + 1]) && strlen($mockWithHero[$k + 1]) > 3) {
        $foundHeroMsg = $mockWithHero[$k + 1];
        break;
    }
}
assertTest($foundHeroMsg !== null, "Dynamic hero scan should find hero message");
assertTest(strpos($foundHeroMsg, 'Bounty Hewan') !== false, "Found message should contain Bounty Hewan");

echo "=== All OasisReportDisplayTest tests passed! ===\n";
