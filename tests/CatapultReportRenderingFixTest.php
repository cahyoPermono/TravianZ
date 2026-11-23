<?php
/**
 * Test case specifically verifying the catapult battle report bug fix:
 * 1. Catapult building damage/destruction rendered properly in 1.tpl and 3.tpl
 * 2. Siege unit IDs (57, 58) are not leaked into the Prisoners row
 * 3. 'None of your soldiers returned' is suppressed when viewing as defender
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Lang/' . LANG . '.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Building.php';
require_once __DIR__ . '/../GameEngine/Generator.php';

echo "=== Running CatapultReportRenderingFixTest ===\n";

$technology = new Technology();
$building = new Building();
$generator = new MyGenerator();

// Mock session and database
$session = new stdClass();
$session->uid = 2; // default to defender
$session->access = 0;
$session->plus = 0;

// Build a modern 10-tribe attack report dataset (290 elements)
$dataarray = array_fill(0, 292, '0');
$dataarray[0] = '1'; // Attacker UID = 1
$dataarray[1] = '100'; // Attacker village wref
$dataarray[2] = '6'; // Attacker tribe (Hun)

// Attacker units (Hun: tribe 6)
$dataarray[3] = '510';
$dataarray[4] = '0';
$dataarray[5] = '56';
$dataarray[6] = '0';
$dataarray[7] = '0';
$dataarray[8] = '80';
$dataarray[9] = '18'; // Ram (u57)
$dataarray[10] = '55'; // Catapult (u58)
$dataarray[11] = '0';
$dataarray[12] = '0';

// Attacker dead
for ($i = 3; $i <= 12; $i++) {
    $dataarray[$i + 10] = $dataarray[$i];
}

$dataarray[28] = '2'; // Defender UID = 2
$dataarray[29] = '200'; // Defender village wref
$dataarray[30] = 'star village'; // Defender village name

// Modern offsets (count >= 270)
$dataarray[264] = '57';
$dataarray[265] = 'Wall was not damaged.';
$dataarray[266] = '58';
$dataarray[267] = 'Ladang Gandum <b>hancur</b>.';
$dataarray[268] = '0';
$dataarray[269] = '0';
$dataarray[288] = 'None of your soldiers returned.';

// Mock $message object
$message = new stdClass();
$message->readingNotice = array(
    'id' => 12345,
    'uid' => 2, // defender view
    'to' => 2,
    'topic' => 'Black_Steppe attacks star village',
    'ntype' => 1,
    'data' => implode(',', $dataarray),
    'time' => time(),
    'viewed' => 1,
    'archive' => 0
);

// Test 1: Render 1.tpl as Defender
$session->uid = 2; // Defender UID
ob_start();
try {
    include __DIR__ . '/../Templates/Notice/1.tpl';
} catch (Throwable $e) {
    // Ignore notices
}
$outputDefender = ob_get_clean();

// Check catapult info is present in Defender view
if (strpos($outputDefender, 'Ladang Gandum <b>hancur</b>.') !== false) {
    echo "[PASS] 1.tpl (Defender): Catapult destruction info is displayed!\n";
} else {
    echo "[FAIL] 1.tpl (Defender): Catapult destruction info is MISSING!\n";
    exit(1);
}

// Check unit icon u58 is present
if (strpos($outputDefender, 'unit u58') !== false) {
    echo "[PASS] 1.tpl (Defender): Hun catapult unit icon (u58) is displayed!\n";
} else {
    echo "[FAIL] 1.tpl (Defender): Hun catapult unit icon (u58) is MISSING!\n";
    exit(1);
}

// Check Ram info is present
if (strpos($outputDefender, 'Wall was not damaged.') !== false && strpos($outputDefender, 'unit u57') !== false) {
    echo "[PASS] 1.tpl (Defender): Ram info and icon (u57) are displayed!\n";
} else {
    echo "[FAIL] 1.tpl (Defender): Ram info/icon is MISSING!\n";
    exit(1);
}

// Check Prisoners row does NOT contain 57 or 58
if (strpos($outputDefender, 'Prisoners') !== false || strpos($outputDefender, 'Tawanan') !== false) {
    echo "[FAIL] 1.tpl (Defender): Prisoners row should NOT be displayed when no troops were trapped!\n";
    exit(1);
} else {
    echo "[PASS] 1.tpl (Defender): False Prisoners row (with leaked 57/58) is NOT displayed!\n";
}

// Check 'None of your soldiers returned' is suppressed for Defender
if (strpos($outputDefender, 'None of your soldiers returned') !== false) {
    echo "[FAIL] 1.tpl (Defender): 'None of your soldiers returned' must NOT be displayed to the defender!\n";
    exit(1);
} else {
    echo "[PASS] 1.tpl (Defender): 'None of your soldiers returned' is properly hidden for defender.\n";
}

// Test 2: Render 1.tpl as Attacker
$session->uid = 1; // Attacker UID
$message->readingNotice['uid'] = 1;
ob_start();
try {
    include __DIR__ . '/../Templates/Notice/1.tpl';
} catch (Throwable $e) {
}
$outputAttacker = ob_get_clean();

// For attacker, catapult info should also be visible
if (strpos($outputAttacker, 'Ladang Gandum <b>hancur</b>.') !== false) {
    echo "[PASS] 1.tpl (Attacker): Catapult destruction info is displayed!\n";
} else {
    echo "[FAIL] 1.tpl (Attacker): Catapult destruction info is MISSING for attacker!\n";
    exit(1);
}

// For attacker, 'None of your soldiers returned' should be visible
if (strpos($outputAttacker, 'None of your soldiers returned') !== false) {
    echo "[PASS] 1.tpl (Attacker): 'None of your soldiers returned' is displayed for attacker.\n";
} else {
    echo "[FAIL] 1.tpl (Attacker): 'None of your soldiers returned' should be displayed for attacker!\n";
    exit(1);
}

// Test 3: Notice 3.tpl (when all attacker units die, type 3 notice is used if attacker loses)
$message->readingNotice['ntype'] = 3;
ob_start();
try {
    include __DIR__ . '/../Templates/Notice/3.tpl';
} catch (Throwable $e) {
}
$outputType3 = ob_get_clean();

if (strpos($outputType3, 'Ladang Gandum <b>hancur</b>.') !== false && strpos($outputType3, 'unit u58') !== false) {
    echo "[PASS] 3.tpl: Catapult destruction info and icon (u58) are displayed!\n";
} else {
    echo "[FAIL] 3.tpl: Catapult destruction info is MISSING in 3.tpl!\n";
    exit(1);
}

// Test 4: Tokenized format from database (e.g. {{BLD|4|0}} <b>{{RC_DESTROYED}}</b>.)
$dataarrayTokens = $dataarray;
$dataarrayTokens[266] = '58';
$dataarrayTokens[267] = '{{BLD|4|0}} <b>{{RC_DESTROYED}}</b>.';
$dataarrayTokens[265] = '{{RC_WALL_NOT_DAMAGED}}';

$message->readingNotice['data'] = implode(',', $dataarrayTokens);
$session->uid = 2; // Defender
ob_start();
try {
    include __DIR__ . '/../Templates/Notice/1.tpl';
} catch (Throwable $e) {
}
$outputTokens = ob_get_clean();

if (strpos($outputTokens, 'unit u58') !== false && (strpos($outputTokens, 'hancur') !== false || strpos($outputTokens, 'destroyed') !== false)) {
    echo "[PASS] 1.tpl (Tokenized): Catapult destruction successfully expanded and rendered!\n";
} else {
    echo "[FAIL] 1.tpl (Tokenized): Catapult destruction failed to render from tokens!\n";
    exit(1);
}

echo ">>> ALL CATAPULT REPORT RENDERING TESTS PASSED SUCCESSFULLY! <<<\n";
