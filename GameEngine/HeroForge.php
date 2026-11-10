<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : HeroForge.php                                             ##
##  Type           : Engine / Business Logic for Tier 4 Legendary Crafting     ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  Developed by   : Shadow & TravianZ Community                               ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

class HeroForge
{
    const ESSENCE_ITEM_ID  = 210;
    const REQUIRED_ESSENCE = 30;

    const COST_WOOD   = 100000;
    const COST_CLAY   = 100000;
    const COST_IRON   = 100000;
    const COST_CROP   = 80000;
    const COST_SILVER = 2000;

    /**
     * Get all craftable Tier 4 Legendary recipes from catalog.
     */
    public static function getRecipes(): array
    {
        global $heroItemCatalog;
        if (!isset($heroItemCatalog)) {
            include_once __DIR__ . '/Data/hero_items.php';
        }

        $recipes = [];
        foreach ($heroItemCatalog as $itemId => $def) {
            if ((int)($def['tier'] ?? 0) === 4 && (int)$def['slot'] !== HSLOT_BAG) {
                $recipes[$itemId] = $def;
            }
        }
        return $recipes;
    }

    /**
     * Get player's current World Essence count in unequipped inventory.
     */
    public static function getEssenceCount(int $uid): int
    {
        global $database;
        $uid = (int)$uid;
        if ($uid <= 0) return 0;

        $rows = $database->query_return("
            SELECT SUM(quantity) as total 
            FROM " . TB_PREFIX . "hero_items 
            WHERE uid = $uid AND itemid = " . self::ESSENCE_ITEM_ID . " AND equipped = 0
        ");
        return (int)($rows[0]['total'] ?? 0);
    }

    /**
     * Get player's unequipped items in the matching slot, grouped by Tier (3 and 2).
     */
    public static function getEligibleSacrifices(int $uid, int $slot): array
    {
        global $database, $heroItemCatalog;
        if (!isset($heroItemCatalog)) {
            include_once __DIR__ . '/Data/hero_items.php';
        }

        $uid = (int)$uid;
        $slot = (int)$slot;
        $tier3 = [];
        $tier2 = [];

        $rows = $database->query_return("
            SELECT id, itemid, quantity 
            FROM " . TB_PREFIX . "hero_items 
            WHERE uid = $uid AND slot = $slot AND equipped = 0
            ORDER BY id ASC
        ");

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $iid = (int)$r['itemid'];
                if (!isset($heroItemCatalog[$iid])) continue;
                $tier = (int)($heroItemCatalog[$iid]['tier'] ?? 0);
                $name = function_exists('heroItemName') ? heroItemName($iid) : ($heroItemCatalog[$iid]['name'] ?? 'Item');
                $entry = [
                    'id'     => (int)$r['id'],
                    'itemid' => $iid,
                    'name'   => $name,
                    'tier'   => $tier
                ];
                if ($tier === 3) {
                    $tier3[] = $entry;
                } elseif ($tier === 2) {
                    $tier2[] = $entry;
                }
            }
        }

        return ['tier3' => $tier3, 'tier2' => $tier2];
    }

    /**
     * Check if player has all requirements to craft a recipe.
     */
    public static function checkRequirements(int $uid, int $villageWid, int $targetItemId): array
    {
        global $database, $heroItemCatalog;
        if (!isset($heroItemCatalog)) {
            include_once __DIR__ . '/Data/hero_items.php';
        }

        if (!isset($heroItemCatalog[$targetItemId]) || (int)$heroItemCatalog[$targetItemId]['tier'] !== 4) {
            return ['can_craft' => false, 'reason' => 'Resep item legendaris tidak valid.'];
        }

        $slot = (int)$heroItemCatalog[$targetItemId]['slot'];
        $essence = self::getEssenceCount($uid);
        $sacrifices = self::getEligibleSacrifices($uid, $slot);

        $hasT3 = count($sacrifices['tier3']) >= 1;
        $hasT2 = count($sacrifices['tier2']) >= 2;
        $hasEssence = $essence >= self::REQUIRED_ESSENCE;

        if (!class_exists('HeroItems')) {
            require_once __DIR__ . '/HeroItems.php';
        }
        $heroItems = new HeroItems();
        $silver = $heroItems->getSilver($uid);
        $hasSilver = ($silver >= self::COST_SILVER);

        $vRes = $database->getVillage($villageWid);
        $hasWood = ($vRes['wood'] ?? 0) >= self::COST_WOOD;
        $hasClay = ($vRes['clay'] ?? 0) >= self::COST_CLAY;
        $hasIron = ($vRes['iron'] ?? 0) >= self::COST_IRON;
        $hasCrop = ($vRes['crop'] ?? 0) >= self::COST_CROP;
        $hasResources = ($hasWood && $hasClay && $hasIron && $hasCrop);

        $canCraft = ($hasT3 && $hasT2 && $hasEssence && $hasResources && $hasSilver);

        return [
            'can_craft'     => $canCraft,
            'slot'          => $slot,
            'has_t3'        => $hasT3,
            'has_t2'        => $hasT2,
            'has_essence'   => $hasEssence,
            'has_resources' => $hasResources,
            'has_silver'    => $hasSilver,
            'silver_count'  => $silver,
            'essence_count' => $essence,
            'tier3_count'   => count($sacrifices['tier3']),
            'tier2_count'   => count($sacrifices['tier2']),
            'cost'          => [
                'wood'   => self::COST_WOOD,
                'clay'   => self::COST_CLAY,
                'iron'   => self::COST_IRON,
                'crop'   => self::COST_CROP,
                'silver' => self::COST_SILVER,
            ],
            'village_resources' => [
                'wood' => (int)($vRes['wood'] ?? 0),
                'clay' => (int)($vRes['clay'] ?? 0),
                'iron' => (int)($vRes['iron'] ?? 0),
                'crop' => (int)($vRes['crop'] ?? 0),
            ]
        ];
    }

    /**
     * Execute crafting.
     */
    public static function craft(int $uid, int $villageWid, int $targetItemId, int $t3RowId, array $t2RowIds): array
    {
        global $database, $heroItemCatalog;
        if (!isset($heroItemCatalog)) {
            include_once __DIR__ . '/Data/hero_items.php';
        }

        if (!isset($heroItemCatalog[$targetItemId]) || (int)$heroItemCatalog[$targetItemId]['tier'] !== 4) {
            return ['success' => false, 'message' => 'Resep perlengkapan legendaris tidak ditemukan.'];
        }

        $slot = (int)$heroItemCatalog[$targetItemId]['slot'];

        // 1. Validate Tier 3 sacrifice item
        $t3RowId = (int)$t3RowId;
        $t3Rows = $database->query_return("
            SELECT id, itemid, slot FROM " . TB_PREFIX . "hero_items 
            WHERE id = $t3RowId AND uid = $uid AND slot = $slot AND equipped = 0 LIMIT 1
        ");
        if (empty($t3Rows[0])) {
            return ['success' => false, 'message' => 'Item Tier 3 yang dipilih tidak valid atau sedang dipakai.'];
        }
        $t3Iid = (int)$t3Rows[0]['itemid'];
        if ((int)($heroItemCatalog[$t3Iid]['tier'] ?? 0) !== 3) {
            return ['success' => false, 'message' => 'Bahan utama harus merupakan item Tier 3.'];
        }

        // 2. Validate Tier 2 sacrifice items (must be 2 distinct rows)
        $t2Clean = array_values(array_unique(array_filter(array_map('intval', $t2RowIds))));
        if (count($t2Clean) < 2) {
            return ['success' => false, 'message' => 'Harus memilih 2 item Tier 2 yang berbeda sebagai bahan katalis.'];
        }
        $t2Id1 = $t2Clean[0];
        $t2Id2 = $t2Clean[1];
        if ($t2Id1 === $t3RowId || $t2Id2 === $t3RowId) {
            return ['success' => false, 'message' => 'Item Tier 3 dan Tier 2 tidak boleh sama.'];
        }

        $t2Rows = $database->query_return("
            SELECT id, itemid, slot FROM " . TB_PREFIX . "hero_items 
            WHERE id IN ($t2Id1, $t2Id2) AND uid = $uid AND slot = $slot AND equipped = 0
        ");
        if (count($t2Rows) < 2) {
            return ['success' => false, 'message' => 'Item Tier 2 tidak valid, tidak cocok dengan slot, atau sedang dipakai.'];
        }
        foreach ($t2Rows as $r) {
            $iid = (int)$r['itemid'];
            if ((int)($heroItemCatalog[$iid]['tier'] ?? 0) !== 2) {
                return ['success' => false, 'message' => 'Katalis penempaan harus merupakan item Tier 2.'];
            }
        }

        // 3. Validate World Essence
        $essenceCount = self::getEssenceCount($uid);
        if ($essenceCount < self::REQUIRED_ESSENCE) {
            return ['success' => false, 'message' => 'World Essence tidak mencukupi. Butuh ' . self::REQUIRED_ESSENCE . ' Essence (Anda memiliki ' . $essenceCount . ').'];
        }

        // 4. Validate Silver Balance
        if (!class_exists('HeroItems')) {
            require_once __DIR__ . '/HeroItems.php';
        }
        $heroItems = new HeroItems();
        $currentSilver = $heroItems->getSilver($uid);
        if ($currentSilver < self::COST_SILVER) {
            return ['success' => false, 'message' => 'Silver tidak mencukupi untuk penempaan. Butuh ' . number_format(self::COST_SILVER) . ' Silver (Anda memiliki ' . number_format($currentSilver) . ' Silver).'];
        }

        // 5. Validate Village Resources
        $vRes = $database->getVillage($villageWid);
        if (
            ($vRes['wood'] ?? 0) < self::COST_WOOD ||
            ($vRes['clay'] ?? 0) < self::COST_CLAY ||
            ($vRes['iron'] ?? 0) < self::COST_IRON ||
            ($vRes['crop'] ?? 0) < self::COST_CROP
        ) {
            return ['success' => false, 'message' => 'Sumber daya desa tidak mencukupi untuk penempaan legendaris.'];
        }

        // 6. Deduct Resources from Village
        $okRes = $database->modifyResource($villageWid, self::COST_WOOD, self::COST_CLAY, self::COST_IRON, self::COST_CROP, 0, true);
        if (!$okRes) {
            return ['success' => false, 'message' => 'Gagal memotong sumber daya desa.'];
        }

        // 7. Deduct Silver
        $okSilver = $heroItems->spendSilver($uid, self::COST_SILVER);
        if (!$okSilver) {
            // Rollback village resources
            $database->modifyResource($villageWid, -self::COST_WOOD, -self::COST_CLAY, -self::COST_IRON, -self::COST_CROP, 0, true);
            return ['success' => false, 'message' => 'Gagal memotong saldo Silver. Pastikan Anda memiliki minimal ' . number_format(self::COST_SILVER) . ' Silver.'];
        }

        // 8. Consume Sacrificed Items
        $database->query("DELETE FROM " . TB_PREFIX . "hero_items WHERE id = $t3RowId AND uid = $uid LIMIT 1");
        $database->query("DELETE FROM " . TB_PREFIX . "hero_items WHERE id IN ($t2Id1, $t2Id2) AND uid = $uid");

        // 9. Deduct World Essence
        $essRows = $database->query_return("
            SELECT id, quantity FROM " . TB_PREFIX . "hero_items 
            WHERE uid = $uid AND itemid = " . self::ESSENCE_ITEM_ID . " AND equipped = 0 
            ORDER BY id ASC
        ");
        $needed = self::REQUIRED_ESSENCE;
        foreach ($essRows as $er) {
            $erId = (int)$er['id'];
            $qty = (int)$er['quantity'];
            if ($qty <= $needed) {
                $database->query("DELETE FROM " . TB_PREFIX . "hero_items WHERE id = $erId LIMIT 1");
                $needed -= $qty;
            } else {
                $rem = $qty - $needed;
                $database->query("UPDATE " . TB_PREFIX . "hero_items SET quantity = $rem WHERE id = $erId LIMIT 1");
                $needed = 0;
            }
            if ($needed <= 0) break;
        }

        // 10. Award Tier 4 Item
        $newRowId = $heroItems->addItem($uid, $targetItemId, 1);
        $heroItems->invalidateCaches($uid);

        $itemName = function_exists('heroItemName') ? heroItemName($targetItemId) : $heroItemCatalog[$targetItemId]['name'];

        // 11. Send In-Game Victory Report / Notice
        $time = time();
        $noticeTopic = "🔥 [ALTAR TEMPA] Mahakarya Legendaris Berhasil Diciptakan: {$itemName}!";
        $noticeMsg = "Keajaiban di Altar Penempaan Kuno!\n\n" .
                     "Dengan melebur 1 Item Tier 3, 2 Item Tier 2, 30 World Essence, dan 2.000 Silver, pahlawanmu berhasil menciptakan perlengkapan legendaris berkekuatan dewa:\n\n" .
                     "✨ {$itemName} (Tier 4 Legendary)\n\n" .
                     "Perlengkapan ini kini telah tersimpan di dalam Inventori Pahlawanmu dan siap untuk dipakai dalam pertempuran!";
        $database->addNotice($uid, $villageWid, 0, 28, $noticeTopic, $noticeMsg, $time);

        return [
            'success' => true,
            'itemid'  => $targetItemId,
            'name'    => $itemName,
            'rowid'   => $newRowId,
            'message' => "Selamat! Anda berhasil menempa item Legendaris: {$itemName}!"
        ];
    }
}

