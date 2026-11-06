<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : report.tpl 		                      			       ##
##  Type           : Admin Panel Frontend                                      ##
## --------------------------------------------------------------------------- ##
##  Developed by   : ronix (Original)                                          ##
##  Refactored by  : iopietro                                                  ##
##  Redesign by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2025. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################
if($_SESSION['access'] < MULTIHUNTER) die("Access Denied!");
include_once("../GameEngine/Generator.php");
include_once("../GameEngine/Technology.php");
include_once("../GameEngine/Message.php");

$bid = isset($_GET['bid'])? (int)$_GET['bid'] : 0;
$search = isset($_GET['q'])? $database->escape($_GET['q']) : '';
$filter = isset($_GET['f'])? $_GET['f'] : 'all';
$page = max(1, (int)($_GET['page']?? 1));
$limit = 50;
$offset = ($page-1)*$limit;

// ---- SINGLE REPORT - PĂSTRĂM VIZUALIZAREA CLASICĂ ----
if($bid > 0){
    $rep = $database->getNotice2($bid);
    if(!$rep) die("Report ID $bid doesn't exist!");
    $isAdmin = true;

    // UNMASK FOR ADMIN: If this report has masked '?' defender troops (e.g. wiped attacker report),
    // find the counterpart unmasked report from the same battle in ndata.
    $wasUnmaskedForAdmin = false;
    if (strpos($rep['data'], '?,?') !== false) {
        $toWref = (int)$rep['toWref'];
        $tMin = (int)$rep['time'] - 10;
        $tMax = (int)$rep['time'] + 10;
        $unmaskedRes = $database->query("
            SELECT * FROM " . TB_PREFIX . "ndata 
            WHERE toWref = $toWref 
              AND `time` BETWEEN $tMin AND $tMax 
              AND id != " . (int)$rep['id'] . " 
              AND `data` NOT LIKE '%?,?%' 
            ORDER BY id ASC LIMIT 1
        ");
        if ($unmaskedRes instanceof \mysqli_result && ($uRow = mysqli_fetch_assoc($unmaskedRes))) {
            $rep = $uRow;
            $wasUnmaskedForAdmin = true;
        }
    }

    $message = new Message();
    $message->readingNotice = $rep;
   ?>
    <link href="../<?php echo GP_LOCATE;?>lang/en/lang.css?f4b7d" rel="stylesheet" type="text/css">
    <link href="../<?php echo GP_LOCATE;?>lang/en/compact.css?f4b7i" rel="stylesheet" type="text/css">
    <link href="../<?php echo GP_LOCATE;?>travian.css?e21d2" rel="stylesheet" type="text/css">
    <style>
    /* FIX: cifre negre - forteaza vizibilitate */
    #content.reports { background:#fff; padding:15px; border-radius:6px; color:#000; }
    #content.reports h1 { color:#000 !important; }
    #content.reports table td,
    #content.reports table th,
    #content.reports div,
    #content.reports span {
        color:#000 !important;
        opacity:1 !important;
    }
    #content.reports td { font-weight:600 !important; }
    </style>
    <div style="max-width:900px;margin:20px auto">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <a href="?p=report" style="font-size:12px"><?php echo ADM_BACK_TO_REPORTS; ?></a>
        <?php if (!empty($wasUnmaskedForAdmin)): ?>
          <span style="background:#1e3a8a;border:1px solid #3b82f6;color:#93c5fd;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;">
            🛡️ Mode Admin: Pasukan Pertahanan Dibuka Penuh (Unmasked)
          </span>
        <?php endif; ?>
      </div>
      <div id="content" class="reports" style="margin-top:10px">
        <h1><?php echo ADM_REPORT; ?></h1>
        <?php include("../Templates/Notice/".$message->getReportType($rep['ntype']).".tpl");?>
      </div>
    </div>
    <?php
    return;
}

// ---- LISTA RAPOARTE CU FILTRE ----
$where = "1=1";
$search = trim($_GET['q'] ?? '');
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
    // Search matching player usernames
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

$countRes = $database->query("SELECT COUNT(*) c FROM ".TB_PREFIX."ndata WHERE $where");
$total = ($countRes instanceof \mysqli_result && ($cRow = mysqli_fetch_assoc($countRes))) ? (int)$cRow['c'] : 0;
$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $limit; }

$listRes = $database->query("SELECT * FROM ".TB_PREFIX."ndata WHERE $where ORDER BY time DESC LIMIT $offset,$limit");
$reps = ($listRes instanceof \mysqli_result) ? mysqli_fetch_all($listRes, MYSQLI_ASSOC) : [];

$typeNames = [1=>'reinforcement',2=>'attack',3=>'defence',4=>'scout',5=>'trade',6=>'wonder',7=>'settlement',8=>'oasis',9=>'artefact',10=>'adventure',14=>'spy'];
?>
<style>
.reports-wrap{max-width:1100px;margin:20px auto;font-family:Verdana;color:#222}
.reports-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px}
.reports-head h1{margin:0;font-size:18px;color:#ffffff}
.filters{display:flex;gap:6px}
.filters a{padding:6px 10px;border:1px solid #ddd;background:#f7f7f7;border-radius:4px;font-size:12px;text-decoration:none;color:#333}
.filters a.active{background:#333;color:#fff;border-color:#333}
.search-box input{padding:6px 8px;border:1px solid #ccc;border-radius:4px;font-size:12px;width:200px;background:#fff;color:#111}
.reports-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px}
.rep-card{background:#fff;border:1px solid #ddd;border-radius:6px;padding:10px;font-size:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:.15s;color:#222}
.rep-card:hover{box-shadow:0 2px 6px rgba(0,0,0,.1);transform:translateY(-1px)}
.rep-top{display:flex;justify-content:space-between;margin-bottom:6px}
.rep-id{font-weight:bold;color:#111}
.rep-time{color:#666;font-size:11px}
.rep-body{color:#333;line-height:1.4;max-height:38px;overflow:hidden}
.rep-foot{display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:#555}
.badge{padding:2px 6px;border-radius:3px;background:#eee;font-size:10px;text-transform:uppercase;color:#333}
.badge.attack{background:#ffe0e0;color:#c00}.badge.defence{background:#e0f0ff;color:#0066cc}
.badge.scout{background:#fff4e0;color:#e67e22}.badge.trade{background:#e8f5e9;color:#2e7d32}
.pagination{margin-top:14px;text-align:center}
.pagination a{padding:5px 9px;margin:0 2px;border:1px solid #ddd;text-decoration:none;color:#333;font-size:12px;border-radius:3px;background:#fff}
.pagination a.active{background:#333;color:#fff}
</style>

<div class="reports-wrap">
  <div class="reports-head">
    <h1>📜 Players Reports (<?php echo number_format($total);?>)</h1>
    <div class="filters">
      <?php foreach(['all'=>'All','attacks'=>'Attacks','defences'=>'Defences','scouts'=>'Scouts','trades'=>'Trades'] as $k=>$v){?>
        <a href="?p=report&f=<?php echo $k;?>&q=<?php echo urlencode($search);?>" class="<?php echo $filter==$k?'active':'';?>"><?php echo $v;?></a>
      <?php }?>
    </div>
    <form class="search-box" method="get" style="display:flex;gap:6px;align-items:center;">
      <input type="hidden" name="p" value="report">
      <input type="hidden" name="f" value="<?php echo htmlspecialchars($filter);?>">
      <input type="text" name="q" placeholder="<?php echo ADM_SEARCH_PH; ?> (Player / Desa / ID)" value="<?php echo htmlspecialchars($search);?>">
      <button type="submit" style="padding:5px 10px;font-size:11px;background:#3b82f6;border:none;border-radius:4px;color:#fff;cursor:pointer;font-weight:bold;">🔍 Cari</button>
      <?php if($search !== ''): ?>
        <a href="?p=report&f=<?php echo urlencode($filter);?>" style="font-size:11px;color:#f87171;text-decoration:none;font-weight:bold;">✕ Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="reports-grid">
    <?php foreach($reps as $r){
        $type = $typeNames[$r['ntype']]?? 'report';
        $user = $database->getUserField($r['uid'],'username',0);
        $time = date('d.m H:i',$r['time']);
        $preview = strip_tags($r['topic'] ?? '');
        $preview = mb_substr($preview,0,80);
  ?>
    <a href="?p=report&bid=<?php echo $r['id'];?>" style="text-decoration:none;color:inherit">
      <div class="rep-card">
        <div class="rep-top">
          <span class="rep-id">#<?php echo $r['id'];?> — <?php echo htmlspecialchars($user);?></span>
          <span class="rep-time"><?php echo $time;?></span>
        </div>
        <div class="rep-body"><?php echo htmlspecialchars($preview);?>...</div>
        <div class="rep-foot">
          <span class="badge <?php echo $type;?>"><?php echo $type;?></span>
          <span style="color:#666"><?php echo ADM_VIEW_ARROW; ?></span>
        </div>
      </div>
    </a>
    <?php }?>
  </div>

  <?php if($total > $limit){ $pages = ceil($total/$limit);?>
  <div class="pagination">
    <?php for($p=1;$p<=$pages && $p<=15;$p++){?>
      <a href="?p=report&page=<?php echo $p;?>&f=<?php echo urlencode($filter);?>&q=<?php echo urlencode($search);?>" class="<?php echo $p==$page?'active':'';?>"><?php echo $p;?></a>
    <?php }?>
  </div>
  <?php }?>
</div>