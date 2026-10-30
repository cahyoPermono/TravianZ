<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : daily_quests.tpl                                          ##
##  Purpose        : Daily Quests Milestone Chest UI Widget                    ##
##  Author         : Antigravity AI & TravianZ                                 ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (!class_exists('DailyQuests')) {
    require_once __DIR__ . '/../GameEngine/DailyQuests.php';
}

$dqUid = isset($session->uid) ? (int)$session->uid : 0;
if ($dqUid <= 0) {
    return;
}

// Handle claim request
if (isset($_POST['claim_daily_quest']) && $dqUid > 0) {
    $claimRes = DailyQuests::claimReward($dqUid);
    $_SESSION['daily_flash'] = $claimRes;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$dqFlash = $_SESSION['daily_flash'] ?? null;
unset($_SESSION['daily_flash']);

$dqProgress = DailyQuests::getDailyProgress($dqUid);
$dqPoints = (int)$dqProgress['points'];
$dqClaimed = (bool)$dqProgress['claimed'];
$dqCanClaim = (bool)$dqProgress['can_claim'];

$barColor = $dqPoints >= 100 ? '#28a745' : ($dqPoints >= 50 ? '#ffc107' : '#007bff');
?>

<div id="daily_quests_widget" style="margin: 10px 0 14px 0; border: 1.5px solid #a67c4a; border-radius: 6px; background: linear-gradient(180deg, #fbf7ee 0%, #f4ebd9 100%); padding: 8px 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); font-family: Verdana, Arial, sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #cbb89d; padding-bottom: 5px; margin-bottom: 6px;">
        <span style="font-weight: bold; font-size: 11px; color: #5a2c00; display: flex; align-items: center; gap: 4px;">
            📜 <b>Misi Harian Kaisar</b>
        </span>
        <span style="font-size: 10px; font-weight: bold; color: <?php echo $dqPoints >= 100 ? '#1e7e34' : '#8a6d3b'; ?>;">
            <?php echo $dqPoints; ?>% Selesai
        </span>
    </div>

    <?php if ($dqFlash): ?>
        <div style="background: <?php echo $dqFlash['success'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $dqFlash['success'] ? '#155724' : '#721c24'; ?>; border: 1px solid <?php echo $dqFlash['success'] ? '#c3e6cb' : '#f5c6cb'; ?>; border-radius: 4px; padding: 4px 6px; font-size: 10px; margin-bottom: 6px; text-align: center;">
            <?php echo htmlspecialchars($dqFlash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Progress Bar -->
    <div style="background: #e2d7c5; border-radius: 10px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 8px; border: 1px solid #c4b59f;">
        <div style="background: <?php echo $barColor; ?>; height: 100%; width: <?php echo $dqPoints; ?>%; transition: width 0.4s ease;"></div>
    </div>

    <!-- Daily Checklist -->
    <div style="display: flex; flex-direction: column; gap: 4px; font-size: 10.5px;">
        <!-- 1. Adventure -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 2px 0;">
            <span style="color: <?php echo $dqProgress['p_adventure'] ? '#28a745' : '#555'; ?>;">
                <?php echo $dqProgress['p_adventure'] ? '✅' : '⏳'; ?> 1. Petualangan Hero
            </span>
            <span style="font-size: 9.5px; color: #777;">
                (<?php echo min(1, (int)$dqProgress['count_adventure']); ?>/1)
            </span>
        </div>

        <!-- 2. Training -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 2px 0;">
            <span style="color: <?php echo $dqProgress['p_train'] ? '#28a745' : '#555'; ?>;">
                <?php echo $dqProgress['p_train'] ? '✅' : '⏳'; ?> 2. Latih Pasukan
            </span>
            <span style="font-size: 9.5px; color: #777;">
                (<?php echo min(20, (int)$dqProgress['count_train']); ?>/20)
            </span>
        </div>

        <!-- 3. Building -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 2px 0;">
            <span style="color: <?php echo $dqProgress['p_build'] ? '#28a745' : '#555'; ?>;">
                <?php echo $dqProgress['p_build'] ? '✅' : '⏳'; ?> 3. Pembangunan Desa
            </span>
            <span style="font-size: 9.5px; color: #777;">
                (<?php echo min(2, (int)$dqProgress['count_build']); ?>/2)
            </span>
        </div>

        <!-- 4. Attack / Raid -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 2px 0;">
            <span style="color: <?php echo $dqProgress['p_attack'] ? '#28a745' : '#555'; ?>;">
                <?php echo $dqProgress['p_attack'] ? '✅' : '⏳'; ?> 4. Serangan / Raid
            </span>
            <span style="font-size: 9.5px; color: #777;">
                (<?php echo min(1, (int)$dqProgress['count_attack']); ?>/1)
            </span>
        </div>
    </div>

    <!-- Reward / Claim Button -->
    <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #cbb89d; text-align: center;">
        <?php if ($dqClaimed): ?>
            <div style="background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; border-radius: 4px; padding: 4px 6px; font-size: 10px; font-weight: bold;">
                🎁 Peti Perak Hari Ini Telah Diklaim (+50 Silver)
            </div>
        <?php elseif ($dqCanClaim): ?>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="margin: 0;">
                <input type="hidden" name="claim_daily_quest" value="1">
                <button type="submit" style="width: 100%; background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%); color: #fff; border: 1px solid #b45309; padding: 5px 10px; font-size: 10.5px; font-weight: bold; border-radius: 4px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.15); animation: pulse 1.5s infinite;">
                    🎁 BUKA PETI HARIAN (+50 SILVER 🪙)
                </button>
            </form>
        <?php else: ?>
            <div style="font-size: 9.5px; color: #8a6d3b;">
                ⭐ Selesaikan 4 misi di atas untuk membuka <b>Peti Perak (+50 Silver)</b>!
            </div>
        <?php endif; ?>
    </div>
</div>
