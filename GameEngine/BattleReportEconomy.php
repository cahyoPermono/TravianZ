<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : BattleReportEconomy.php                                   ##
##  Type           : Battle Report Economic Analysis (Profit & Loss / Casualties)##
##  Purpose        : Calculate resource value of troop casualties vs loot       ##
##                   revenue to show players if a battle was profitable/loss   ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class BattleReportEconomy
{
    /**
     * Cache for unit costs.
     */
    private static $unitCostCache = [];

    /**
     * Ensure unitdata.php is loaded.
     */
    public static function ensureDataLoaded(): void
    {
        if (!isset($GLOBALS['u1']) || !isset($GLOBALS['u91'])) {
            $path = __DIR__ . '/Data/unitdata.php';
            if (file_exists($path)) {
                include_once $path;
            }
        }
    }

    /**
     * Get the resource cost of a single unit.
     *
     * @param int $unitId Unit index (1-100)
     * @return array [wood, clay, iron, crop, total]
     */
    public static function getUnitCost(int $unitId): array
    {
        if (isset(self::$unitCostCache[$unitId])) {
            return self::$unitCostCache[$unitId];
        }

        self::ensureDataLoaded();

        $varName = 'u' . $unitId;
        $unitData = $GLOBALS[$varName] ?? null;

        if (is_array($unitData)) {
            $wood = (int)($unitData['wood'] ?? 0);
            $clay = (int)($unitData['clay'] ?? 0);
            $iron = (int)($unitData['iron'] ?? 0);
            $crop = (int)($unitData['crop'] ?? 0);
            $total = $wood + $clay + $iron + $crop;
        } else {
            // Paranormal spirits fallback
            if (class_exists('Paranormal') && isset(Paranormal::$spiritStats[$unitId])) {
                $cost = Paranormal::$spiritStats[$unitId]['cost'] ?? [];
                $wood = (int)($cost['wood'] ?? 0);
                $clay = (int)($cost['clay'] ?? 0);
                $iron = (int)($cost['iron'] ?? 0);
                $crop = (int)($cost['crop'] ?? 0);
                $total = $wood + $clay + $iron + $crop;
            } else {
                $wood = $clay = $iron = $crop = $total = 0;
            }
        }

        $res = [
            'wood'  => $wood,
            'clay'  => $clay,
            'iron'  => $iron,
            'crop'  => $crop,
            'total' => $total,
        ];

        self::$unitCostCache[$unitId] = $res;
        return $res;
    }

    /**
     * Calculate economic statistics for a battle report.
     *
     * @param array $dataarray Expanded CSV array from report data
     * @param int $viewingUid UID of user viewing the report
     * @param bool $isTotalLoss True if no troops returned (3.tpl)
     * @return array Comprehensive economic balance array
     */
    public static function calculate(array $dataarray, int $viewingUid = 0, bool $isTotalLoss = false): array
    {
        self::ensureDataLoaded();

        $attackerUid = (int)($dataarray[0] ?? 0);
        $attackerTribe = !empty($dataarray[2]) ? (int)$dataarray[2] : 5;
        $defUid = (int)($dataarray[28] ?? 0);
        $defTribe = !empty($dataarray[34]) ? (int)$dataarray[34] : 1;

        // 1. Detect Spying
        $isSpy = false;
        foreach ($dataarray as $val) {
            if (is_string($val) && (
                strpos($val, '<div class="res">') !== false ||
                strpos($val, 'RC_CRANNY_CAPACITY') !== false ||
                strpos($val, 'RC_WALL_LEVEL') !== false
            )) {
                $isSpy = true;
                break;
            }
        }
        if (!$isSpy && !empty($dataarray[268]) && !empty($dataarray[269])) {
            $isSpy = true;
        }

        // 2. Attacker Casualties Cost
        $attCost = ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'total' => 0];
        $attDeadUnits = 0;
        $attStartUnit = ($attackerTribe - 1) * 10 + 1;

        for ($i = 0; $i < 10; $i++) {
            $unitId = $attStartUnit + $i;
            if ($isTotalLoss) {
                // In 3.tpl, troops sent at 3..12 all died
                $deadCount = max(0, (int)($dataarray[3 + $i] ?? 0));
            } else {
                // In 1.tpl, troops dead at 13..22
                $deadCount = max(0, (int)($dataarray[13 + $i] ?? 0));
            }

            if ($deadCount > 0) {
                $attDeadUnits += $deadCount;
                $cost = self::getUnitCost($unitId);
                $attCost['wood']  += $deadCount * $cost['wood'];
                $attCost['clay']  += $deadCount * $cost['clay'];
                $attCost['iron']  += $deadCount * $cost['iron'];
                $attCost['crop']  += $deadCount * $cost['crop'];
                $attCost['total'] += $deadCount * $cost['total'];
            }
        }

        // 3. Attacker Loot / Revenue
        $loot = ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'total' => 0, 'capacity' => 0];
        if (!$isTotalLoss && !$isSpy) {
            $loot['wood']  = max(0, (int)($dataarray[23] ?? 0));
            $loot['clay']  = max(0, (int)($dataarray[24] ?? 0));
            $loot['iron']  = max(0, (int)($dataarray[25] ?? 0));
            $loot['crop']  = max(0, (int)($dataarray[26] ?? 0));
            $loot['total'] = $loot['wood'] + $loot['clay'] + $loot['iron'] + $loot['crop'];
            $loot['capacity'] = max(0, (int)($dataarray[27] ?? 0));
        }

        // 4. Attacker Net Profit/Loss
        $netWood  = $loot['wood']  - $attCost['wood'];
        $netClay  = $loot['clay']  - $attCost['clay'];
        $netIron  = $loot['iron']  - $attCost['iron'];
        $netCrop  = $loot['crop']  - $attCost['crop'];
        $netTotal = $loot['total'] - $attCost['total'];

        $attStatus = 'even';
        if ($netTotal > 0) {
            $attStatus = 'profit';
        } elseif ($netTotal < 0) {
            $attStatus = 'loss';
        }

        $marginPct = 0.0;
        if ($attCost['total'] > 0) {
            $marginPct = round(($netTotal / $attCost['total']) * 100, 1);
        } elseif ($loot['total'] > 0) {
            $marginPct = 100.0;
        }

        // 5. Defender Casualties Cost
        $defCost = ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'total' => 0];
        $defDeadUnits = 0;

        if (!$isTotalLoss) {
            // Main defender casualties (indices 45..54)
            $defStartUnit = ($defTribe - 1) * 10 + 1;
            for ($i = 0; $i < 10; $i++) {
                $unitId = $defStartUnit + $i;
                $deadCount = max(0, (int)($dataarray[45 + $i] ?? 0));
                if ($deadCount > 0) {
                    $defDeadUnits += $deadCount;
                    $cost = self::getUnitCost($unitId);
                    $defCost['wood']  += $deadCount * $cost['wood'];
                    $defCost['clay']  += $deadCount * $cost['clay'];
                    $defCost['iron']  += $deadCount * $cost['iron'];
                    $defCost['crop']  += $deadCount * $cost['crop'];
                    $defCost['total'] += $deadCount * $cost['total'];
                }
            }

            // Reinforcement casualties
            $defArray = [1, $dataarray[55] ?? 0, $dataarray[76] ?? 0, $dataarray[97] ?? 0, $dataarray[118] ?? 0, $dataarray[139] ?? 0, $dataarray[160] ?? 0, $dataarray[181] ?? 0, $dataarray[202] ?? 0, $dataarray[223] ?? 0];
            for ($index = 1; $index <= 9; $index++) {
                if (empty($defArray[$index])) continue;
                $rStart = ($index - 1) * 10 + 1;
                $troopsDeadStart = ($index * 21 + 35) + 10;
                for ($i = 0; $i < 10; $i++) {
                    $unitId = $rStart + $i;
                    $deadCount = max(0, (int)($dataarray[$troopsDeadStart + $i] ?? 0));
                    if ($deadCount > 0) {
                        $defDeadUnits += $deadCount;
                        $cost = self::getUnitCost($unitId);
                        $defCost['wood']  += $deadCount * $cost['wood'];
                        $defCost['clay']  += $deadCount * $cost['clay'];
                        $defCost['iron']  += $deadCount * $cost['iron'];
                        $defCost['crop']  += $deadCount * $cost['crop'];
                        $defCost['total'] += $deadCount * $cost['total'];
                    }
                }
            }

            // Nusantara reinforcement casualties
            foreach ($dataarray as $item) {
                if (is_string($item) && strpos($item, 'NUSREINF:') === 0) {
                    $parts = explode(';', substr($item, 9));
                    if (count($parts) >= 20) {
                        for ($ni = 0; $ni < 10; $ni++) {
                            $deadCount = max(0, (int)($parts[10 + $ni] ?? 0));
                            if ($deadCount > 0) {
                                $unitId = 91 + $ni;
                                $defDeadUnits += $deadCount;
                                $cost = self::getUnitCost($unitId);
                                $defCost['wood']  += $deadCount * $cost['wood'];
                                $defCost['clay']  += $deadCount * $cost['clay'];
                                $defCost['iron']  += $deadCount * $cost['iron'];
                                $defCost['crop']  += $deadCount * $cost['crop'];
                                $defCost['total'] += $deadCount * $cost['total'];
                            }
                        }
                    }
                    break;
                }
            }

            // Paranormal spirits casualties
            foreach ($dataarray as $item) {
                if (is_string($item) && strpos($item, 'PARANORMAL:') === 0) {
                    $parts = explode(';', substr($item, 11));
                    $spiritCosts = [
                        1 => ['wood' => 100, 'clay' => 120, 'iron' => 80,  'crop' => 150, 'total' => 450], // Pocong
                        2 => ['wood' => 120, 'clay' => 80,  'iron' => 100, 'crop' => 180, 'total' => 480], // Kuntilanak
                        3 => ['wood' => 250, 'clay' => 200, 'iron' => 280, 'crop' => 300, 'total' => 1030] // Genderuwo
                    ];
                    for ($s = 1; $s <= 3; $s++) {
                        $deadCount = max(0, (int)($parts[3 + $s] ?? 0));
                        if ($deadCount > 0 && isset($spiritCosts[$s])) {
                            $sc = $spiritCosts[$s];
                            $defCost['wood']  += $deadCount * $sc['wood'];
                            $defCost['clay']  += $deadCount * $sc['clay'];
                            $defCost['iron']  += $deadCount * $sc['iron'];
                            $defCost['crop']  += $deadCount * $sc['crop'];
                            $defCost['total'] += $deadCount * $sc['total'];
                        }
                    }
                    break;
                }
            }
        }

        // Defender Total Loss (Casualty cost + looted resources)
        $defTotalLoss = [
            'wood'  => $defCost['wood'] + $loot['wood'],
            'clay'  => $defCost['clay'] + $loot['clay'],
            'iron'  => $defCost['iron'] + $loot['iron'],
            'crop'  => $defCost['crop'] + $loot['crop'],
            'total' => $defCost['total'] + $loot['total'],
        ];

        // 6. Viewer Role
        $viewerRole = 'spectator';
        if ($viewingUid > 0) {
            if ($viewingUid === $attackerUid) {
                $viewerRole = 'attacker';
            } elseif ($viewingUid === $defUid) {
                $viewerRole = 'defender';
            }
        }

        return [
            'has_combat_data' => ($attDeadUnits > 0 || $defDeadUnits > 0 || $loot['total'] > 0 || $isTotalLoss),
            'is_total_loss'   => $isTotalLoss,
            'is_spy'          => $isSpy,
            'viewer_role'     => $viewerRole,

            'loot' => $loot,

            'attacker' => [
                'uid'               => $attackerUid,
                'tribe'             => $attackerTribe,
                'total_dead_units'  => $attDeadUnits,
                'casualties_cost'   => $attCost,
                'net_balance'       => [
                    'wood'  => $netWood,
                    'clay'  => $netClay,
                    'iron'  => $netIron,
                    'crop'  => $netCrop,
                    'total' => $netTotal,
                ],
                'status'            => $attStatus,
                'profit_margin_pct' => $marginPct,
            ],

            'defender' => [
                'uid'               => $defUid,
                'tribe'             => $defTribe,
                'total_dead_units'  => $defDeadUnits,
                'casualties_cost'   => $defCost,
                'total_loss'        => $defTotalLoss,
            ],

            'summary' => [
                'total_resources_destroyed' => $attCost['total'] + $defCost['total'],
                'net_profit_loss'           => $netTotal,
                'formatted_pl'              => ($netTotal > 0 ? '+' : '') . number_format($netTotal),
            ]
        ];
    }

    /**
     * Render the Economic Battle Analysis HTML card and table.
     *
     * @param array $economy Result from calculate()
     * @return string Rendered HTML
     */
    public static function renderHtml(array $economy): string
    {
        if (!$economy['has_combat_data']) {
            return '';
        }

        $att = $economy['attacker'];
        $def = $economy['defender'];
        $loot = $economy['loot'];
        $role = $economy['viewer_role'];
        $isTotalLoss = $economy['is_total_loss'];

        $netTotal = $att['net_balance']['total'];
        $isProfit = ($netTotal > 0);
        $isLoss   = ($netTotal < 0);

        // Styling tokens
        if ($isProfit) {
            $bannerBg = 'linear-gradient(135deg, #065f46 0%, #047857 100%)';
            $bannerBorder = '#10b981';
            $bannerText = '#ecfdf5';
            $badgeBg = '#10b981';
            $badgeText = '#ffffff';
            $statusLabel = '🟢 SURPLUS / UNTUNG (PROFIT)';
            $adviceMsg = 'Operasi militer menghasilkan keuntungan bersih! Hasil rampasan melampaui seluruh modal pasukan yang gugur.';
        } elseif ($isLoss) {
            $bannerBg = 'linear-gradient(135deg, #881337 0%, #9f1239 100%)';
            $bannerBorder = '#f43f5e';
            $bannerText = '#fff1f2';
            $badgeBg = '#e11d48';
            $badgeText = '#ffffff';
            $statusLabel = '🔴 DEFISIT / RUGI (LOSS)';
            $adviceMsg = 'Operasi militer mengalami defisit! Nilai bahan mentah prajurit yang gugur melebihi sumber daya yang dijarah.';
        } else {
            $bannerBg = 'linear-gradient(135deg, #334155 0%, #475569 100%)';
            $bannerBorder = '#94a3b8';
            $bannerText = '#f8fafc';
            $badgeBg = '#64748b';
            $badgeText = '#ffffff';
            $statusLabel = '⚪ IMPAS / BREAK-EVEN';
            $adviceMsg = 'Operasi militer berakhir seimbang antara hasil rampasan dan biaya korban pasukan.';
        }

        // Highlight if viewing as defender
        if ($role === 'defender') {
            $bannerBg = 'linear-gradient(135deg, #451a03 0%, #78350f 100%)';
            $bannerBorder = '#f59e0b';
            $statusLabel = '🛡️ RINGKASAN KERUGIAN DESA BERTAHAN';
            $adviceMsg = 'Total kerugian yang diderita desa: sumber daya terjarah ditambah biaya korban prajurit penjaga.';
        }

        ob_start();
        ?>
        <div class="battle-economy-report" style="margin: 14px 0 10px 0; font-family: inherit;">
            <!-- BANNER STATUS -->
            <div style="background: <?php echo $bannerBg; ?>; border: 1.5px solid <?php echo $bannerBorder; ?>; border-radius: 8px 8px 0 0; padding: 10px 14px; color: <?php echo $bannerText; ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">📊</span>
                        <div>
                            <div style="font-weight: 800; font-size: 13px; letter-spacing: 0.3px;"><?php echo $statusLabel; ?></div>
                            <div style="font-size: 11px; opacity: 0.9; margin-top: 1px;"><?php echo $adviceMsg; ?></div>
                        </div>
                    </div>
                    <div>
                        <?php if ($role === 'defender'): ?>
                            <span style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 14px; font-weight: 800; font-size: 12px; color: #fca5a5;">
                                -<?php echo number_format($def['total_loss']['total']); ?> Sumber Daya
                            </span>
                        <?php else: ?>
                            <span style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeText; ?>; padding: 4px 10px; border-radius: 14px; font-weight: 800; font-size: 12px;">
                                <?php echo ($netTotal > 0 ? '+' : '') . number_format($netTotal); ?> Sumber Daya 
                                <?php if ($att['profit_margin_pct'] != 0): ?>
                                    (<?php echo ($att['profit_margin_pct'] > 0 ? '+' : '') . $att['profit_margin_pct']; ?>%)
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TABEL NERACA RINCIAN SUMBER DAYA -->
            <table class="tableNone" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; border-top: none; font-size: 11px; background: #ffffff;">
                <thead>
                    <tr style="background: #f1f5f9; color: #334155; border-bottom: 1.5px solid #cbd5e1;">
                        <th style="padding: 7px 10px; text-align: left; font-weight: bold; width: 32%;">Parameter Perang</th>
                        <th style="padding: 7px 6px; text-align: right; width: 13%;"><img class="r1" src="img/x.gif" title="Kayu" /> Kayu</th>
                        <th style="padding: 7px 6px; text-align: right; width: 13%;"><img class="r2" src="img/x.gif" title="Tanah Liat" /> Liat</th>
                        <th style="padding: 7px 6px; text-align: right; width: 13%;"><img class="r3" src="img/x.gif" title="Besi" /> Besi</th>
                        <th style="padding: 7px 6px; text-align: right; width: 13%;"><img class="r4" src="img/x.gif" title="Gandum" /> Gandum</th>
                        <th style="padding: 7px 10px; text-align: right; width: 16%; font-weight: bold;">📦 Total</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- BARIS 1: RAMPASAN -->
                    <tr style="border-bottom: 1px solid #e2e8f0; background: #f0fdf4;">
                        <td style="padding: 6px 10px; font-weight: bold; color: #166534;">
                            💰 Hasil Rampasan Perang
                            <div style="font-size: 9.5px; color: #15803d; font-weight: normal;">(Pendapatan dijarah penyerang)</div>
                        </td>
                        <td style="padding: 6px; text-align: right; color: #166534;">+<?php echo number_format($loot['wood']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #166534;">+<?php echo number_format($loot['clay']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #166534;">+<?php echo number_format($loot['iron']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #166534;">+<?php echo number_format($loot['crop']); ?></td>
                        <td style="padding: 6px 10px; text-align: right; font-weight: bold; color: #15803d;">+<?php echo number_format($loot['total']); ?></td>
                    </tr>

                    <!-- BARIS 2: KORBAN PENYERANG -->
                    <tr style="border-bottom: 1px solid #e2e8f0; background: #fff5f5;">
                        <td style="padding: 6px 10px; font-weight: bold; color: #991b1b;">
                            💀 Modal Korban Penyerang
                            <div style="font-size: 9.5px; color: #b91c1c; font-weight: normal;">(<?php echo number_format($att['total_dead_units']); ?> prajurit gugur)</div>
                        </td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($att['casualties_cost']['wood']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($att['casualties_cost']['clay']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($att['casualties_cost']['iron']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($att['casualties_cost']['crop']); ?></td>
                        <td style="padding: 6px 10px; text-align: right; font-weight: bold; color: #b91c1c;">-<?php echo number_format($att['casualties_cost']['total']); ?></td>
                    </tr>

                    <!-- BARIS 3: NERACA BERSIH PENYERANG -->
                    <tr style="border-bottom: 2px solid #cbd5e1; background: <?php echo ($isProfit ? '#ecfdf5' : ($isLoss ? '#fff1f2' : '#f8fafc')); ?>; font-weight: bold;">
                        <td style="padding: 7px 10px; color: <?php echo ($isProfit ? '#065f46' : ($isLoss ? '#881337' : '#334155')); ?>;">
                            ⚖️ Neraca Bersih Penyerang
                            <div style="font-size: 9.5px; font-weight: normal; color: #64748b;">(Rampasan &minus; Biaya Korban)</div>
                        </td>
                        <td style="padding: 7px 6px; text-align: right; color: <?php echo ($att['net_balance']['wood'] >= 0 ? '#166534' : '#dc2626'); ?>;">
                            <?php echo ($att['net_balance']['wood'] > 0 ? '+' : '') . number_format($att['net_balance']['wood']); ?>
                        </td>
                        <td style="padding: 7px 6px; text-align: right; color: <?php echo ($att['net_balance']['clay'] >= 0 ? '#166534' : '#dc2626'); ?>;">
                            <?php echo ($att['net_balance']['clay'] > 0 ? '+' : '') . number_format($att['net_balance']['clay']); ?>
                        </td>
                        <td style="padding: 7px 6px; text-align: right; color: <?php echo ($att['net_balance']['iron'] >= 0 ? '#166534' : '#dc2626'); ?>;">
                            <?php echo ($att['net_balance']['iron'] > 0 ? '+' : '') . number_format($att['net_balance']['iron']); ?>
                        </td>
                        <td style="padding: 7px 6px; text-align: right; color: <?php echo ($att['net_balance']['crop'] >= 0 ? '#166534' : '#dc2626'); ?>;">
                            <?php echo ($att['net_balance']['crop'] > 0 ? '+' : '') . number_format($att['net_balance']['crop']); ?>
                        </td>
                        <td style="padding: 7px 10px; text-align: right; font-size: 12px; color: <?php echo ($isProfit ? '#047857' : ($isLoss ? '#be123c' : '#475569')); ?>;">
                            <?php echo ($netTotal > 0 ? '+' : '') . number_format($netTotal); ?>
                        </td>
                    </tr>

                    <?php if (!$isTotalLoss): ?>
                    <!-- BARIS 4: KORBAN BERTAHAN -->
                    <tr style="border-bottom: 1px solid #e2e8f0; background: #fafaf9;">
                        <td style="padding: 6px 10px; color: #44403c;">
                            🛡️ Modal Korban Bertahan
                            <div style="font-size: 9.5px; color: #78716c;">(<?php echo number_format($def['total_dead_units']); ?> prajurit bertahan gugur)</div>
                        </td>
                        <td style="padding: 6px; text-align: right; color: #78716c;"><?php echo number_format($def['casualties_cost']['wood']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #78716c;"><?php echo number_format($def['casualties_cost']['clay']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #78716c;"><?php echo number_format($def['casualties_cost']['iron']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #78716c;"><?php echo number_format($def['casualties_cost']['crop']); ?></td>
                        <td style="padding: 6px 10px; text-align: right; font-weight: bold; color: #44403c;"><?php echo number_format($def['casualties_cost']['total']); ?></td>
                    </tr>

                    <!-- BARIS 5: TOTAL KERUGIAN BERTAHAN -->
                    <tr style="background: #fef2f2; font-weight: bold;">
                        <td style="padding: 6px 10px; color: #991b1b;">
                            🏚️ Total Kerugian Bertahan
                            <div style="font-size: 9.5px; color: #b91c1c; font-weight: normal;">(Rampasan hilang + Korban prajurit)</div>
                        </td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($def['total_loss']['wood']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($def['total_loss']['clay']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($def['total_loss']['iron']); ?></td>
                        <td style="padding: 6px; text-align: right; color: #dc2626;">-<?php echo number_format($def['total_loss']['crop']); ?></td>
                        <td style="padding: 6px 10px; text-align: right; color: #b91c1c; font-size: 11.5px;">-<?php echo number_format($def['total_loss']['total']); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="padding: 6px 10px; font-size: 10px; color: #64748b; background: #f8fafc; border: 1px solid #cbd5e1; border-top: none; border-radius: 0 0 8px 8px;">
                💡 <i>Nilai modal korban dihitung berdasarkan total bahan mentah (Kayu, Liat, Besi, Gandum) yang dikeluarkan untuk melatih prajurit yang gugur dalam pertempuran ini.</i>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
