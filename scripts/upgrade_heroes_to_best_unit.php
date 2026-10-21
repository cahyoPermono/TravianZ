<?php

#################################################################################
##  Filename       : scripts/upgrade_heroes_to_best_unit.php                   ##
##  Type           : Migration Script to Upgrade Heroes to Tribe Best Base Unit##
#################################################################################

if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    require_once dirname(__DIR__) . '/upgrade_heroes.php';
    exit;
}

require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';

global $database;

if (empty($database->dblink) || !($database->dblink instanceof mysqli)) {
    fwrite(STDERR, "[ERROR] Database connection failed. Please verify GameEngine/config.php" . PHP_EOL);
    exit(1);
}

echo "=== Upgrading All Heroes to Tribe Elite / Best Base Unit ===" . PHP_EOL;

$bestUnits = [
    1  => ['unit' => 6,  'name' => 'Equites Caesaris'],
    2  => ['unit' => 16, 'name' => 'Teutonic Knight'],
    3  => ['unit' => 26, 'name' => 'Haeduan'],
    6  => ['unit' => 56, 'name' => 'Marauder'],
    7  => ['unit' => 66, 'name' => 'Resheph Chariot'],
    8  => ['unit' => 76, 'name' => 'Elpida Rider'],
    9  => ['unit' => 86, 'name' => 'Einherjar'],
    10 => ['unit' => 96, 'name' => 'Gajah Perang'],
];

$sql = "SELECT h.heroid, h.uid, h.unit, h.name, u.tribe, u.username
        FROM " . TB_PREFIX . "hero h
        JOIN " . TB_PREFIX . "users u ON h.uid = u.id";

$result = mysqli_query($database->dblink, $sql);
if (!$result) {
    echo "Query failed: " . mysqli_error($database->dblink) . PHP_EOL;
    exit(1);
}

$upgraded = 0;
$alreadyBest = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $tribe = (int)$row['tribe'];
    if (!isset($bestUnits[$tribe])) {
        continue;
    }

    $targetUnit = $bestUnits[$tribe]['unit'];
    $targetName = $bestUnits[$tribe]['name'];

    if ((int)$row['unit'] === $targetUnit) {
        $alreadyBest++;
        continue;
    }

    $heroid = (int)$row['heroid'];
    $updateSql = "UPDATE " . TB_PREFIX . "hero SET `unit` = $targetUnit WHERE `heroid` = $heroid";
    if (mysqli_query($database->dblink, $updateSql)) {
        echo "Hero ID {$heroid} (User: {$row['username']}, Tribe {$tribe}) upgraded: unit {$row['unit']} -> {$targetUnit} ({$targetName})" . PHP_EOL;
        $upgraded++;
    }
}

echo PHP_EOL . "Summary:" . PHP_EOL;
echo " - Upgraded heroes: {$upgraded}" . PHP_EOL;
echo " - Already elite heroes: {$alreadyBest}" . PHP_EOL;
echo "Done!" . PHP_EOL;
