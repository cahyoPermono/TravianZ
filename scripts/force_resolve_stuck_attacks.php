<?php
#################################################################################
##  Emergency Troop Movement Unstuck & Auto-Resolver Script                    ##
##  Project: TravianZ                                                          ##
#################################################################################

if (!defined('AUTOMATION_MANUAL_RUN')) {
    define('AUTOMATION_MANUAL_RUN', true);
}

$repoRoot = dirname(__DIR__);
require_once $repoRoot . '/autoloader.php';
require_once $repoRoot . '/GameEngine/config.php';
require_once $repoRoot . '/GameEngine/Database.php';
require_once $repoRoot . '/GameEngine/Automation.php';

header('Content-Type: text/plain; charset=utf-8');

global $database;

echo "=== TravianZ Emergency Troop Movement Resolver ===\n\n";

// 1. Ensure any missing tables exist immediately (self-healing)
echo "1. Ensuring required database tables exist...\n";
$tablesToEnsure = [
    TB_PREFIX . 'paranormal' => "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "paranormal` (
        `vref` int(11) NOT NULL,
        `pocong` int(11) NOT NULL DEFAULT '0',
        `kuntilanak` int(11) NOT NULL DEFAULT '0',
        `genderuwo` int(11) NOT NULL DEFAULT '0',
        `tuyul_until` int(11) NOT NULL DEFAULT '0',
        `ritual_type` int(11) NOT NULL DEFAULT '0',
        `ritual_end` int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`vref`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    TB_PREFIX . 'healing' => "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "healing` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `vref` int(11) NULL,
        `unit` smallint(6) NULL,
        `amt` int(11) NULL,
        `timestamp` int(11) NULL,
        `eachtime` int(11) NULL,
        `timestamp2` int(11) NULL,
        PRIMARY KEY (`id`),
        KEY `vref` (`vref`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

    TB_PREFIX . 'hospital' => "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "hospital` (
        `vref` int(11) NOT NULL,
        `u1` int(11) NULL DEFAULT '0',
        `u2` int(11) NULL DEFAULT '0',
        `u3` int(11) NULL DEFAULT '0',
        `u4` int(11) NULL DEFAULT '0',
        `u5` int(11) NULL DEFAULT '0',
        `u6` int(11) NULL DEFAULT '0',
        `u7` int(11) NULL DEFAULT '0',
        `u8` int(11) NULL DEFAULT '0',
        `u9` int(11) NULL DEFAULT '0',
        `u10` int(11) NULL DEFAULT '0',
        `u11` int(11) NULL DEFAULT '0',
        `u12` int(11) NULL DEFAULT '0',
        `u13` int(11) NULL DEFAULT '0',
        `u14` int(11) NULL DEFAULT '0',
        `u15` int(11) NULL DEFAULT '0',
        `u16` int(11) NULL DEFAULT '0',
        `u17` int(11) NULL DEFAULT '0',
        `u18` int(11) NULL DEFAULT '0',
        `u19` int(11) NULL DEFAULT '0',
        `u20` int(11) NULL DEFAULT '0',
        `u21` int(11) NULL DEFAULT '0',
        `u22` int(11) NULL DEFAULT '0',
        `u23` int(11) NULL DEFAULT '0',
        `u24` int(11) NULL DEFAULT '0',
        `u25` int(11) NULL DEFAULT '0',
        `u26` int(11) NULL DEFAULT '0',
        `u27` int(11) NULL DEFAULT '0',
        `u28` int(11) NULL DEFAULT '0',
        `u29` int(11) NULL DEFAULT '0',
        `u30` int(11) NULL DEFAULT '0',
        `u31` int(11) NULL DEFAULT '0',
        `u32` int(11) NULL DEFAULT '0',
        `u33` int(11) NULL DEFAULT '0',
        `u34` int(11) NULL DEFAULT '0',
        `u35` int(11) NULL DEFAULT '0',
        `u36` int(11) NULL DEFAULT '0',
        `u37` int(11) NULL DEFAULT '0',
        `u38` int(11) NULL DEFAULT '0',
        `u39` int(11) NULL DEFAULT '0',
        `u40` int(11) NULL DEFAULT '0',
        `u41` int(11) NULL DEFAULT '0',
        `u42` int(11) NULL DEFAULT '0',
        `u43` int(11) NULL DEFAULT '0',
        `u44` int(11) NULL DEFAULT '0',
        `u45` int(11) NULL DEFAULT '0',
        `u46` int(11) NULL DEFAULT '0',
        `u47` int(11) NULL DEFAULT '0',
        `u48` int(11) NULL DEFAULT '0',
        `u49` int(11) NULL DEFAULT '0',
        `u50` int(11) NULL DEFAULT '0',
        `u51` int(11) NULL DEFAULT '0',
        `u52` int(11) NULL DEFAULT '0',
        `u53` int(11) NULL DEFAULT '0',
        `u54` int(11) NULL DEFAULT '0',
        `u55` int(11) NULL DEFAULT '0',
        `u56` int(11) NULL DEFAULT '0',
        `u57` int(11) NULL DEFAULT '0',
        `u58` int(11) NULL DEFAULT '0',
        `u59` int(11) NULL DEFAULT '0',
        `u60` int(11) NULL DEFAULT '0',
        `u61` int(11) NULL DEFAULT '0',
        `u62` int(11) NULL DEFAULT '0',
        `u63` int(11) NULL DEFAULT '0',
        `u64` int(11) NULL DEFAULT '0',
        `u65` int(11) NULL DEFAULT '0',
        `u66` int(11) NULL DEFAULT '0',
        `u67` int(11) NULL DEFAULT '0',
        `u68` int(11) NULL DEFAULT '0',
        `u69` int(11) NULL DEFAULT '0',
        `u70` int(11) NULL DEFAULT '0',
        `u71` int(11) NULL DEFAULT '0',
        `u72` int(11) NULL DEFAULT '0',
        `u73` int(11) NULL DEFAULT '0',
        `u74` int(11) NULL DEFAULT '0',
        `u75` int(11) NULL DEFAULT '0',
        `u76` int(11) NULL DEFAULT '0',
        `u77` int(11) NULL DEFAULT '0',
        `u78` int(11) NULL DEFAULT '0',
        `u79` int(11) NULL DEFAULT '0',
        `u80` int(11) NULL DEFAULT '0',
        `u81` int(11) NULL DEFAULT '0',
        `u82` int(11) NULL DEFAULT '0',
        `u83` int(11) NULL DEFAULT '0',
        `u84` int(11) NULL DEFAULT '0',
        `u85` int(11) NULL DEFAULT '0',
        `u86` int(11) NULL DEFAULT '0',
        `u87` int(11) NULL DEFAULT '0',
        `u88` int(11) NULL DEFAULT '0',
        `u89` int(11) NULL DEFAULT '0',
        `u90` int(11) NULL DEFAULT '0',
        `u91` int(11) NULL DEFAULT '0',
        `u92` int(11) NULL DEFAULT '0',
        `u93` int(11) NULL DEFAULT '0',
        `u94` int(11) NULL DEFAULT '0',
        `u95` int(11) NULL DEFAULT '0',
        `u96` int(11) NULL DEFAULT '0',
        `u97` int(11) NULL DEFAULT '0',
        `u98` int(11) NULL DEFAULT '0',
        `u99` int(11) NULL DEFAULT '0',
        `u100` int(11) NULL DEFAULT '0',
        PRIMARY KEY (`vref`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
];

foreach ($tablesToEnsure as $tbl => $sql) {
    try {
        $database->query($sql);
        echo " - Table '$tbl': OK\n";
    } catch (\Throwable $e) {
        echo " - Table '$tbl' notice: " . $e->getMessage() . "\n";
    }
}

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
echo "\nFound $count stuck movement(s) with proc = 0 and endtime <= now.\n\n";

if ($count === 0) {
    echo "No movements are currently stuck.\n";
    exit;
}

foreach ($stuckMovements as $mov) {
    echo "Pending Movement #{$mov['moveid']}: Type={$mov['sort_type']} From={$mov['from']} To={$mov['to']} Ref={$mov['ref']} Target=\"{$mov['to_village_name']}\" (Owner {$mov['to_owner']})\n";
}

echo "\nInvoking Automation combat & movement processors...\n";

$auto = new Automation();
$methodsToRun = ['sendunitsComplete', 'sendreinfunitsComplete', 'returnunitsComplete', 'sendsettlersComplete'];

for ($pass = 1; $pass <= 5; $pass++) {
    echo "\n--- Resolution Pass #$pass ---\n";
    foreach ($methodsToRun as $methodName) {
        try {
            if (method_exists($auto, $methodName)) {
                $refMethod = new ReflectionMethod($auto, $methodName);
                $refMethod->setAccessible(true);
                $refMethod->invoke($auto);
                echo " - Executed $methodName()\n";
            }
        } catch (\Throwable $e) {
            echo "Exception during $methodName(): " . $e->getMessage() . "\n";
        }
    }

    $remaining = (int)$database->query_return("SELECT COUNT(*) as cnt FROM " . TB_PREFIX . "movement WHERE proc = 0 AND endtime <= " . time())[0]['cnt'];
    echo "Remaining stuck movements after Pass #$pass: $remaining\n";
    if ($remaining === 0) {
        break;
    }
}

echo "\nChecking status of initially stuck movements...\n";
$resolvedCount = 0;
foreach ($stuckMovements as $mov) {
    $check = $database->query_return("SELECT moveid, proc FROM " . TB_PREFIX . "movement WHERE moveid = " . (int)$mov['moveid']);
    $proc = $check[0]['proc'] ?? 'DELETED';
    $status = ($proc == 1) ? "[RESOLVED OK]" : "[STILL STUCK]";
    if ($proc == 1) $resolvedCount++;
    echo "Movement #{$mov['moveid']}: proc = $proc $status\n";
}

echo "\nSummary: $resolvedCount of $count movements resolved.\n";
echo "=== Done! ===\n";
