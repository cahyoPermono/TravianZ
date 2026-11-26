<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : MarketPushProtectionRemovalTest.php                       ##
##  Type           : Unit Test                                                 ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Shadow / TravianZ                                         ##
##  Project        : TravianZ                                                  ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

require_once __DIR__ . '/../GameEngine/Market.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "[FAILED] $message\n";
        exit(1);
    }
    echo "[PASSED] $message\n";
}

echo "=== Running Market Push Protection Removal Unit Test ===\n";

$market = new Market();

// Dummy database mock to test exceedsPushLimit
class MockDatabaseForMarket {
    public function getVillageField($wid, $field) {
        if ($field === 'owner') {
            // fromWid 100 owner is 10, toWid 200 owner is 20 (different players)
            return ($wid == 100) ? 10 : 20;
        }
        return 0;
    }
}

$mockDb = new MockDatabaseForMarket();

// 1. Verify exceedsPushLimit returns false by default (unrestricted sending to other players)
$result = $market->exceedsPushLimit($mockDb, 100, 200, [50000, 50000, 50000, 50000]);
assertTest($result === false, "exceedsPushLimit returns false by default for sending resources to other players");

// 2. Verify own-village transfer also returns false
$resultOwn = $market->exceedsPushLimit($mockDb, 100, 100, [10000, 10000, 10000, 10000]);
assertTest($resultOwn === false, "exceedsPushLimit returns false for transfers to own village");

// 3. Verify zero resources returns false
$resultZero = $market->exceedsPushLimit($mockDb, 100, 200, [0, 0, 0, 0]);
assertTest($resultZero === false, "exceedsPushLimit returns false for zero resources");

echo "\nAll Market Push Protection Removal Tests Passed Successfully!\n";
