<?php
#################################################################################
##  Emergency Troop Movement Unstuck & Auto-Resolver Script                    ##
##  Project: TravianZ                                                          ##
#################################################################################

$repoRoot = dirname(__DIR__);
require_once $repoRoot . '/autoloader.php';
require_once $repoRoot . '/GameEngine/config.php';
require_once $repoRoot . '/GameEngine/Database.php';
require_once $repoRoot . '/GameEngine/Automation.php';

header('Content-Type: text/plain; charset=utf-8');

global $database;

echo "=== TravianZ Emergency Troop Movement Resolver ===\n\n";

$now = time();
$stuckMovements = $database->query_return("
    SELECT m.moveid, m.sort_type, m.from, m.to, m.ref, m.starttime, m.endtime, m.proc,
           v.name as to_village_name, v.owner as to_owner
    FROM " . TB_PREFIX . "movement m
    LEFT JOIN " . TB_PREFIX . "vdata v ON v.wref = m.to
    WHERE m.proc = 0 AND m.endtime <= $now
    ORDER BY m.endtime ASC
");

$count = count($stuckMovements);
echo "Found $count stuck movement(s) with proc = 0 and endtime <= now.\n\n";

if ($count === 0) {
    echo "No movements are currently stuck.\n";
    exit;
}

foreach ($stuckMovements as $mov) {
    echo "Processing Movement #{$mov['moveid']}: Type={$mov['sort_type']} From={$mov['from']} To={$mov['to']} Ref={$mov['ref']} Target=\"{$mov['to_village_name']}\" (Owner {$mov['to_owner']})\n";
}

echo "\nInvoking Automation sendunitsComplete()...\n";

$auto = new Automation();
try {
    $refMethod = new ReflectionMethod($auto, 'sendunitsComplete');
    $refMethod->setAccessible(true);
    $refMethod->invoke($auto);
} catch (\Throwable $e) {
    echo "Exception during resolution: " . $e->getMessage() . "\n";
}

echo "\nChecking status after resolution...\n";
foreach ($stuckMovements as $mov) {
    $check = $database->query_return("SELECT moveid, proc FROM " . TB_PREFIX . "movement WHERE moveid = " . (int)$mov['moveid']);
    $proc = $check[0]['proc'] ?? 'DELETED';
    echo "Movement #{$mov['moveid']}: proc = $proc " . ($proc == 1 ? "[RESOLVED OK]" : "[STILL STUCK]") . "\n";
}

echo "\n=== Done! ===\n";
