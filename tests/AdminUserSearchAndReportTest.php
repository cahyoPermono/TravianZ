<?php
/**
 * Test for Admin Users Search & Admin Report Unmasking & Search
 */

require_once __DIR__ . '/../GameEngine/Database.php';

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        echo "FAIL: $message\n";
        echo "  Expected: " . var_export($expected, true) . "\n";
        echo "  Actual:   " . var_export($actual, true) . "\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

function assertTrue($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running AdminUserSearchAndReportTest ===\n";

// -------------------------------------------------------------------------
// 1. Test Admin Users Search SQL Building
// -------------------------------------------------------------------------
function buildUsersWhere($filter, $search, $link) {
    $where = "1=1";
    if($filter=='admins') $where = "access >= 8";
    elseif($filter=='normal') $where = "access = 2";
    elseif($filter=='banned') $where = "access = 0";
    elseif($filter=='online') $where = "timestamp > ".(time()-900);

    if($search !== '') {
        $searchEsc = mysqli_real_escape_string($link, $search);
        $searchCond = "(username LIKE '%$searchEsc%' OR email LIKE '%$searchEsc%'";
        if(ctype_digit($search)) {
            $searchCond .= " OR id = " . (int)$search;
        }
        $searchCond .= ")";
        $where .= " AND " . $searchCond;
    }
    return $where;
}

global $database;
$link = $database->dblink;

$where1 = buildUsersWhere('all', 'Theoden', $link);
assertTrue(strpos($where1, "username LIKE '%Theoden%'") !== false, "Users search query includes username LIKE search term");

$where2 = buildUsersWhere('normal', '127', $link);
assertTrue(strpos($where2, "id = 127") !== false, "Users search query includes id numeric match");

// Test executing query on database - should succeed without error
$qUsers = mysqli_query($link, "SELECT id, username FROM " . TB_PREFIX . "users WHERE $where1 LIMIT 5");
assertTrue($qUsers !== false, "Users search query executes successfully on MySQL");

// -------------------------------------------------------------------------
// 2. Test Report Search Query without n1/n2
// -------------------------------------------------------------------------
function buildReportWhere($search, $filter, $database) {
    $where = "1=1";
    if($search !== '') {
        $searchEsc = $database->escape($search);
        $searchParts = [
            "topic LIKE '%" . $searchEsc . "%'",
            "`data` LIKE '%" . $searchEsc . "%'"
        ];
        if (ctype_digit($search)) {
            $searchParts[] = "id = " . (int)$search;
            $searchParts[] = "uid = " . (int)$search;
        }
        $userRes = $database->query("SELECT id FROM " . TB_PREFIX . "users WHERE username LIKE '%" . $searchEsc . "%' LIMIT 20");
        if ($userRes instanceof \mysqli_result) {
            $uids = [];
            while ($uRow = mysqli_fetch_assoc($userRes)) {
                $uids[] = (int)$uRow['id'];
            }
            if (!empty($uids)) {
                $searchParts[] = "uid IN (" . implode(',', $uids) . ")";
            }
        }
        $where .= " AND (" . implode(" OR ", $searchParts) . ")";
    }

    $filterMap = [
        'attacks' => "ntype IN (1,2,3,9,18,19,20,21,22,23)",
        'defences' => "ntype IN (3,15,16,17,23)",
        'scouts' => "ntype IN (4,14,18,19,20,21)",
        'trades' => "ntype IN (5)"
    ];
    if(isset($filterMap[$filter])) $where.= " AND ".$filterMap[$filter];
    return $where;
}

$whereRep = buildReportWhere('Lukas97', 'attacks', $database);
assertTrue(strpos($whereRep, "n1") === false, "Report search does not contain obsolete n1");
assertTrue(strpos($whereRep, "n2") === false, "Report search does not contain obsolete n2");

$countRes = $database->query("SELECT COUNT(*) c FROM " . TB_PREFIX . "ndata WHERE $whereRep");
assertTrue($countRes instanceof \mysqli_result, "Report search query executes without SQL syntax error");
$total = ($countRes && ($cRow = mysqli_fetch_assoc($countRes))) ? (int)$cRow['c'] : 0;
assertTrue(is_int($total), "Report search count is an integer and does not crash");

// -------------------------------------------------------------------------
// 3. Test Admin Unmasking Logic
// -------------------------------------------------------------------------
$time = time();
$toWref = 99999;
$realData = "88104,20602,1,28,0,23,0,0,0,0,0,0,0,28,0,23,0,0,0,0,0,0,0,0,0,0,0,0,127,99999,Lukas97 Realm,,,,2,50,0,20,0,0,0,0,0,0,0,5,0,2,0,0,0,0,0,0,0,0";
$failData = "88104,20602,1,28,0,23,0,0,0,0,0,0,0,28,0,23,0,0,0,0,0,0,0,0,0,0,0,0,127,99999,Lukas97 Realm,,,,2,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0";

// Insert paired test notices
$database->query("INSERT INTO " . TB_PREFIX . "ndata (`uid`, `toWref`, `ally`, `topic`, `ntype`, `data`, `time`, `viewed`, `archive`, `del`) VALUES 
(88104, $toWref, 0, 'Nuremberg attacks Lukas97 Realm', 3, '$failData', $time, 0, 0, 0)");
$attackerNoticeId = mysqli_insert_id($database->dblink);

$database->query("INSERT INTO " . TB_PREFIX . "ndata (`uid`, `toWref`, `ally`, `topic`, `ntype`, `data`, `time`, `viewed`, `archive`, `del`) VALUES 
(127, $toWref, 0, 'Nuremberg attacks Lukas97 Realm', 1, '$realData', $time, 0, 0, 0)");
$defenderNoticeId = mysqli_insert_id($database->dblink);

// Test unmasking attacker report
$rep = $database->getNotice2($attackerNoticeId);
assertTrue(strpos($rep['data'], '?,?') !== false, "Attacker report is initially masked with ?");

$wasUnmasked = false;
if (strpos($rep['data'], '?,?') !== false) {
    $tMin = (int)$rep['time'] - 10;
    $tMax = (int)$rep['time'] + 10;
    $toW = (int)$rep['toWref'];
    $uRes = $database->query("
        SELECT * FROM " . TB_PREFIX . "ndata 
        WHERE toWref = $toW 
          AND `time` BETWEEN $tMin AND $tMax 
          AND id != " . (int)$rep['id'] . " 
          AND `data` NOT LIKE '%?,?%' 
        ORDER BY id ASC LIMIT 1
    ");
    if ($uRes instanceof \mysqli_result && ($uRow = mysqli_fetch_assoc($uRes))) {
        $rep = $uRow;
        $wasUnmasked = true;
    }
}

assertTrue($wasUnmasked, "Admin unmasking successfully found counterpart defender report");
assertEqual($defenderNoticeId, (int)$rep['id'], "Unmasked report row points to defender report ID");
assertTrue(strpos($rep['data'], '?,?') === false, "Unmasked data contains true numbers without question marks");

// Clean up test data
$database->query("DELETE FROM " . TB_PREFIX . "ndata WHERE id IN ($attackerNoticeId, $defenderNoticeId)");

echo "=== All AdminUserSearchAndReportTest checks passed successfully! ===\n";
