<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : gameover.php                                              ##
##  Type           : In Game Game Over Screen                                  ##
##  Purpose        : Elimination screen when all villages are destroyed       ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Session.php");
AccessLogger::logRequest();

if (!$session->logged_in) {
    header("Location: login.php");
    exit;
}

// If player is not Game Over, send back to village
if (empty($session->userarray['is_gameover'])) {
    header("Location: dorf1.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf']) || $_SESSION['csrf'] !== ($_POST['csrf'] ?? '')) {
        throw new RuntimeException('CSRF attack detected.');
    }

    $action = $_POST['action'] ?? '';
    $uid = (int)$session->uid;
    $username = $session->username;

    if ($action === 'restart') {
        include_once("GameEngine/Account.php");
        $account = new Account();
        $kid = rand(1, 4);
        $villageName = $username . "'s village";

        $wid = $database->generateVillages([
            [
                'wid'     => 0,
                'mode'    => 0,
                'type'    => 3,
                'kid'     => $kid,
                'capital' => 1,
                'pop'     => 2,
                'name'    => $villageName,
                'natar'   => 0
            ]
        ], $uid, $username);

        $wid = is_array($wid) ? (int)($wid[0] ?? 0) : (int)$wid;

        if ((!defined('HERO_FROM_START') || HERO_FROM_START) && class_exists('Units')) {
            $tribe = (int)$database->getUserField($uid, 'tribe', 0);
            Units::createStarterHero($uid, $wid, $tribe, $username);
        }

        // Restore access to normal player (2), clear gameover flag, grant protection
        $protectTime = time() + (defined('TIME_OF_PROTECTION') ? TIME_OF_PROTECTION : 86400);
        $database->updateUserField($uid, ['is_gameover', 'access', 'protect', 'ok'], [0, 2, $protectTime, 0], 1);

        unset($_SESSION['cache_villages_' . $uid]);
        $_SESSION['wid'] = $wid;

        $logText = "Pemain ID $uid ($username) telah melakukan Mulai Baru (Restart) setelah Game Over.";
        $logEsc = $database->escape($logText);
        $database->query("INSERT INTO " . TB_PREFIX . "admin_log (`id`, `user`, `log`, `time`) VALUES (0, '0', '$logEsc', " . time() . ")");

        header("Location: dorf1.php");
        exit;
    } elseif ($action === 'delete') {
        // Complete account deletion
        $database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");
        $database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $uid");
        $database->query("DELETE FROM " . TB_PREFIX . "mdata WHERE target = $uid OR owner = $uid");
        $database->query("DELETE FROM " . TB_PREFIX . "ndata WHERE uid = $uid");
        $database->query("DELETE FROM " . TB_PREFIX . "medal WHERE userid = $uid");
        $database->query("DELETE FROM " . TB_PREFIX . "online WHERE uid = $uid");
        $database->query("DELETE FROM " . TB_PREFIX . "deleting WHERE uid = $uid");

        $logText = "Pemain ID $uid ($username) memilih menghapus akun secara permanen dari layar Game Over.";
        $logEsc = $database->escape($logText);
        $database->query("INSERT INTO " . TB_PREFIX . "admin_log (`id`, `user`, `log`, `time`) VALUES (0, '0', '$logEsc', " . time() . ")");

        $session->Logout();
        header("Location: login.php?msg=deleted");
        exit;
    }
}

// Generate fresh CSRF token
$key = sha1(microtime());
$_SESSION['csrf'] = $key;

// Extract gameover tag info if available
$desc2 = $session->userarray['desc2'] ?? '';
$attackerName = 'Musuh';
$downfallTime = date('d-m-Y H:i:s');
if (preg_match('/\[#GAMEOVER#\]\|Atk:(\d+)\|By:([^|]+)\|Time:(\d+)/', $desc2, $m)) {
    $attackerName = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
    if (!empty($m[3])) {
        $downfallTime = date('d-m-Y H:i:s', (int)$m[3]);
    }
}

$tribeNames = [
    1 => 'Romawi',
    2 => 'Teuton',
    3 => 'Galia',
    4 => 'Alam (Nature)',
    5 => 'Natar',
    6 => 'Hun',
    7 => 'Mesir',
    8 => 'Sparta',
    9 => 'Viking',
    10 => 'Nusantara'
];
$playerTribe = $tribeNames[$session->tribe] ?? 'Tidak Diketahui';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php echo SERVER_NAME; ?> - GAME OVER</title>
    <link rel="shortcut icon" href="favicon.ico" />
    <meta http-equiv="cache-control" content="max-age=0" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?f4b7i" rel="stylesheet" type="text/css" />
    <link href="<?php echo GP_LOCATE; ?>lang/en/lang.css?e21d2" rel="stylesheet" type="text/css" />
    <link href="<?php echo GP_LOCATE; ?>travian.css?e21d2" rel="stylesheet" type="text/css" />
    <link href="css/mobile_engine.css?v=20261009_m2" rel="stylesheet" type="text/css" />
    <style type="text/css">
        .gameover-container {
            max-width: 650px;
            margin: 30px auto;
            background: #fff;
            border: 2px solid #8b0000;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(139, 0, 0, 0.35);
            padding: 25px;
            font-family: Arial, sans-serif;
            text-align: center;
        }
        .gameover-header {
            background: linear-gradient(135deg, #8b0000 0%, #4a0000 100%);
            color: #fff;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .gameover-header h1 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 1.5px;
            color: #ffcccc;
            text-shadow: 1px 1px 4px #000;
        }
        .gameover-subtitle {
            margin: 6px 0 0;
            font-size: 14px;
            color: #fce4e4;
        }
        .gameover-desc {
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background: #fff8f8;
            border-left: 4px solid #cc0000;
            padding: 12px 18px;
            text-align: left;
            margin-bottom: 20px;
        }
        .gameover-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            text-align: left;
            font-size: 13px;
        }
        .gameover-table th {
            background: #f2f2f2;
            padding: 8px 12px;
            border: 1px solid #ddd;
            width: 35%;
        }
        .gameover-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }
        .action-cards {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .action-card {
            flex: 1 1 240px;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .btn-restart {
            background: #2e7d32;
            color: #fff;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }
        .btn-restart:hover {
            background: #1b5e20;
        }
        .btn-delete {
            background: #c62828;
            color: #fff;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }
        .btn-delete:hover {
            background: #8e0000;
        }
        .btn-logout {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: underline;
            font-size: 13px;
        }
    </style>
</head>
<body class="v35 ie ie7">
<div class="wrapper">
    <div id="dynamic_header"></div>
    <div id="header"></div>
    <div id="mid">
        <div class="gameover-container">
            <div class="gameover-header">
                <h1>⚔️ GAME OVER ⚔️</h1>
                <div class="gameover-subtitle">Peradaban Anda Telah Musnah dari Peta Dunia</div>
            </div>

            <div class="gameover-desc">
                <strong>Pemberitahuan Kerajaan:</strong><br />
                Seluruh desa kekaisaran Anda telah dihancurkan oleh serangan ketapel musuh hingga mencapai <strong>populasi 0</strong>.
                Karena seluruh desa Anda telah rata dengan tanah, peradaban Anda dinyatakan gugur (Game Over) dan dihapus dari peta kekuasaan dunia.
            </div>

            <table class="gameover-table">
                <tr>
                    <th>Pemimpin / Akun</th>
                    <td><strong><?php echo htmlspecialchars($session->username, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                </tr>
                <tr>
                    <th>Suku</th>
                    <td><?php echo htmlspecialchars($playerTribe, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <th>Dihancurkan Oleh</th>
                    <td><strong style="color:#c62828;"><?php echo $attackerName; ?></strong> (Ketapel)</td>
                </tr>
                <tr>
                    <th>Waktu Kehancuran</th>
                    <td><?php echo $downfallTime; ?></td>
                </tr>
                <tr>
                    <th>Status Dunia</th>
                    <td><span style="color:#d32f2f; font-weight:bold;">Telah Gugur / Dieliminasi Total</span></td>
                </tr>
            </table>

            <div class="action-cards">
                <div class="action-card">
                    <h3 style="margin-top:0; color:#2e7d32;">🏰 Mulai Peradaban Baru</h3>
                    <p style="font-size:12px; color:#555; text-align:left;">
                        Bangun kembali kekaisaran Anda dari awal! Anda akan mendapatkan 1 desa permulaan baru di koordinat acak, lengkap dengan perlindungan pemula (Beginner Protection).
                    </p>
                    <form method="post" action="gameover.php">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <input type="hidden" name="action" value="restart" />
                        <button type="submit" class="btn-restart">Mulai Baru (Restart)</button>
                    </form>
                </div>

                <div class="action-card">
                    <h3 style="margin-top:0; color:#c62828;">🗑️ Hapus Akun Sepenuhnya</h3>
                    <p style="font-size:12px; color:#555; text-align:left;">
                        Jika Anda tidak ingin bermain lagi di server ini, Anda dapat menghapus data akun Anda secara permanen dari server permainan.
                    </p>
                    <form method="post" action="gameover.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun Anda secara permanen? Tindakan ini tidak dapat dibatalkan.');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <button type="submit" class="btn-delete">Hapus Akun Permanen</button>
                    </form>
                </div>
            </div>

            <div>
                <a href="logout.php" class="btn-logout">Keluar (Logout)</a>
            </div>
        </div>
    </div>
    <div class="footer-stopper outgame"></div>
    <div class="clear"></div>
    <?php include("Templates/footer.tpl"); ?>
</div>
</body>
</html>
