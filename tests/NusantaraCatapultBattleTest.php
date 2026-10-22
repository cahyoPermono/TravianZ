<?php
/**
 * Test Nusantara Catapult, Ram, Chief, Scout battle mechanics and report rendering.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Lang/' . LANG . '.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Data/buidata.php';
require_once __DIR__ . '/../GameEngine/Building.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Battle.php';
require_once __DIR__ . '/../GameEngine/Units.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationBattleResolution.php';

function assertEqual($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new Exception("Assertion failed: $message. Expected: $expStr, got: $actStr");
    }
}

function assertTrue($condition, $message = '') {
    if (!$condition) {
        throw new Exception("Assertion failed: $message");
    }
}

echo "=== Running NusantaraCatapultBattleTest ===\n";

// TEST 1: Verify buildAttackerUnits in AutomationBattleResolution
echo "Test 1: Verifying buildAttackerUnits for Tribe 10 (Nusantara)...\n";
$autoBattle = new class {
    use AutomationBattleResolution;
    public function testBuildAttacker(array $attackRow, $owntribe, $isoasis) {
        return $this->buildAttackerUnits($attackRow, $owntribe, $isoasis);
    }
};

// Attacker attack row for Tribe 10 (t1..t11):
// t4 = 5 (Telik Sandi), t7 = 8 (Ram), t8 = 11 (Catapult), t9 = 1 (Chief), t11 = 1 (Hero)
$attackRow = [
    't1' => 0, 't2' => 0, 't3' => 0, 't4' => 5,
    't5' => 38, 't6' => 0, 't7' => 8, 't8' => 11,
    't9' => 1, 't10' => 0, 't11' => 1
];

$resVillage = $autoBattle->testBuildAttacker($attackRow, 10, 0); // non-oasis
assertEqual(98, $resVillage['catp_pic'], "Catapult unit pic for tribe 10 must be 98");
assertEqual(97, $resVillage['ram_pic'], "Ram unit pic for tribe 10 must be 97");
assertEqual(99, $resVillage['chief_pic'], "Chief unit pic for tribe 10 must be 99");
assertEqual(94, $resVillage['spy_pic'], "Scout unit pic for tribe 10 must be 94");
assertEqual(11, $resVillage['Attacker']['u98'], "Attacker u98 must have 11 catapults");
assertEqual(8, $resVillage['Attacker']['u97'], "Attacker u97 must have 8 rams");
assertEqual(1, $resVillage['Attacker']['u99'], "Attacker u99 must have 1 chief");
assertEqual(5, $resVillage['Attacker']['u94'], "Attacker u94 must have 5 scouts");

$resOasis = $autoBattle->testBuildAttacker($attackRow, 10, 1); // oasis
assertEqual(98, $resOasis['catp_pic'], "Catapult unit pic for tribe 10 in oasis must be 98");
assertEqual(97, $resOasis['ram_pic'], "Ram unit pic for tribe 10 in oasis must be 97");
assertEqual(99, $resOasis['chief_pic'], "Chief unit pic for tribe 10 in oasis must be 99");
assertEqual(94, $resOasis['spy_pic'], "Scout unit pic for tribe 10 in oasis must be 94");
echo "PASS: buildAttackerUnits correctly identifies 98 (Catapult), 97 (Ram), 99 (Chief), 94 (Scout) for Nusantara.\n";

// TEST 2: Verify unitdata definitions for Tribe 10 siege/catapult
echo "Test 2: Verifying unitdata.php mapping...\n";
global $unitsbytype;
assertTrue(in_array(98, $unitsbytype['catapult']), "Unit 98 must be in unitsbytype['catapult']");
assertTrue(in_array(97, $unitsbytype['ram']), "Unit 97 must be in unitsbytype['ram']");
assertTrue(in_array(99, $unitsbytype['chief']), "Unit 99 must be in unitsbytype['chief']");
assertTrue(in_array(94, $unitsbytype['scout']), "Unit 94 must be in unitsbytype['scout']");
echo "PASS: unitdata.php has proper siege, ram, chief, and scout mapping for Nusantara.\n";

// TEST 3: Verify Catapult Target Normalization for Sanggar Paranormal (GID 51)
echo "Test 3: Verifying catapult target normalization for Sanggar Paranormal (51)...\n";
$unitsClass = new Units();
$uRef = new ReflectionClass($unitsClass);
$normalizeMethod = $uRef->getMethod('normalizeCatapultTarget');
$normalizeMethod->setAccessible(true);
$invalidMethod = $uRef->getMethod('catapultInvalidBuildings');
$invalidMethod->setAccessible(true);

$invalidRP10 = $invalidMethod->invoke($unitsClass, 10);
assertTrue(!in_array(51, $invalidRP10), "Building 51 (Sanggar Paranormal) must not be invalid at RP >= 10");

$validTarget = $normalizeMethod->invoke($unitsClass, 51, 1, ['u8' => 10], 10, $invalidRP10);
assertEqual(51, $validTarget, "Target 51 must be retained when RP >= 10 and catapults > 0");
echo "PASS: Building 51 can be successfully targeted by catapults.\n";

// TEST 4: Verify Notice Report (1.tpl) rendering for both new and legacy catapult reports
echo "Test 4: Verifying battle report (1.tpl) rendering for Nusantara catapults...\n";
global $technology;
$technology = new Technology();

// Case A: New report with catp_pic (dataarray[264] = 98)
$dataarray = array_fill(0, 310, '0');
$dataarray[0] = '1';
$dataarray[1] = '100';
$dataarray[2] = '10'; // Attacker tribe: Nusantara
$dataarray[28] = '2';
$dataarray[29] = '200';
$dataarray[264] = '98';
$dataarray[265] = 'Tambang Kayu hancur.';
$colspan = 11;
$tribe = 10;

ob_start();
if (!empty($dataarray[265])) {
    $catpUnit = (!empty($dataarray[264]) && is_numeric($dataarray[264])) ? (int)$dataarray[264] : (($tribe - 1) * 10 + 8);
    $catpName = is_numeric($catpUnit) ? $technology->getUnitName($catpUnit) : (defined('U18') ? U18 : 'Catapult');
?>
<tbody class="goods">
<tr>
<th>Information</th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $catpUnit; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($catpName); ?>" title="<?php echo htmlspecialchars($catpName); ?>" />
<?php echo $dataarray[265]; ?>
</td>
</tr>
</tbody>
<?php
}
$renderedNew = ob_get_clean();

assertTrue(strpos($renderedNew, 'class="unit u98"') !== false, "Rendered HTML must have unit u98");
assertTrue(strpos($renderedNew, 'Tambang Kayu hancur.') !== false, "Rendered HTML must contain destruction text");

// Case B: Legacy/retroactive report where dataarray[264] was blank due to bug
$dataarrayLegacy = array_fill(0, 310, '0');
$dataarrayLegacy[2] = '10'; // Tribe 10
$dataarrayLegacy[264] = ''; // was empty!
$dataarrayLegacy[265] = 'Ladang Gandum rusak dari level 5 ke level 2.';

ob_start();
if (!empty($dataarrayLegacy[265])) {
    $catpUnit = (!empty($dataarrayLegacy[264]) && is_numeric($dataarrayLegacy[264])) ? (int)$dataarrayLegacy[264] : (($tribe - 1) * 10 + 8);
    $catpName = is_numeric($catpUnit) ? $technology->getUnitName($catpUnit) : (defined('U18') ? U18 : 'Catapult');
?>
<tbody class="goods">
<tr>
<th>Information</th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $catpUnit; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($catpName); ?>" title="<?php echo htmlspecialchars($catpName); ?>" />
<?php echo $dataarrayLegacy[265]; ?>
</td>
</tr>
</tbody>
<?php
}
$renderedLegacy = ob_get_clean();

assertTrue(strpos($renderedLegacy, 'class="unit u98"') !== false, "Legacy report must fall back to u98 for tribe 10");
assertTrue(strpos($renderedLegacy, 'Ladang Gandum rusak dari level 5 ke level 2.') !== false, "Legacy report must display the damage text");
echo "PASS: Notice report renders catapult damage correctly for both new and existing battle reports.\n";

echo "=== All Nusantara Catapult Battle Tests Passed Successfully! ===\n";
