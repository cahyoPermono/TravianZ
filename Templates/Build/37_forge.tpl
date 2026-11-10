<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : HERO T4 FORGE TEMPLATE                                    ##
##  Type           : BUILDING TEMPLATE (Altar Penempaan Legendaris)            ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  Developed by   : Shadow & TravianZ Community                               ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

if (!class_exists('HeroForge')) {
    $enginePath = dirname(__DIR__, 2) . '/GameEngine/';
    if (file_exists($enginePath . 'HeroForge.php')) {
        require_once $enginePath . 'HeroForge.php';
    }
}
if (!class_exists('HeroItems')) {
    $enginePath = dirname(__DIR__, 2) . '/GameEngine/';
    if (file_exists($enginePath . 'HeroItems.php')) {
        require_once $enginePath . 'HeroItems.php';
    }
}

$forgeMsg = '';
$forgeMsgType = 'error';

// Process crafting action
if (isset($_POST['forge_action']) && $_POST['forge_action'] === 'craft') {
    $targetItemId = (int)($_POST['target_item_id'] ?? 0);
    $t3RowId      = (int)($_POST['t3_row_id'] ?? 0);
    $t2Id1        = (int)($_POST['t2_id1'] ?? 0);
    $t2Id2        = (int)($_POST['t2_id2'] ?? 0);

    $res = HeroForge::craft($session->uid, (int)$village->wid, $targetItemId, $t3RowId, [$t2Id1, $t2Id2]);
    if ($res['success']) {
        $forgeMsg = $res['message'];
        $forgeMsgType = 'success';
    } else {
        $forgeMsg = $res['message'];
        $forgeMsgType = 'error';
    }
}

$recipes      = HeroForge::getRecipes();
$essenceCount = HeroForge::getEssenceCount($session->uid);

$heroItemsObj = new HeroItems();
$silverCount  = $heroItemsObj->getSilver($session->uid);
$silverOk     = ($silverCount >= HeroForge::COST_SILVER);

$woodOk = ($village->awood >= HeroForge::COST_WOOD);
$clayOk = ($village->aclay >= HeroForge::COST_CLAY);
$ironOk = ($village->airon >= HeroForge::COST_IRON);
$cropOk = ($village->acrop >= HeroForge::COST_CROP);
$allResOk = ($woodOk && $clayOk && $ironOk && $cropOk);
$essenceOk = ($essenceCount >= HeroForge::REQUIRED_ESSENCE);

$slotNames = [
    HSLOT_HELMET => 'Helm Legendaris',
    HSLOT_BODY   => 'Zirah Tubuh Legendaris',
    HSLOT_RIGHT  => 'Senjata Universal Legendaris',
    HSLOT_LEFT   => 'Tangan Kiri & Perisai Legendaris',
    HSLOT_SHOES  => 'Sepatu & Pelindung Kaki Legendaris',
    HSLOT_HORSE  => 'Tunggangan Legendaris'
];

$slotEligible = [];
for ($s = 1; $s <= 6; $s++) {
    $slotEligible[$s] = HeroForge::getEligibleSacrifices($session->uid, $s);
}
?>

<link rel="stylesheet" href="css/hero_items.css?v=20261110_t4v2" type="text/css">
<style type="text/css">
.forge-container {
    margin-top: 8px;
    font-family: inherit;
}
.forge-banner {
    background: linear-gradient(135deg, #2c3e50, #1a252f);
    color: #ecf0f1;
    border: 1px solid #34495e;
    border-radius: 6px;
    padding: 12px 14px;
    margin-bottom: 14px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.forge-banner h2 {
    color: #f39c12;
    margin: 0 0 6px 0;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.forge-banner p {
    margin: 0 0 8px 0;
    font-size: 11.5px;
    line-height: 1.5;
    color: #bdc3c7;
}
.forge-requirements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 8px;
    margin-top: 8px;
}
.forge-req-card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 11px;
    text-align: center;
}
.forge-req-card.ok {
    border-color: #27ae60;
    background: rgba(39, 174, 96, 0.12);
}
.forge-req-card.missing {
    border-color: #e74c3c;
    background: rgba(231, 76, 60, 0.12);
}
.forge-req-card b {
    display: block;
    margin-bottom: 2px;
}
.forge-alert {
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 12px;
    font-weight: bold;
    font-size: 11.5px;
}
.forge-alert.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.forge-alert.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.forge-section-title {
    font-size: 13px;
    font-weight: bold;
    color: #d35400;
    border-bottom: 2px solid #e67e22;
    padding-bottom: 4px;
    margin: 16px 0 10px 0;
}
.forge-item-card {
    background: #fdfdfd;
    border: 1px solid #dcdcdc;
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.forge-item-header {
    display: flex;
    align-items: center;
    gap: 12px;
}
.forge-item-details {
    flex: 1;
}
.forge-item-name {
    font-size: 12.5px;
    font-weight: bold;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.forge-badge-legendary {
    background: linear-gradient(135deg, #f39c12, #d35400);
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 1px 6px;
    border-radius: 3px;
    letter-spacing: 0.3px;
}
.forge-item-bonus {
    margin-top: 4px;
    font-size: 11px;
    color: #7f8c8d;
    line-height: 1.4;
}
.forge-form {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e0e0e0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.forge-select-group {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 10.5px;
}
.forge-select-group label {
    font-weight: bold;
    color: #555;
}
.forge-select {
    padding: 3px 6px;
    font-size: 11px;
    border: 1px solid #ccc;
    border-radius: 3px;
    background: #fff;
    max-width: 220px;
}
.forge-btn-craft {
    margin-left: auto;
    padding: 6px 14px;
    font-size: 11px;
    font-weight: bold;
    color: #fff;
    background: linear-gradient(#e67e22, #d35400);
    border: 1px solid #b94a00;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.forge-btn-craft:hover {
    background: linear-gradient(#f39c12, #e67e22);
}
.forge-btn-craft:disabled {
    background: #bdc3c7;
    border-color: #95a5a6;
    color: #7f8c8d;
    cursor: not-allowed;
    box-shadow: none;
}
.forge-locked-note {
    font-size: 10.5px;
    color: #c0392b;
    font-style: italic;
    margin-top: 4px;
}
</style>

<div class="forge-container">
    <?php if ($forgeMsg !== ''): ?>
        <div class="forge-alert <?php echo $forgeMsgType; ?>">
            <?php echo htmlspecialchars($forgeMsg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="forge-banner">
        <h2>🔥 Altar Penempaan Kuno (Legendary Forge)</h2>
        <p>
            Di altar magis ini, para pahlawan dapat melebur perlengkapan langka dan <b>World Essence</b> yang diperebutkan dari Sarang Bandit serta World Boss untuk menciptakan <b>Perlengkapan Legendaris Tier 4</b>.
            Seluruh item legendaris bersifat <b>Universal</b> dan dapat digunakan oleh hero suku apapun tanpa batasan!
        </p>

        <div style="font-size: 11px; font-weight: bold; color: #f1c40f; margin-bottom: 4px;">
            📜 Rumus Standar Penempaan 1 Item Legendaris:
        </div>
        <div style="font-size: 10.5px; color: #ecf0f1; margin-bottom: 8px;">
            • <b>1x Item Tier 3</b> (slot yang sama, unequipped)<br>
            • <b>2x Item Tier 2</b> (slot yang sama, unequipped)<br>
            • <b>30x World Essence</b> (diperoleh dari Bandit Camp Tier 2/3 &amp; World Boss)<br>
            • <b>2.000 Silver</b> (saldo akun pahlawan)<br>
            • <b>100.000 Kayu, 100.000 Tanah Liat, 100.000 Besi, 80.000 Gandum</b> (dari desa saat ini)
        </div>

        <div style="margin-bottom: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="manual.php?typ=8&amp;s=1" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #f39c12, #d35400); color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold; border: 1px solid #ffd700; box-shadow: 0 1px 4px rgba(0,0,0,0.3);">
                📖 Buku Panduan: Info Drop World Essence &amp; Syarat Tempa
            </a>
            <a href="bandit.php" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; background: #34495e; color: #ecf0f1; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold; border: 1px solid #2c3e50;">
                🗺️ Radar Bandit &amp; World Boss
            </a>
        </div>

        <div class="forge-requirements-grid">
            <div class="forge-req-card <?php echo $essenceOk ? 'ok' : 'missing'; ?>">
                <b>💎 World Essence</b>
                <span><?php echo number_format($essenceCount); ?> / <?php echo HeroForge::REQUIRED_ESSENCE; ?></span>
            </div>
            <div class="forge-req-card <?php echo $silverOk ? 'ok' : 'missing'; ?>">
                <b>🪙 2.000 Silver</b>
                <span><?php echo number_format($silverCount); ?> / <?php echo number_format(HeroForge::COST_SILVER); ?></span>
            </div>
            <div class="forge-req-card <?php echo $woodOk ? 'ok' : 'missing'; ?>">
                <b>🪵 Kayu</b>
                <span><?php echo number_format((int)$village->awood); ?> / 100k</span>
            </div>
            <div class="forge-req-card <?php echo $clayOk ? 'ok' : 'missing'; ?>">
                <b>🧱 Tanah Liat</b>
                <span><?php echo number_format((int)$village->aclay); ?> / 100k</span>
            </div>
            <div class="forge-req-card <?php echo $ironOk ? 'ok' : 'missing'; ?>">
                <b>⛓️ Besi</b>
                <span><?php echo number_format((int)$village->airon); ?> / 100k</span>
            </div>
            <div class="forge-req-card <?php echo $cropOk ? 'ok' : 'missing'; ?>">
                <b>🌾 Gandum</b>
                <span><?php echo number_format((int)$village->acrop); ?> / 80k</span>
            </div>
        </div>
    </div>

    <?php
    // Group recipes by slot
    $recipesBySlot = [];
    foreach ($recipes as $iid => $def) {
        $slot = (int)$def['slot'];
        $recipesBySlot[$slot][$iid] = $def;
    }
    ?>

    <?php for ($slot = 1; $slot <= 6; $slot++): ?>
        <?php if (empty($recipesBySlot[$slot])) continue; ?>
        <div class="forge-section-title">
            🛡️ <?php echo $slotNames[$slot] ?? ('Slot ' . $slot); ?>
            <span style="font-size: 11px; font-weight: normal; color: #7f8c8d; margin-left: 6px;">
                (Bahan tersedia di tas: <?php echo count($slotEligible[$slot]['tier3']); ?>x Tier 3 | <?php echo count($slotEligible[$slot]['tier2']); ?>x Tier 2)
            </span>
        </div>

        <?php foreach ($recipesBySlot[$slot] as $iid => $def): ?>
            <?php
            $itemName = function_exists('heroItemName') ? heroItemName($iid) : $def['name'];
            $itemBonus = function_exists('heroItemBonusText') ? heroItemBonusText($iid) : '';
            $t3List = $slotEligible[$slot]['tier3'];
            $t2List = $slotEligible[$slot]['tier2'];

            $hasEnoughSacrifices = (count($t3List) >= 1 && count($t2List) >= 2);
            $canCraftThis = ($hasEnoughSacrifices && $essenceOk && $allResOk && $silverOk);
            ?>

            <div class="forge-item-card">
                <div class="forge-item-header">
                    <span class="heroT4Item item<?php echo $iid; ?> heroT4ItemLarge heroT4LegendaryGlow"></span>
                    <div class="forge-item-details">
                        <div class="forge-item-name">
                            <span><?php echo htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="forge-badge-legendary">★ Tier 4 Legendary</span>
                        </div>
                        <?php if ($itemBonus !== ''): ?>
                            <div class="forge-item-bonus">
                                <b>Efek:</b> <?php echo htmlspecialchars($itemBonus, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="" method="POST" class="forge-form">
                    <input type="hidden" name="forge_action" value="craft">
                    <input type="hidden" name="target_item_id" value="<?php echo $iid; ?>">

                    <div class="forge-select-group">
                        <label>Item Tier 3 (Tumbal Utama):</label>
                        <select name="t3_row_id" class="forge-select" required>
                            <?php if (empty($t3List)): ?>
                                <option value="">-- Tidak ada item Tier 3 di tas --</option>
                            <?php else: ?>
                                <?php foreach ($t3List as $t3): ?>
                                    <option value="<?php echo $t3['id']; ?>"><?php echo htmlspecialchars($t3['name'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo $t3['id']; ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="forge-select-group">
                        <label>Item Tier 2 (Katalis 1):</label>
                        <select name="t2_id1" class="forge-select" required>
                            <?php if (empty($t2List)): ?>
                                <option value="">-- Tidak ada item Tier 2 --</option>
                            <?php else: ?>
                                <?php foreach ($t2List as $idx => $t2): ?>
                                    <option value="<?php echo $t2['id']; ?>"><?php echo htmlspecialchars($t2['name'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo $t2['id']; ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="forge-select-group">
                        <label>Item Tier 2 (Katalis 2):</label>
                        <select name="t2_id2" class="forge-select" required>
                            <?php if (count($t2List) < 2): ?>
                                <option value="">-- Butuh min 2x item Tier 2 --</option>
                            <?php else: ?>
                                <?php foreach ($t2List as $idx => $t2): ?>
                                    <option value="<?php echo $t2['id']; ?>" <?php echo ($idx === 1) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t2['name'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo $t2['id']; ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <button type="submit" class="forge-btn-craft" <?php echo $canCraftThis ? '' : 'disabled'; ?> onclick="return confirm('Apakah Anda yakin ingin melebur item dan bahan terpilih untuk menempa <?php echo htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8'); ?>? Tindakan ini tidak dapat dibatalkan.');">
                        🔥 Tempa Item Legendaris
                    </button>
                </form>

                <?php if (!$canCraftThis): ?>
                    <div class="forge-locked-note">
                        ⚠️ Belum dapat ditempa: 
                        <?php
                        $reasons = [];
                        if (!$hasEnoughSacrifices) {
                            $reasons[] = 'Butuh min 1x Item Tier 3 dan 2x Item Tier 2 yang tidak terpakai di slot ini';
                        }
                        if (!$essenceOk) {
                            $reasons[] = 'World Essence kurang (' . $essenceCount . '/' . HeroForge::REQUIRED_ESSENCE . ')';
                        }
                        if (!$silverOk) {
                            $reasons[] = 'Silver kurang (' . number_format($silverCount) . '/' . number_format(HeroForge::COST_SILVER) . ')';
                        }
                        if (!$allResOk) {
                            $reasons[] = 'Sumber daya desa belum mencukupi (100k/100k/100k/80k)';
                        }
                        echo implode(' • ', $reasons);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endfor; ?>
</div>

