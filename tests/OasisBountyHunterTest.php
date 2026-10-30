<?php
/**
 * Test Suite: Oasis Nature Bounty Hunter
 * Tests OasisBounty calculation, daily capping at 150 Silver, and database persistence.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';
require_once __DIR__ . '/../GameEngine/OasisBounty.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running OasisBountyHunterTest ===\n";

$db = $database->dblink;
$testUid = 99971;

// Cleanup test user
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "oasis_bounty_daily` WHERE uid = $testUid");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES ($testUid, 'OasisHunter', 'p', 'hunter@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES ($testUid, $testUid, 'HunterHero', 1, 1, 50)");

// 1. Test calculation formula
// 10 rats (u31 = 1 sil each) = 10
// 5 wolves (u36 = 2 sil each) = 10
// 3 bears (u37 = 4 sil each) = 12
// 2 elephants (u40 = 8 sil each) = 16
// Total = 48 silver
$killed1 = [
    31 => 10,
    36 => 5,
    37 => 3,
    40 => 2
];
$calc1 = OasisBounty::calculateBounty($killed1);
assertTest($calc1 === 48, "Bounty calculated properly: 10 + 10 + 12 + 16 = 48 Silver");

// 2. Award first bounty
$res1 = OasisBounty::awardBounty($testUid, $killed1, 1);
assertTest($res1['awarded'] === 48, "First bounty awarded is 48");
assertTest($res1['daily_total'] === 48, "Daily total is 48");
assertTest(BlackMarket::getSilver($testUid) === 98, "Hero silver increased to 98 (50 + 48)");

// 3. Award second bounty close to cap (e.g. 80 silver -> daily total 128)
// 10 elephants (80 sil)
$killed2 = [40 => 10];
$res2 = OasisBounty::awardBounty($testUid, $killed2, 1);
assertTest($res2['awarded'] === 80, "Second bounty awarded is 80");
assertTest($res2['daily_total'] === 128, "Daily total is now 128 (48 + 80)");
assertTest(BlackMarket::getSilver($testUid) === 178, "Hero silver increased to 178");

// 4. Test Daily Cap (Cap is 150):
// Now try to earn 50 silver (5 elephants = 40, 5 bears = 20 -> total 60)
// Remaining cap is 150 - 128 = 22 Silver!
$killed3 = [37 => 5, 40 => 5]; // 20 + 40 = 60
$res3 = OasisBounty::awardBounty($testUid, $killed3, 1);
assertTest($res3['potential'] === 60, "Potential bounty was 60");
assertTest($res3['awarded'] === 22, "Awarded bounty was capped to 22 (150 cap reached)");
assertTest($res3['daily_total'] === 150, "Daily total reached exactly 150");
assertTest($res3['capped'] === true, "Result indicates bounty was capped");
assertTest(BlackMarket::getSilver($testUid) === 200, "Hero silver is now 200 (178 + 22)");

// 5. Further hunting on the same day gives 0 silver (cap exceeded)
$killed4 = [40 => 1];
$res4 = OasisBounty::awardBounty($testUid, $killed4, 1);
assertTest($res4['awarded'] === 0, "Further hunting awards 0 silver when daily cap is full");
assertTest(BlackMarket::getSilver($testUid) === 200, "Hero silver remains 200");

// Cleanup
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "oasis_bounty_daily` WHERE uid = $testUid");

echo "=== All OasisBountyHunterTest tests passed! ===\n";
