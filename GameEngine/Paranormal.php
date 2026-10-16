<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Paranormal.php                                            ##
##  Type           : Nusantara Special Building (GID 51) Logic                 ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Shadow & Antigravity                                      ##
##  Project        : TravianZ                                                  ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class Paranormal
{
    /**
     * Spirit Definitions:
     * 1 => Pocong: Infantry defender (+40 def_i, +20 def_c)
     * 2 => Kuntilanak: Anti-cavalry defender (+20 def_i, +60 def_c)
     * 3 => Genderuwo: Heavy defender (+75 def_i, +75 def_c, takes 2 slots)
     */
    public static $spiritStats = array(
        1 => array(
            'name' => 'Pocong',
            'desc' => 'Arwah penjaga yang kokoh menahan gempuran infanteri musuh.',
            'cost' => array('wood' => 100, 'clay' => 120, 'iron' => 80, 'crop' => 150),
            'def_i' => 40,
            'def_c' => 20,
            'slots' => 1,
            'icon' => 'img/un/u/v10_pocong.png'
        ),
        2 => array(
            'name' => 'Kuntilanak',
            'desc' => 'Suara tawanya yang melengking membuat kuda kavaleri penyerang panik gentar.',
            'cost' => array('wood' => 120, 'clay' => 80, 'iron' => 100, 'crop' => 180),
            'def_i' => 20,
            'def_c' => 60,
            'slots' => 1,
            'icon' => 'img/un/u/v10_kunti.png'
        ),
        3 => array(
            'name' => 'Genderuwo',
            'desc' => 'Raksasa astral penunggu beringin yang memiliki daya tahan gaib luar biasa.',
            'cost' => array('wood' => 250, 'clay' => 200, 'iron' => 280, 'crop' => 300),
            'def_i' => 75,
            'def_c' => 75,
            'slots' => 2,
            'icon' => 'img/un/u/v10_genderuwo.png'
        )
    );

    public static $tuyulCost = array('wood' => 500, 'clay' => 500, 'iron' => 500, 'crop' => 1000);
    public static $tuyulDuration = 86400; // 24 jam

    /**
     * Ambil data makhluk gaib desa
     */
    public static function getVillageSpirits($vref)
    {
        global $database;
        $vref = (int)$vref;
        $table = TB_PREFIX . "paranormal";
        $res = $database->query("SELECT * FROM `$table` WHERE `vref` = $vref LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return $row;
        }

        // Inisialisasi jika belum ada
        $database->query("INSERT IGNORE INTO `$table` (`vref`, `pocong`, `kuntilanak`, `genderuwo`, `tuyul_until`) VALUES ($vref, 0, 0, 0, 0)");
        return array('vref' => $vref, 'pocong' => 0, 'kuntilanak' => 0, 'genderuwo' => 0, 'tuyul_until' => 0);
    }

    /**
     * Hitung total slot yang terpakai di desa
     */
    public static function getUsedSlots($spirits)
    {
        return ($spirits['pocong'] * self::$spiritStats[1]['slots'])
             + ($spirits['kuntilanak'] * self::$spiritStats[2]['slots'])
             + ($spirits['genderuwo'] * self::$spiritStats[3]['slots']);
    }

    /**
     * Kapasitas slot maksimal berdasarkan level Sanggar Paranormal
     */
    public static function getMaxCapacity($level)
    {
        global $bid51;
        $level = (int)$level;
        if ($level < 1) return 0;
        return isset($bid51[$level]['attri']) ? (int)$bid51[$level]['attri'] : ($level * 10);
    }

    /**
     * Proses POST request dari Templates/Build/51.tpl
     */
    public static function procPost($post)
    {
        global $session, $village, $database, $building;

        if (!isset($post['cmd']) || $post['cmd'] !== 'paranormal') {
            return;
        }

        $vref = (int)$village->wid;
        $level = $building->getTypeLevel(51);
        if ($level < 1) {
            return;
        }

        $spirits = self::getVillageSpirits($vref);
        $maxCap = self::getMaxCapacity($level);
        $usedSlots = self::getUsedSlots($spirits);

        $action = isset($post['action']) ? $post['action'] : '';

        if ($action === 'summon') {
            $type = (int)($post['spirit_type'] ?? 0);
            $amount = max(0, (int)($post['amount'] ?? 0));

            if (!isset(self::$spiritStats[$type]) || $amount <= 0) {
                return;
            }

            $slotPerUnit = self::$spiritStats[$type]['slots'];
            $neededSlots = $amount * $slotPerUnit;

            if ($usedSlots + $neededSlots > $maxCap) {
                // Melebihi kapasitas slot gaib
                return;
            }

            $cost = self::$spiritStats[$type]['cost'];
            $reqWood = $cost['wood'] * $amount;
            $reqClay = $cost['clay'] * $amount;
            $reqIron = $cost['iron'] * $amount;
            $reqCrop = $cost['crop'] * $amount;

            if ($village->awood >= $reqWood && $village->aclay >= $reqClay && $village->airon >= $reqIron && $village->acrop >= $reqCrop) {
                // Potong sumber daya
                $database->modifyResource($vref, $reqWood, $reqClay, $reqIron, $reqCrop, 0);

                $field = ($type === 1) ? 'pocong' : (($type === 2) ? 'kuntilanak' : 'genderuwo');
                $table = TB_PREFIX . "paranormal";
                $database->query("UPDATE `$table` SET `$field` = `$field` + $amount WHERE `vref` = $vref");

                header("Location: build.php?id=" . (int)($post['id'] ?? 0) . "&msg=summon_ok");
                exit;
            }
        } elseif ($action === 'tuyul') {
            // Ritual Tuyul
            $cost = self::$tuyulCost;
            if ($village->awood >= $cost['wood'] && $village->aclay >= $cost['clay'] && $village->airon >= $cost['iron'] && $village->acrop >= $cost['crop']) {
                $database->modifyResource($vref, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop'], 0);

                $now = time();
                $curUntil = max($now, (int)$spirits['tuyul_until']);
                $newUntil = $curUntil + self::$tuyulDuration;

                $table = TB_PREFIX . "paranormal";
                $database->query("UPDATE `$table` SET `tuyul_until` = $newUntil WHERE `vref` = $vref");

                header("Location: build.php?id=" . (int)($post['id'] ?? 0) . "&msg=tuyul_ok");
                exit;
            }
        } elseif ($action === 'dismiss') {
            $type = (int)($post['spirit_type'] ?? 0);
            $amount = max(0, (int)($post['amount'] ?? 0));
            if ($amount > 0 && in_array($type, [1, 2, 3], true)) {
                $field = ($type === 1) ? 'pocong' : (($type === 2) ? 'kuntilanak' : 'genderuwo');
                $table = TB_PREFIX . "paranormal";
                $database->query("UPDATE `$table` SET `$field` = GREATEST(0, `$field` - $amount) WHERE `vref` = $vref");

                header("Location: build.php?id=" . (int)($post['id'] ?? 0) . "&msg=dismiss_ok");
                exit;
            }
        }
    }

    /**
     * Hitung poin pertahanan desa yang diberikan oleh makhluk gaib
     */
    public static function getVillageDefense($vref)
    {
        $vref = (int)$vref;
        if ($vref <= 0) {
            return ['dp' => 0, 'cdp' => 0, 'involve' => 0];
        }

        $spirits = self::getVillageSpirits($vref);
        $totalSpirits = (int)$spirits['pocong'] + (int)$spirits['kuntilanak'] + (int)$spirits['genderuwo'];
        if ($totalSpirits <= 0) {
            return ['dp' => 0, 'cdp' => 0, 'involve' => 0];
        }

        $dp = ((int)$spirits['pocong'] * self::$spiritStats[1]['def_i'])
            + ((int)$spirits['kuntilanak'] * self::$spiritStats[2]['def_i'])
            + ((int)$spirits['genderuwo'] * self::$spiritStats[3]['def_i']);

        $cdp = ((int)$spirits['pocong'] * self::$spiritStats[1]['def_c'])
             + ((int)$spirits['kuntilanak'] * self::$spiritStats[2]['def_c'])
             + ((int)$spirits['genderuwo'] * self::$spiritStats[3]['def_c']);

        return [
            'dp' => $dp,
            'cdp' => $cdp,
            'involve' => $totalSpirits
        ];
    }

    /**
     * Terapkan korban pertempuran pada makhluk gaib yang bertugas membela desa
     */
    public static function applyCasualties($vref, $casualtyRatio)
    {
        global $database;
        $vref = (int)$vref;
        $res = [
            'troops' => ['pocong' => 0, 'kuntilanak' => 0, 'genderuwo' => 0],
            'dead' => ['pocong' => 0, 'kuntilanak' => 0, 'genderuwo' => 0],
            'total_troops' => 0,
            'total_dead' => 0
        ];

        if ($vref <= 0) return $res;
        $casualtyRatio = min(1.0, max(0.0, (float)$casualtyRatio));

        $spirits = self::getVillageSpirits($vref);
        $totalSpirits = (int)$spirits['pocong'] + (int)$spirits['kuntilanak'] + (int)$spirits['genderuwo'];
        if ($totalSpirits <= 0) return $res;

        $res['troops'] = [
            'pocong' => (int)$spirits['pocong'],
            'kuntilanak' => (int)$spirits['kuntilanak'],
            'genderuwo' => (int)$spirits['genderuwo']
        ];
        $res['total_troops'] = $totalSpirits;

        if ($casualtyRatio > 0) {
            $deadP = (int)round((int)$spirits['pocong'] * $casualtyRatio);
            $deadK = (int)round((int)$spirits['kuntilanak'] * $casualtyRatio);
            $deadG = (int)round((int)$spirits['genderuwo'] * $casualtyRatio);

            $res['dead'] = ['pocong' => $deadP, 'kuntilanak' => $deadK, 'genderuwo' => $deadG];
            $res['total_dead'] = $deadP + $deadK + $deadG;

            $newP = max(0, (int)$spirits['pocong'] - $deadP);
            $newK = max(0, (int)$spirits['kuntilanak'] - $deadK);
            $newG = max(0, (int)$spirits['genderuwo'] - $deadG);

            $table = TB_PREFIX . "paranormal";
            $database->query("UPDATE `$table` SET `pocong` = $newP, `kuntilanak` = $newK, `genderuwo` = $newG WHERE `vref` = $vref");
        }

        return $res;
    }

    /**
     * Ambil bonus jarahan raid dari berkah Tuyul (+15% jarahan)
     */
    public static function getRaidMultiplier($vref)
    {
        $vref = (int)$vref;
        if ($vref <= 0) return 1.0;
        $spirits = self::getVillageSpirits($vref);
        if (!empty($spirits['tuyul_until']) && (int)$spirits['tuyul_until'] > time()) {
            return 1.15;
        }
        return 1.0;
    }
}
