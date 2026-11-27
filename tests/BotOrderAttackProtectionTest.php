<?php
/**
 * Test: BotAI::orderAttack & BotAI::orderConquest Beginner Protection Enforcement
 *
 * Verifies that Admin Bot Manager attack/alliance-war/conquest orders:
 * 1. Reject attacking a single target under beginner protection (Raid / Normal Attack).
 * 2. Allow sending Reinforcement (attackType = 2) to a player under beginner protection.
 * 3. Reject an alliance war order when all villages in the target alliance are under beginner protection.
 * 4. Skip protected villages and only attack unprotected villages when an alliance has mixed protection status.
 * 5. Reject BotAI::orderConquest targeting a village under beginner protection.
 */

if (!defined('TB_PREFIX')) {
    define('TB_PREFIX', 's1_');
}
if (!defined('INCREASE_SPEED')) {
    define('INCREASE_SPEED', 1);
}

require_once __DIR__ . '/../GameEngine/BotAI.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "[FAILED] $message\n");
        exit(1);
    }
    echo "[PASSED] $message\n";
}

$now = time();

$database = new class($now) {
    public $dblink = null;
    public $now;
    public $dispatchedMovements = [];
    public $allianceVillages = [];

    public function __construct($now) {
        $this->now = $now;
    }

    public function escape($str) {
        return addslashes((string)$str);
    }

    public function query($sql) {
        return true;
    }

    public function query_return($sql) {
        // Bandit check
        if (strpos($sql, 'bandit_camps') !== false) {
            return [];
        }
        // Alliance lookup
        if (strpos($sql, 'FROM ' . TB_PREFIX . 'alidata') !== false) {
            if (strpos($sql, 'id = 10') !== false) {
                return [['id' => 10, 'name' => 'Protected Alliance', 'tag' => 'PROT']];
            }
            if (strpos($sql, 'id = 20') !== false) {
                return [['id' => 20, 'name' => 'Mixed Alliance', 'tag' => 'MIX']];
            }
            return [];
        }
        // Alliance target villages
        if (strpos($sql, 'WHERE u.alliance = 10') !== false) {
            return [
                ['wref' => 101, 'owner' => 1001, 'vname' => 'ProtVil1', 'pop' => 50, 'x' => 10, 'y' => 10, 'username' => 'ProtUser1', 'access' => 2, 'protect' => $this->now + 3600, 'ali_tag' => 'PROT', 'ali_name' => 'Protected Alliance'],
                ['wref' => 102, 'owner' => 1002, 'vname' => 'ProtVil2', 'pop' => 40, 'x' => 12, 'y' => 12, 'username' => 'ProtUser2', 'access' => 2, 'protect' => $this->now + 7200, 'ali_tag' => 'PROT', 'ali_name' => 'Protected Alliance'],
            ];
        }
        if (strpos($sql, 'WHERE u.alliance = 20') !== false) {
            return [
                ['wref' => 201, 'owner' => 2001, 'vname' => 'MixProtVil', 'pop' => 60, 'x' => 15, 'y' => 15, 'username' => 'MixProtUser', 'access' => 2, 'protect' => $this->now + 3600, 'ali_tag' => 'MIX', 'ali_name' => 'Mixed Alliance'],
                ['wref' => 202, 'owner' => 2002, 'vname' => 'MixOpenVil', 'pop' => 80, 'x' => 20, 'y' => 20, 'username' => 'MixOpenUser', 'access' => 2, 'protect' => 0, 'ali_tag' => 'MIX', 'ali_name' => 'Mixed Alliance'],
            ];
        }
        // Single target by w.x / w.y
        if (strpos($sql, 'FROM ' . TB_PREFIX . 'wdata w') !== false && strpos($sql, 'w.x = 5 AND w.y = 5') !== false) {
            return [[
                'wref' => 301, 'owner' => 3001, 'vname' => 'ConqProtVil', 'pop' => 120, 'capital' => 0, 'loyalty' => 100,
                'x' => 5, 'y' => 5, 'username' => 'ConqProtUser', 'dtribe' => 1, 'dally' => 0, 'access' => 2, 'owner_villages' => 2,
            ]];
        }
        // Eligible attacker bots
        if (strpos($sql, 'u.is_bot = 1') !== false) {
            return [[
                'uid' => 9001, 'username' => 'Bot_Tester', 'tribe' => 1, 'alliance' => 99,
                'wref' => 901, 'vname' => 'BotVillage', 'x' => 0, 'y' => 0,
                'ali_tag' => 'BOT', 'ali_name' => 'Bot Alliance',
            ]];
        }
        // Bot home units
        if (strpos($sql, 'FROM ' . TB_PREFIX . 'units WHERE vref = 901') !== false) {
            return [[
                'u1' => 100, 'u2' => 100, 'u3' => 100, 'u4' => 0, 'u5' => 50, 'u6' => 20,
                'u7' => 0, 'u8' => 0, 'u9' => 0, 'u10' => 0, 'hero' => 0,
            ]];
        }
        // Hero check
        if (strpos($sql, 'FROM ' . TB_PREFIX . 'units u LEFT JOIN ' . TB_PREFIX . 'hero') !== false) {
            return [['hero' => 0, 'dead' => 0, 'health' => 0, 'unit' => 0]];
        }
        return [];
    }

    public function getMInfo($wref) {
        if ((int)$wref === 301) {
            return ['wref' => 301, 'owner' => 3001, 'name' => 'ProtTargetVil', 'x' => 5, 'y' => 5];
        }
        return [];
    }

    public function getUserField($uidOrName, $field, $mode) {
        if ((int)$mode === 1 && $uidOrName === 'ProtUser') {
            return 3001;
        }
        if ((int)$uidOrName === 3001) {
            if ($field === 'username') return 'ProtUser';
            if ($field === 'protect') return $this->now + 3600;
        }
        return '';
    }

    public function getProfileVillages($uid) {
        if ((int)$uid === 3001) {
            return [['wref' => 301]];
        }
        return [];
    }

    public function hasBeginnerProtection($vid) {
        return in_array((int)$vid, [101, 102, 201, 301], true);
    }

    public function modifyUnit($vref, $units, $amounts, $modes) {
        return true;
    }

    public function addAttack($vid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type, $c1, $c2, $c3) {
        return count($this->dispatchedMovements) + 1;
    }

    public function addMovement($type, $from, $to, $ref, $start, $end) {
        $this->dispatchedMovements[] = ['type' => $type, 'from' => $from, 'to' => $to, 'ref' => $ref];
        return true;
    }
};

echo "=== Running BotOrderAttackProtectionTest ===\n";

// 1. Single target under beginner protection -> Raid (4) & Normal Attack (3) must fail
$resRaid = BotAI::orderAttack('0', 'ProtUser', 4, 20, 'balanced');
assertTrue($resRaid['status'] === 'error', 'Single target under beginner protection is rejected for Raid');
assertTrue(strpos($resRaid['message'], 'perlindungan pemula') !== false, 'Error message mentions beginner protection for Raid');

$resNormal = BotAI::orderAttack('0', 'ProtUser', 3, 20, 'balanced');
assertTrue($resNormal['status'] === 'error', 'Single target under beginner protection is rejected for Normal Attack');

// 2. Single target under beginner protection -> Reinforcement (2) must be allowed
$database->dispatchedMovements = [];
$resReinf = BotAI::orderAttack('0', 'ProtUser', 2, 20, 'defense');
assertTrue($resReinf['status'] === 'success', 'Reinforcement (attackType=2) to protected target is allowed');
assertTrue(count($database->dispatchedMovements) === 1 && $database->dispatchedMovements[0]['to'] === 301, 'Reinforcement dispatched to protected village');

// 3. Alliance target where ALL villages are under beginner protection -> must fail
$database->dispatchedMovements = [];
$resAllProt = BotAI::orderAttack('all', 'alliance:10', 4, 20, 'balanced');
assertTrue($resAllProt['status'] === 'error', 'Alliance war rejected when all target alliance villages are under beginner protection');
assertTrue(count($database->dispatchedMovements) === 0, 'No movements dispatched when all alliance villages are protected');

// 4. Alliance target with mixed protection -> only unprotected village (wref=202) is attacked
$database->dispatchedMovements = [];
$resMixed = BotAI::orderAttack('all', 'alliance:20', 4, 20, 'balanced');
assertTrue($resMixed['status'] === 'success', 'Alliance war succeeds when at least one village is not protected');
assertTrue(count($database->dispatchedMovements) === 1, 'Only 1 movement dispatched for mixed alliance');
assertTrue($database->dispatchedMovements[0]['to'] === 202, 'Only unprotected village (wref=202) was targeted');
assertTrue(strpos($resMixed['message'], '1 desa dalam masa perlindungan pemula dilewati') !== false, 'Summary reports skipped protected village count');

// 5. Conquest order targeting a protected village -> must fail
$resConq = BotAI::orderConquest('0', '5|5');
assertTrue($resConq['status'] === 'error', 'orderConquest rejects target village under beginner protection');
assertTrue(strpos($resConq['message'], 'perlindungan pemula') !== false, 'orderConquest error message mentions beginner protection');

echo "ALL TESTS PASSED!\n";
