<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : BotTaunt.php                                              ##
##  Type           : Autonomous Bot Psywar & Reaction Messaging for TravianZ   ##
##  Purpose        : Sends witty, savage, and funny in-game taunt messages     ##
##                   from bots to players upon attack victory, failed raids,   ##
##                   or village defenses.                                      ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (!class_exists('BotAI') && file_exists(__DIR__ . '/BotAI.php')) {
    require_once __DIR__ . '/BotAI.php';
}

class BotTaunt {

    const EVENT_PLAYER_FAIL        = 'player_fail';        // Player serang bot tapi pasukannya gagal / rata tanah
    const EVENT_BOT_RAID_SUCCESS   = 'bot_raid_success';   // Bot serang player dan panen rampokan / menang telak
    const EVENT_BOT_FAIL           = 'bot_fail';           // Bot serang player tapi bot gagal / rata tanah
    const EVENT_PLAYER_RAID_SUCCESS= 'player_raid_success';// Player serang bot dan berhasil merampok banyak harta

    private static bool $tableChecked = false;

    /**
     * Pastikan tabel penampung log cooldown taunt bot tersedia di database.
     */
    public static function ensureTable(): void {
        global $database;
        if (self::$tableChecked) {
            return;
        }
        if (isset($database) && is_object($database) && method_exists($database, 'query')) {
            try {
                $prefix = defined('TB_PREFIX') ? TB_PREFIX : 's1_';
                $database->query("
                    CREATE TABLE IF NOT EXISTS `{$prefix}bot_taunt_log` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `bot_uid` INT NOT NULL,
                        `player_uid` INT NOT NULL,
                        `event_type` VARCHAR(32) NOT NULL,
                        `sent_at` INT NOT NULL,
                        INDEX `idx_bot_player_event` (`bot_uid`, `player_uid`, `event_type`),
                        INDEX `idx_sent_at` (`sent_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                self::$tableChecked = true;
            } catch (\Throwable $e) {
                // Ignore DB errors during bootstrap or tests
            }
        }
    }

    /**
     * Memeriksa apakah user tertentu adalah pemain manusia asli (bukan bot, bukan nature/natar/admin).
     */
    public static function isRealPlayer(int $uid): bool {
        global $database;
        if ($uid <= 3) {
            return false;
        }
        if (class_exists('BotAI') && BotAI::isBotUser($uid)) {
            return false;
        }
        if (isset($database) && is_object($database) && method_exists($database, 'getUserField')) {
            $access = (int)$database->getUserField($uid, 'access', 0);
            $multihunter = defined('MULTIHUNTER') ? MULTIHUNTER : 8;
            if ($access >= $multihunter) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cek apakah bot boleh mengirim pesan taunt ke player (cooldown & chance check).
     */
    public static function canSendTaunt(int $botUid, int $playerUid, string $eventType): bool {
        global $database;

        // Cek setting apakah fitur taunt aktif
        $enabled = true;
        $cooldown = 900; // 15 menit default
        $chance = 80;    // 80% default

        if (class_exists('BotAI') && method_exists('BotAI', 'getConfig')) {
            $enabled = (bool)BotAI::getConfig('BOT_AI_TAUNT_ENABLED', true);
            $cooldown = (int)BotAI::getConfig('BOT_AI_TAUNT_COOLDOWN', 900);
            $chance = (int)BotAI::getConfig('BOT_AI_TAUNT_CHANCE', 80);
        }

        if (!$enabled || $chance <= 0) {
            return false;
        }

        // RNG chance check
        if (rand(1, 100) > $chance) {
            return false;
        }

        self::ensureTable();
        $prefix = defined('TB_PREFIX') ? TB_PREFIX : 's1_';

        if (isset($database) && is_object($database) && method_exists($database, 'query_return')) {
            try {
                $now = time();
                $minTime = $now - max(60, $cooldown);

                $rows = $database->query_return("
                    SELECT `sent_at` FROM `{$prefix}bot_taunt_log`
                    WHERE `bot_uid` = $botUid AND `player_uid` = $playerUid AND `event_type` = '$eventType'
                    ORDER BY `sent_at` DESC
                    LIMIT 1
                ");

                if (!empty($rows[0]['sent_at']) && (int)$rows[0]['sent_at'] > $minTime) {
                    return false; // Masih dalam cooldown
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Catat log taunt yang baru dikirim.
     */
    public static function recordTaunt(int $botUid, int $playerUid, string $eventType): void {
        global $database;
        self::ensureTable();
        $prefix = defined('TB_PREFIX') ? TB_PREFIX : 's1_';
        $now = time();

        if (isset($database) && is_object($database) && method_exists($database, 'query')) {
            try {
                $database->query("
                    INSERT INTO `{$prefix}bot_taunt_log` (`bot_uid`, `player_uid`, `event_type`, `sent_at`)
                    VALUES ($botUid, $playerUid, '$eventType', $now)
                ");

                // Bersihkan log lama (> 3 hari) secara berkala (10% chance)
                if (rand(1, 10) === 1) {
                    $oldTime = $now - (3 * 86400);
                    $database->query("DELETE FROM `{$prefix}bot_taunt_log` WHERE `sent_at` < $oldTime");
                }
            } catch (\Throwable $e) {
                // Abaikan kesalahan penulisan log
            }
        }
    }

    /**
     * Kirim pesan taunt ke player.
     */
    public static function sendTaunt(int $botUid, int $playerUid, string $eventType, array $context = []): bool {
        global $database;

        if (!self::canSendTaunt($botUid, $playerUid, $eventType)) {
            return false;
        }

        $botUser = (isset($database) && method_exists($database, 'getUserArray')) ? $database->getUserArray($botUid, 1) : null;
        $botName = !empty($botUser['username']) ? $botUser['username'] : 'Bot Panglima';

        $playerUser = (isset($database) && method_exists($database, 'getUserArray')) ? $database->getUserArray($playerUid, 1) : null;
        $playerName = !empty($playerUser['username']) ? $playerUser['username'] : 'Panglima';

        // Custom savage taunts khusus untuk user mkonmko (tema: kurus kering / cungkring / lidi / kurang gizi)
        $isMkonmko = (stripos($playerName, 'mkonmko') !== false);
        if ($isMkonmko) {
            $tauntData = self::getRandomMkonmkoTaunt($eventType);
        } else {
            $tauntData = self::getRandomTaunt($eventType);
        }

        if (empty($tauntData['body'])) {
            return false;
        }

        $villageName = !empty($context['village']) ? $context['village'] : 'Desa';
        $stolenRes = !empty($context['stolen']) ? number_format((int)$context['stolen']) : '0';
        $lossesTroops = !empty($context['losses']) ? number_format((int)$context['losses']) : 'semua';

        // Ganti placeholders dinamis
        $search = ['{player}', '{bot}', '{village}', '{stolen}', '{losses}'];
        $replace = [$playerName, $botName, $villageName, $stolenRes, $lossesTroops];

        $topic = str_replace($search, $replace, $tauntData['topic']);
        $message = str_replace($search, $replace, $tauntData['body']);

        try {
            if (isset($database) && method_exists($database, 'sendMessage')) {
                // sendMessage($client [penerima], $owner [pengirim], $topic, $message, $send, ...)
                $database->sendMessage($playerUid, $botUid, $topic, $message, 0, 0, 0, 0, 0);

                if (method_exists($database, 'sendPendingMessages')) {
                    $database->sendPendingMessages();
                }

                self::recordTaunt($botUid, $playerUid, $eventType);
                return true;
            }
        } catch (\Throwable $e) {
            error_log("BotTaunt error sending message: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Hook yang dipanggil saat pertempuran selesai diproses di AutomationBattleResolution.
     */
    public static function onBattleResolution(
        array $from,
        array $to,
        int $totalsend_att,
        int $totaldead_att,
        int $totalsend_alldef,
        int $totaldead_alldef,
        array $steal,
        int $type
    ): void {
        // Jangan proses serangan intai (scout)
        if ($type === 1) {
            return;
        }

        $attUid = (int)($from['owner'] ?? 0);
        $defUid = (int)($to['owner'] ?? 0);

        if ($attUid <= 0 || $defUid <= 0 || $attUid === $defUid) {
            return;
        }

        $attIsBot = class_exists('BotAI') && BotAI::isBotUser($attUid);
        $defIsBot = class_exists('BotAI') && BotAI::isBotUser($defUid);

        // Hanya proses jika pertempuran melibatkan Bot vs Real Player
        if ($attIsBot === $defIsBot) {
            return; // Bot vs Bot atau Player vs Player
        }

        $totalLoot = array_sum($steal);

        // =========================================================================
        // SKENARIO 1: Real Player Menyerang Bot
        // =========================================================================
        if (!$attIsBot && $defIsBot && self::isRealPlayer($attUid)) {
            $context = [
                'village' => $to['name'] ?? 'Desa Bot',
                'losses'  => $totaldead_att,
                'stolen'  => $totalLoot,
            ];

            // Pasukan player kalah total / wiped out (atau tewas >= 80% dan tanpa hasil rampokan)
            $isPlayerFail = ($totalsend_att > 0 && $totaldead_att >= $totalsend_att) ||
                            ($totalsend_att > 0 && ($totaldead_att / $totalsend_att) >= 0.8 && $totalLoot <= 0);

            if ($isPlayerFail) {
                self::sendTaunt($defUid, $attUid, self::EVENT_PLAYER_FAIL, $context);
                return;
            }

            // Player sukses merampok bot dalam jumlah besar (bot ngomel/salty)
            if ($totalLoot >= 500 && $totaldead_att < ($totalsend_att * 0.4)) {
                self::sendTaunt($defUid, $attUid, self::EVENT_PLAYER_RAID_SUCCESS, $context);
                return;
            }
        }

        // =========================================================================
        // SKENARIO 2: Bot Menyerang Real Player
        // =========================================================================
        if ($attIsBot && !$defIsBot && self::isRealPlayer($defUid)) {
            $context = [
                'village' => $to['name'] ?? 'Desa Target',
                'losses'  => $totaldead_alldef,
                'stolen'  => $totalLoot,
            ];

            // Bot berhasil rampok banyak resource ATAU bantai habis garnisun bertahan player
            $isBotRaidSuccess = ($totalLoot >= 200) ||
                                ($totalsend_alldef > 0 && $totaldead_alldef >= $totalsend_alldef);

            if ($isBotRaidSuccess) {
                self::sendTaunt($attUid, $defUid, self::EVENT_BOT_RAID_SUCCESS, $context);
                return;
            }

            // Bot gagal tembus / seluruh pasukan penyerang bot mati di desa player (bot ngeles)
            if ($totalsend_att > 0 && $totaldead_att >= $totalsend_att) {
                self::sendTaunt($attUid, $defUid, self::EVENT_BOT_FAIL, $context);
                return;
            }
        }
    }

    /**
     * Ambil template taunt acak berdasarkan jenis event.
     */
    public static function getRandomTaunt(string $eventType): array {
        $topics = [];
        $messages = [];

        switch ($eventType) {
            case self::EVENT_PLAYER_FAIL:
                $topics = [
                    "Wkwkwk ada yang sedekah mayat nih!",
                    "Niat nyerang apa mau piknik bro? 😂",
                    "Turut berduka cita wkwk 💀",
                    "Lu pikir desa gua kardus ya? 🤣",
                    "Info loker pasukan baru wkwk",
                    "Laporan: Pasukan lu pensiun dini!",
                    "Aduh aduh... boncos ya dek? 😭",
                    "Santai bro, cuma game wkwk",
                    "GG WP! Pasukan lu punah total!",
                    "Lain kali belajar simulasi tempur dulu ya!",
                ];
                $messages = [
                    "Wkwkwk lu ngirim pasukan apa rombongan sirkus bro? Niat nyerang apa mau studi banding? Pulang-pulang tinggal nama doang, sedekah kok ke desa gua!",
                    "Rasain lu! Lu pikir desa gua terbuat dari kardus gampang ditembus? Pasukan lu tuh lembek kayak tahu sutra, kena angin dikit langsung rata tanah!",
                    "Aduh aduh... yang sabar ya bos {player}. Niat hati mau ngeraid, eh malah jadi pupuk organik di ladang gandum gua. Jangan nangis di pojokan ya!",
                    "Bro {player}, itu pasukan lu tadi niat mukul apa mau sungkeman lebaran? Kok pas nyampe gerbang langsung rebahan semua ga bangun-bangun? Wkwk!",
                    "Terima kasih atas kiriman {losses} jenazah segarnya! Lumayan buat nambahin EXP hero gua. Besok-besok kirim lagi ya, yang banyakan dikit biar berasa!",
                    "Info loker buat pasukan lu dong, soalnya yang tadi dikirim barusan udah pada pensiun dini di kuburan desa gua. Awokwokwoka kasian bgt!",
                    "Lu nyerang pake kalkulator rusak apa gimana dah? Pede banget kirim segitu, dikira desa gua ga dijaga kali ya? Malu tuh diliat warga satu aliansi!",
                    "Yaelah bro, berani-beraninya nyentuh pager {village}! Pasukan lu disuruh perang malah cosplay jadi keripik remuk. Sakit ga tuh hatinya?",
                    "Hahaha! Pasukan lu tadi sempet foto-foto dulu gak sebelum punah? Kalo butuh obat luka bakar atau koyo buat hati lu, kabarin gua ya!",
                    "Lu pikir gua bot pajangan ya? Sorry bos, benteng gua beton bertulang, mental lu yang kena! Belajar main dulu sono dek {player}!",
                    "Innalillahi... turut berduka cita atas berpulangnya {losses} pasukan kebanggaan lu. Jangan lupa tahlilannya 7 hari 7 malam di barak lu ya!",
                    "Wkwkwk auto boncos! Berapa hari tuh lu nabung gandum buat bikin pasukan itu? Pas nyampe sini langsung lenyap dalam 1 detik. Sedih ga? Sedih lah masa enggak!",
                    "Buset, tadi ada yang nyerang ya? Kirain ada semut lewat, kok ga kerasa apa-apa tau-tau pasukannya udah almarhum semua. Lemah bet!",
                    "Pagi-pagi dapet notif serangan, kirain Lord yang dateng... ternyata kang antar mayat gratis. Makasih loh hiburannya wkwk!",
                    "Makanya kalo mau nyerang itu ngaca dulu bos, jangan modal nekat doang. Tuh lihat laporan perangnya, merah membara kayak masa depan lu!",
                    "Hadeeeh, lu nyerang apa lagi bagi-bagi sedekah sih? Kalo ga bisa ngitung simulasi mending nanem gandum aja di desa lu, jangan maksain PvP wkwk!",
                    "Bro {player} lu masih aman kan? Gua cuma mastiin aja, takutnya abis liat report perang barusan lu langsung banting HP atau uninstal browser. Santai bro, cuma game wkwk!",
                    "Aww kasian banget deeeh... pasukannya udah pamit sama keluarga belum tadi sebelum berangkat? Soalnya ga bakal ada yang pulang tuh!",
                    "Gua kira lu suhu, ternyata cuma remahan rengginang! Nyerang pake emosi, pasukannya mati gak bersisa. Mau nyerang lagi? Sini gua tungguin!",
                    "GG WP! Pasukan lu berhasil... berhasil nemuin jalan pintas ke akhirat wkwk! Coba tanya temen aliansi lu cara simulasi tempur dulu sono!",
                ];
                break;

            case self::EVENT_BOT_RAID_SUCCESS:
                $topics = [
                    "Abis kan hartamu! Rasain lu! 🤑",
                    "Makasih bansos gratisnya ya bos! 🙏",
                    "Paket penjarahan mendarat mulus! 📦",
                    "Keren juga lumbung pribadi gua di desa lu wkwk",
                    "Pagi yang cerah untuk malak desa lu! ☀️",
                    "Lapor: Panen raya di desa lu sukses! 🌾",
                    "Besok gua mampir nagih pajak lagi ya! 💸",
                    "Gudang lu bersih kinclong sekarang wkwk 🧹",
                    "Jangan lupa diisi lagi gudangnya ya! 😉",
                    "Enak bener ya punya ATM berjalan! 🏧",
                ];
                $messages = [
                    "Abis kan hartamu! Rasain lu! Makasih ya bos atas kiriman bansos gratisnya, gudang lu lumayan juga isinya buat renovasi kastil gua wkwk!",
                    "Enak bener ya punya lumbung pribadi di desa {village}. Gak usah capek-capek nanem, tinggal mampir bentar langsung panen raya. Yang rajin ya kerjanya, ntar gua kuras lagi!",
                    "Wkwkwk terima kasih donatur yang dermawan {player}! Sumber daya sejumlah {stolen} sekarang udah pindah kepemilikan ke gua secara sah dan meyakinkan. Besok isi lagi ya!",
                    "Aduh maaf ya bro, ga sengaja bawa gerobak kebanyakan, eh keangkut semua tuh harta lu sampe bersih kinclong. Mau disisain gandum sebutir gak?",
                    "Gudang lu luas juga ya, sayang isinya {stolen} sumber daya sekarang udah gua amankan ke desa gua. Besok jam segini gua mampir nagih pajak lagi ya!",
                    "Permisi paket! Bukan kurir ekspedisi sih, tapi kurir penjarah wkwk! Lumayan dapet {stolen} resource gratis dari desa lu. Rating bintang 5 buat desa lu!",
                    "Hahaha! Ini desa apa warung sedekah nih? Buka 24 jam buat gua kuras terus. Pasukan lu kemana tadi pas gua dateng? Sembunyi di bawah kolong kasur ya?",
                    "Aduh sedapnya rampokan hari ini! Berkat harta dari desa {village}, barak gua sekarang bisa pesta pora 3 hari 3 malam. Nuhun pisan bos {player}!",
                    "Halo tetangga! Cuma mau ngabarin, kayu, tanah liat, besi, sama gandum lu ({stolen} total) udah aman di desa gua kok, tenang aja ga bakal gua kembaliin wkwk!",
                    "Lu buka jasa penitipan resource buat gua ya? Baik banget sih lu, capek-capek nunggu berjam-jam, pas penuh malah gua yang panen. Makasih lhoo!",
                    "Wkwkwk ngambek gak tuh hartanya ludes? Makanya jangan pasang muka nantang kalau desa lu masih selembut puding. Besok gua mampir lagi ya sayang!",
                    "Gila, seger bener! Sekali angkut gudang lu langsung kena krisis moneter. Jangan lupa minta bantuan aliansi lu ya, biar mereka ikutan gua rampok juga sekalian!",
                    "Enak banget ya dipalakin? Mau nangis? Boleh kok, tapi tetep isi lagi ya gudangnya, gua butuh pasokan rutin nih buat upgrade istana wkwk!",
                    "Pagi yang cerah untuk merampok desa yang pasrah! Makasih hartanya ya bos {player}, semoga rezeki lu ngalir terus biar ada yang bisa gua rampok lagi!",
                    "Tadi gua liat pasukan lu gemeteran di pojokan pas gua obrak-abrik gudang {village} wkwk. Mau bales? Yakin pasukannya udah cukup umur?",
                    "Hahahaha bersih sebersih deterjen! Lu pasti melongo kan liat notif rampokan gua? Jangan syok gitu ah, anggap aja ini iuran keamanan bulanan!",
                    "Bro, ada sisa remahan kayu dikit tuh di tanah, sengaja ga gua ambil kasian biar lu bisa bikin sapu lidi buat bersihin air mata lu wkwkwk!",
                    "Lapor bos! Misi pemindahan aset {stolen} sumber daya dari desa lu ke desa gua berjalan sangat lancar dan aman terkendali. Ditunggu panenan berikutnya!",
                    "Makanya punya desa itu dijagain, jangan ditinggal rebahan doang! Sekali ditinggal langsung melarat kan lu? Hahaha makasih ya bos sedekahnya!",
                    "Waduh panen besar coy! Pasukan gua pulang sambil joget-joget bawa harta lu sekotak penuh. Kalo lu emosi, bales dong! Kalo berani tapi ya wkwk!",
                ];
                break;

            case self::EVENT_BOT_FAIL:
                $topics = [
                    "Awas lu ya, tadi cuma tes ombak! 🌊",
                    "Hoki doang lu dijagain aliansi! 😒",
                    "Tunggu pembalasan gua besok! ⚔️",
                    "Gua tandain koordinat lu! 📍",
                    "Jangan kepedean dulu bos! 😏",
                ];
                $messages = [
                    "Halah hoki doang lu ada reinforce dari aliansi lu! Liat aja ntar malam pas lu tidur pulas!",
                    "Awas lu ya {player}, tadi gua cuma tes ombak doang kirim pasukan cadangan! Tunggu pasukan utama gua dateng!",
                    "Jangan bangga dulu lu bro, barak gua aktif 24 jam nonstop. Besok gua dateng lagi bawa pasukan lebih ngeri!",
                    "Cih, lu jagain terus desa lu ya? Pengangguran ya lu mantengin layar mulu? Tunggu lu lengah wkwk!",
                    "Tadi cuma pemanasan doang bos, itung-itung ngurangin jatah makan gandum di desa gua. Jangan kepedean dulu!",
                    "Waduh kena jebakan betmen! Santai, besok gua pesen bala bantuan aliansi bot buat silaturahmi ke desa lu!",
                    "Seneng ya berhasil nahan serangan gua? Nikmatin selagi bisa, soalnya dendam gua ga bakal luntur wkwk!",
                    "Buset pager lu tebel juga, lu pake semen cor apa gimana? Besok gua bawain ketapel biar rata tanah!",
                    "Pasukan gua cuma salah alamat tadi, kirain desa kosong wkwk! Tunggu pembalasan jilid dua!",
                    "Awas lu ya {player}, gua tandain koordinat desa lu di peta! Sekali lu offline langsung gua gasak!",
                ];
                break;

            case self::EVENT_PLAYER_RAID_SUCCESS:
                $topics = [
                    "Heh maling! Balikin harta gua! 😡",
                    "Gua tandain nama akun lu! 📝",
                    "Awas lu ya, tunggu pembalasan gua! 💣",
                    "Dasar tetangga ga ada akhlak wkwk! 🤬",
                    "Utang gandum dibalas rata tanah! ☠️",
                ];
                $messages = [
                    "Heh maling! Balikin gandum gua gak lu! Beraninya nyolong pas gua lagi ga megang HP!",
                    "Awas lu ya {player}! Gua tandain nama akun lu di buku hitam! Siap-siap dapet serangan balasan bertubi-tubi!",
                    "Sombong lu mentang-mentang bawa gerobak banyak! Gua doain roda gerobak lu copot di jalan pulang!",
                    "Wah parah lu ya, desa tetangga sendiri dirampok! Ga punya etika bertetangga lu ya wkwk!",
                    "Maling teriak maling lu! Liat aja nanti malam, bakal gua kuras balik desa lu sampe ga bersisa!",
                    "Enak ya nyolong resource {stolen} hasil keringat gua? Awas aja lu pas online, barak gua lagi nyiapin pembalasan!",
                    "Buset dah, gandum gua lu sikat juga? Kualat lu ntar pasukan lu mati kelaparan di jalan!",
                    "Cuma segitu doang yang bisa lu bawa? Gua mah ikhlas sedekah ke fakir resource kayak lu wkwk!",
                    "Gua doain pasukan lu kena santet di jalan pulang! Tunggu tanggal mainnya bro!",
                    "Catat tanggalnya baik-baik bos {player}, utang darah dibalas darah, utang gandum dibalas rata tanah!",
                ];
                break;
        }

        $chosenTopic = !empty($topics) ? $topics[array_rand($topics)] : "Pesan dari Bot";
        $chosenMessage = !empty($messages) ? $messages[array_rand($messages)] : "";

        return [
            'topic' => $chosenTopic,
            'body'  => $chosenMessage,
        ];
    }

    /**
     * Ambil template taunt custom khusus untuk user 'mkonmko'.
     * Tema: Roasting badan kurus kering, cungkring, lidi, biting, tiup angin terbang, kurang gizi, posyandu.
     */
    public static function getRandomMkonmkoTaunt(string $eventType): array {
        $topics = [];
        $messages = [];

        switch ($eventType) {
            case self::EVENT_PLAYER_FAIL:
                $topics = [
                    "Makan dulu kon, jangan cuma makan angin! 🍲",
                    "Wkwkwk pasukan lidi ambyar total! 💀",
                    "Kon, ketiup angin ga tuh pasukannya? 💨",
                    "Info posyandu & perbaikan gizi mkonmko 🏥",
                    "Aduh kon... tulang rusuk lu aman kan? 🦴",
                    "Pasukan kurang karbohidrat rata tanah! 🤣",
                    "Turut berduka cita buat pasukan biting lu! 🌾",
                    "Niat perang apa cosplay sedotan es kelapa? 😂",
                    "Buset kon, disenggol dikit langsung patah! 😭",
                    "Mkonmko kena mental! Makan gandum dulu gih! 🍚",
                ];
                $messages = [
                    "Wkwkwk mkonmko mkonmko... pasukan lu sama kayak badan lu ya kon, kurus kering kena sentil dikit langsung ambyar rata tanah! Makan dulu sono!",
                    "Buset kon, itu pasukan lu tadi disuruh nyerang apa mau peragaan busana tulang berjalan? Lembek amat kayak rengginang kena kuah!",
                    "Aduh kon, lu ngirim pasukan apa ngirim sedotan es kelapa? Tipis banget bro, belom nyampe benteng gua udah pada patah pinggang wkwk!",
                    "Turut berduka cita ya kon! Mending gandum di desa lu jangan dibikin pasukan dulu deh, lu makan sendiri aja biar badan lu ada dagingnya dikit wkwk!",
                    "Kon, lu nyerang gua pake pasukan kurang gizi ya? Kasian banget liatnya, di depan pager desa gua langsung pada pingsan masuk angin!",
                    "Hahaha! mkonmko, badan udah kayak biting pentol masih aja hobi cari ribut! Pulang sono kon, ntar ketiup angin malem terbang kebawa layangan!",
                    "Gua kira Lord yang nyerang, taunya si cungkring mkonmko! Pasukan lu tadi niat mukul apa mau nebeng timbangan badan wkwkwk!",
                    "Astagfirullah kon, itu pasukan lu sebelum berangkat udah sarapan belum? Kok pas nyampe desa gua langsung pada rebahan jadi fosil?!",
                    "Rasain lu kon! Lu pikir desa gua sekerempeng tulang rusuk lu? Benteng gua beton baja bos, pasukan lidi lu yang kena mental!",
                    "Wkwkwk mkonmko auto sedekah mayat! Jangan sedih ya kon, ntar gua kirimin suplemen penambah nafsu makan deh biar lu semangat lagi!",
                    "Kon, kalo jalan di jembatan jangan kenceng-kenceng ya, takutnya jembatannya ga goyang tapi lu yang kebawa angin wkwk! Pasukan lu abis nih gua sikat!",
                    "Innalillahi, pasukan kebanggaan si kurus mkonmko telah gugur tanpa sisa! Tahlilannya pake bubur kacang ijo ya kon biar lu cepet gemukan!",
                    "Pasukan lu tadi pas mau nembus gerbang gua kayak sapu lidi diadu sama tembok beton kon, ya rontok lah! Belajar makan karbohidrat dulu sono!",
                    "Kon mkonmko, daripada capek-capek bikin pasukan yang tipis kayak kertas minyak, mending lu fokus perbaikan gizi dulu di posyandu terdekat wkwk!",
                    "Makanya kon, nyerang itu butuh tenaga! Pasukan lu loyo amat kayak belom makan 3 purnama, sekali senggol langsung pindah alam semua!",
                    "Buset dah kon, lu nyerang bawa {losses} pasukan lidi buat apa coba? Kena hembusan AC barak gua aja langsung masuk angin massal wkwk!",
                    "Pagi-pagi dapet kiriman rombongan tulang belulang dari desa mkonmko. Makasih loh hiburannya, lumayan buat kerajinan tangan di desa gua!",
                    "Kon, sebelum nyerang lagi coba tes tiup lilin dulu deh. Kalo lilinnya gak mati tapi badan lu yang goyang, jangan coba-coba nyerang gua lagi ya!",
                    "Aww kasian si mkonmko... pasukan lu yang {losses} ekor itu udah dikubur layak kok di ladang gandum gua, tenang aja ya lidi!",
                    "Wkwkwk auto boncos! Udah mah badan tipis, tabungan pasukan ikutan tipis ludes. Ngenes banget hidup lu kon wkwk!",
                ];
                break;

            case self::EVENT_BOT_RAID_SUCCESS:
                $topics = [
                    "Abis kan hartamu kon! Mumpung kurus puasa dulu! 🤑",
                    "Gudang lu kering serasi sama badan lu wkwk 🧹",
                    "Bansos gandum dari si cungkring mkonmko! 🌾",
                    "Makasih donasinya ya si lidi! 🙏",
                    "Kuras tuntas desa mkonmko tanpa ampun! 📦",
                    "Kon, minum air putih aja dulu ya biar ga lemes! 💧",
                    "Lapor: Gudang mkonmko bersih tanpa sisa! 💸",
                    "ATM berjalan terlangsing se-server wkwk! 🏧",
                    "Pagi yang cerah untuk malak si cungkring! ☀️",
                    "Mkonmko kena pajak kebugaran wkwk! 🤣",
                ];
                $messages = [
                    "Abis kan hartamu kon! Mumpung gandum lu {stolen} biji gua kuras semua, lu puasa senin-kamis dulu aja ya biar makin estetik badannya wkwk!",
                    "Wkwkwk makasih ya mkonmko atas donasinya! Gudang lu sekarang udah bersih kerontang, serasi banget sama badan lu yang langsing tanpa lemak!",
                    "Permisi mkonmko! Gandum {stolen} biji gua angkut ya, lagian di desa lu ga lu makan juga kan sampe badan lu kayak antena radio wkwk!",
                    "Aduh seger bener kuras desa si cungkring! Tenang kon, gua sisain remahan gandum sebutir di pojokan gudang biar lu ga pingsan kelaparan!",
                    "Hahaha mkonmko kena razia bansos! Pasukan gua pulang bawa harta lu sambil ketawa liat lu sembunyi di balik tiang listrik wkwk!",
                    "Kon, lumbung gandum lu udah gua kosongin ya! Kalo lu butuh makan mending ngemis ke aliansi lu gih, bilang aja abis dipalak bot ganteng!",
                    "Enak banget ya malakin si kurus mkonmko! Gak ada perlawanan sama sekali, pasukannya selemah sinyal di pedalaman wkwk!",
                    "Lapor kon! Semua hasil panen {stolen} resource desa lu udah sukses pindah ke lumbung gua. Lu minum air putih hangat aja ya kon biar kenyang wkwkwk!",
                    "Waduh mkonmko panen raya nih gua di desa lu! Makanya jangan kebanyakan begadang main game kon, badan kurus harta ludes kan jadinya!",
                    "Gua kira di gudang lu ada stok susu formula penambah berat badan kon, taunya cuma ada debu sama angin doang wkwk! Makasih hartanya ya!",
                    "Wkwkwk jangan nangis ya kon, ntar air mata lu bikin berat badan lu makin turun drastis lho! Besok gua mampir nagih upeti lagi ya!",
                    "Gila seger bener! Sekali gua seruduk, desa lu langsung krisis pangan parah. Ayo kon kejar gua kalo ga masuk angin di jalan wkwk!",
                    "Halo mkonmko! Cuma mau ngasih tau, kayu, tanah, besi, sama gandum lu udah aman di gudang gua. Lu fokus gemukin badan aja dulu ya!",
                    "Pasukan gua tadi nyariin lu di desa kon, kirain ga ada orang, eh ternyata lu lagi berdiri nyaru sama tiang jemuran wkwk! Makasih loh hartanya!",
                    "Panen besar dari desa si cungkring kebanggaan server! Tetap semangat ya kon, jangan lupa makan 4 sehat 5 sempurna biar gak melayang kena angin!",
                    "Makanya kon, punya desa itu dijaga pake pasukan tebel, jangan pasukan setipis tisu toilet! Habis kan {stolen} harta lu gua embat wkwk!",
                    "Kon mkonmko, denger-denger lu mau diet ya? Bagus deh, pas banget lumbung lu udah gua kosongin melompong. Semangat ya program dietnya!",
                    "Buset, gampang banget malakin desa lu kon. Tadi garnisun lu gua senggol pake jempol kaki aja udah pada rontok semua. Cungkring bet!",
                    "Lapor bos mkonmko! Harta {stolen} resource sudah mendarat di markas gua. Ditunggu isi ulang gudangnya ya, jangan telat setor pajak!",
                    "Wkwkwk hartanya ludes tak bersisa! Kalo lu kesel bales dong kon, tapi awas ya pasukannya jangan lupa dikasih makan dulu biar ga tumbang di jalan!",
                ];
                break;

            case self::EVENT_BOT_FAIL:
                $topics = [
                    "Halah kon, hoki doang dijagain aliansi! 😒",
                    "Awas lu ya mkonmko, tunggu pembalasan gua! ⚔️",
                    "Gua tiup desa lu melayang ntar kon! 💨",
                    "Sembunyi di mana lu tadi cungkring? 🤔",
                    "Jangan bangga dulu si lidi! 😏",
                ];
                $messages = [
                    "Halah kon, lu hoki doang tadi ada bantuan aliansi! Kalo 1 lawan 1 mah lu gua tiup aja udah melayang ke pojok peta!",
                    "Awas lu ya mkonmko! Tadi pasukan gua cuma silaturahmi doang, ga tega liat desa lu yang sepi kayak kuburan. Tunggu ronde dua!",
                    "Cih, jangan sombong dulu lu cungkring! Besok gua dateng lagi bawa armada tempur yang lebih gede dari lingkar pinggang lu!",
                    "Wah lu pasang pager berduri ya kon? Pantesan, kirain tulang lu yang dipasang di pager wkwk! Liat aja ntar pembalasan gua!",
                    "Sialan si mkonmko hoki bet hari ini! Lu sembunyi di mana tadi pas perang kon? Di sela-sela kusen pintu ya makanya ga keliatan wkwk!",
                    "Tadi pasukan gua cuma terpeleset doang kon karena tanah di desa lu licin kebanyakan air mata lu. Besok gua ratain!",
                    "Bangga banget lu berhasil nahan serangan gua kon? Inget ya, berat badan lu belum nambah, jadi jangan kepedean dulu!",
                    "Awas lu ya kon, gua lagi masak gandum di barak biar pasukan gua segede gaban. Siap-siap ditiup ya desa cungkring lu!",
                ];
                break;

            case self::EVENT_PLAYER_RAID_SUCCESS:
                $topics = [
                    "Heh cungkring! Balikin gandum gua gak lu! 😡",
                    "Gua tandain nama akun lu mkonmko! 📝",
                    "Maling gandum buat beli obat gemuk ya lu?! 🤬",
                    "Awas lu ya kon, gua tiup desa lu! 💣",
                    "Utang gandum dibalas rata tanah kon! ☠️",
                ];
                $messages = [
                    "Heh cungkring mkonmko! Balikin gandum gua gak lu! Lu kira badan lu kurus terus gua ga tega bales nyerang?! Gua tandain akun lu ya!",
                    "Buset mkonmko berani-beraninya malak gua! Awas lu ya ntar malam gua tiup desa lu sampe rata sama tanah!",
                    "Maling lu kon! Gandum {stolen} biji gua lu curi buat beli suplemen penambah nafsu makan ya?! Awas lu kalo ga gemuk-gemuk juga ntar!",
                    "Sombong lu kon mentang-mentang bawa gerobak banyak! Gua doain gerobak lu keberatan muatan terus lu yang disuruh dorong sampe patah pinggang!",
                    "Awas lu ya mkonmko! Utang gandum dibalas gandum, besok gua kuras balik desa lu biar lu makin kering kerontang!",
                    "Berani banget ya si lidi satu ini maling di kandang macan! Tunggu gua bales, bakal gua bantai sampe lu nangis ke emak lu!",
                    "Kon mkonmko, dapet harta banyak jangan lupa buat beli susu penambah berat badan ya, jangan cuma buat beli pasukan remuk lagi!",
                    "Cuma bisa nyolong pas gua lagi tidur ya kon? Mental lu emang setipis tulang selangka lu! Tunggu pembalasan gua!",
                ];
                break;
        }

        $chosenTopic = !empty($topics) ? $topics[array_rand($topics)] : "Pesan dari Bot";
        $chosenMessage = !empty($messages) ? $messages[array_rand($messages)] : "";

        return [
            'topic' => $chosenTopic,
            'body'  => $chosenMessage,
        ];
    }
}
