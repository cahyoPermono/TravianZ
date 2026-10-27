<?php
declare(strict_types=1);

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Automation.php';
require_once __DIR__ . '/../GameEngine/Alliance.php';

echo "Running AllianceCapacityAndEmbassyFixTest...\n";

global $database;

// Test 1: updateAllianceMax defensively enforces at least 9
$conn = $database->dblink;

// Setup dummy user as leader
$dummyLeaderUid = 999901;
$dummyMemberUid = 999902;
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id IN ($dummyLeaderUid, $dummyMemberUid)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "alidata WHERE tag = 'TESTCAP'");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "ali_invite WHERE uid IN ($dummyLeaderUid, $dummyMemberUid)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "ali_permission WHERE uid IN ($dummyLeaderUid, $dummyMemberUid)");

mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, password, email, tribe, access, gold, alliance) VALUES ($dummyLeaderUid, 'LeaderTest', 'x', 'l@t.com', 1, 2, 0, 0)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, password, email, tribe, access, gold, alliance) VALUES ($dummyMemberUid, 'MemberTest', 'x', 'm@t.com', 1, 2, 0, 0)");

// Create alliance
$aid = $database->createAlliance('TESTCAP', 'Test Capacity Alliance', $dummyLeaderUid, 9);
assert($aid > 0, "Alliance creation failed");
mysqli_query($conn, "UPDATE " . TB_PREFIX . "users SET alliance = $aid WHERE id = $dummyLeaderUid");

echo "[PASS] Test setup: Alliance created with ID $aid\n";

// Test 2: DatabaseBuildingQueries::updateAllianceMax does not allow setting < 9
$database->updateAllianceMax($dummyLeaderUid, 0);
$checkRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max FROM " . TB_PREFIX . "alidata WHERE id = $aid"));
assert((int)$checkRow['max'] === 9, "updateAllianceMax with 0 must be clamped to 9! Got: " . $checkRow['max']);
echo "[PASS] updateAllianceMax clamped 0 to 9\n";

// Test 3: Automation::updateMax with leader having no embassy clamps to 9
// Force DB max to 0 directly via raw SQL to simulate legacy corrupted row
mysqli_query($conn, "UPDATE " . TB_PREFIX . "alidata SET max = 0 WHERE id = $aid");
$database->clearQueryCache('alliance');
Automation::updateMax($dummyLeaderUid);
$checkRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max FROM " . TB_PREFIX . "alidata WHERE id = $aid"));
assert((int)$checkRow['max'] >= 9, "Automation::updateMax must enforce minimum 9 for existing alliance! Got: " . $checkRow['max']);
echo "[PASS] Automation::updateMax clamped corrupted 0 capacity to at least 9\n";

// Test 4: Automation::updateMax with leader having highest Embassy level across villages
// Create 2 test villages for leader
$vref1 = 99991;
$vref2 = 99992;
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vref1, $vref2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($vref1, $vref2)");

mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop) VALUES ($vref1, $dummyLeaderUid, 'Village 1', 1, 100)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop) VALUES ($vref2, $dummyLeaderUid, 'Village 2', 0, 100)");

// In Village 1: Embassy Level 5 (Capacity = 15)
// In Village 2: Embassy Level 1 (Capacity = 0 if alone)
$fSql1 = "INSERT INTO " . TB_PREFIX . "fdata (vref, f19, f19t) VALUES ($vref1, 5, 18)";
$fSql2 = "INSERT INTO " . TB_PREFIX . "fdata (vref, f19, f19t) VALUES ($vref2, 1, 18)";
mysqli_query($conn, $fSql1);
mysqli_query($conn, $fSql2);

$database->clearResourseLevelsCache();
Automation::updateMax($dummyLeaderUid);
$checkRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max FROM " . TB_PREFIX . "alidata WHERE id = $aid"));
assert((int)$checkRow['max'] === 15, "Automation::updateMax must pick the highest embassy (lvl 5 -> 15 members)! Got: " . $checkRow['max']);
echo "[PASS] Automation::updateMax correctly calculated 15 members from highest embassy\n";

// Test 5: getAlliance self-heals corrupted row
// Corrupt row back to 0
mysqli_query($conn, "UPDATE " . TB_PREFIX . "alidata SET max = 0 WHERE id = $aid");
$database->clearQueryCache('alliance');
$healedAlly = $database->getAlliance($aid, false);
assert((int)$healedAlly['max'] >= 9, "getAlliance must self-heal max < 9! Got: " . $healedAlly['max']);
$checkRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max FROM " . TB_PREFIX . "alidata WHERE id = $aid"));
assert((int)$checkRow['max'] >= 9, "DB row must be updated on self-heal! Got: " . $checkRow['max']);
echo "[PASS] getAlliance auto-repaired corrupted alliance capacity in memory and database\n";

// Test 6: acceptInvite with simulated corrupted max = 0 auto-heals and joins
// Create invitation for dummyMemberUid
$database->sendInvitation($dummyMemberUid, $aid, $dummyLeaderUid);
$invId = (int)mysqli_insert_id($conn);
assert($invId > 0, "Invite creation failed");

// Corrupt max to 0 directly in DB
mysqli_query($conn, "UPDATE " . TB_PREFIX . "alidata SET max = 0 WHERE id = $aid");
$database->clearQueryCache('alliance');

// Set up mock session as dummyMemberUid
global $session, $form;
$session = new stdClass();
$session->uid = $dummyMemberUid;
$session->username = 'MemberTest';
$session->alliance = 0;
$form = new Form();

$allianceObj = new Alliance();
// Access private acceptInvite via reflection
$reflection = new ReflectionClass(Alliance::class);
$acceptInviteMethod = $reflection->getMethod('acceptInvite');
$acceptInviteMethod->setAccessible(true);

// Set inviteArray on alliance object
$inviteArrayProp = $reflection->getProperty('inviteArray');
$inviteArrayProp->setAccessible(true);
$inviteArrayProp->setValue($allianceObj, [
    ['id' => $invId, 'alliance' => $aid, 'uid' => $dummyMemberUid]
]);

// Execute acceptInvite in child process because it calls exit on successful redirect
$repoPath = addslashes(dirname(__DIR__));
$childScript = "<?php
require_once '{$repoPath}/GameEngine/config.php';
require_once '{$repoPath}/GameEngine/Database.php';
require_once '{$repoPath}/GameEngine/Automation.php';
require_once '{$repoPath}/GameEngine/Alliance.php';

global \$session, \$form;
\$session = new stdClass();
\$session->uid = $dummyMemberUid;
\$session->username = 'MemberTest';
\$session->alliance = 0;
\$form = new Form();

\$allianceObj = new Alliance();
\$reflection = new ReflectionClass(Alliance::class);
\$acceptInviteMethod = \$reflection->getMethod('acceptInvite');
\$acceptInviteMethod->setAccessible(true);
\$inviteArrayProp = \$reflection->getProperty('inviteArray');
\$inviteArrayProp->setAccessible(true);
\$inviteArrayProp->setValue(\$allianceObj, [
    ['id' => $invId, 'alliance' => $aid, 'uid' => $dummyMemberUid]
]);

@\$acceptInviteMethod->invoke(\$allianceObj, ['d' => $invId]);
";

$tmpFile = sys_get_temp_dir() . '/test_ally_accept_' . uniqid() . '.php';
file_put_contents($tmpFile, $childScript);
exec("php " . escapeshellarg($tmpFile), $out, $ret);
@unlink($tmpFile);

// Verify member now belongs to alliance
$updatedUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT alliance FROM " . TB_PREFIX . "users WHERE id = $dummyMemberUid"));
assert((int)$updatedUser['alliance'] === $aid, "Member must successfully join alliance despite initial max=0! Got: " . $updatedUser['alliance']);
echo "[PASS] acceptInvite auto-healed capacity and member joined without 0 members warning\n";

// Cleanup test data
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id IN ($dummyLeaderUid, $dummyMemberUid)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "alidata WHERE id = $aid");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vref1, $vref2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($vref1, $vref2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "ali_permission WHERE alliance = $aid");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "ali_log WHERE aid = $aid");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "ali_invite WHERE alliance = $aid");

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
