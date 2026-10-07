<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : NameGenerator.php                                         ##
##  Type           : Realistic Name & Village Generator for TravianZ Bots      ##
##  Purpose        : Generates natural human player names (online API + local) ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class NameGenerator {

    /**
     * Tribe-specific historical warrior names
     */
    private static $tribeNames = [
        1 => [ // Romans
            'Maximus', 'Marcus', 'Caesar', 'Aurelius', 'Trajan', 'Cassius', 'Julius',
            'Scipio', 'Tiberius', 'Valerius', 'Augustus', 'Flavius', 'Lucius', 'Brutus',
            'Decimus', 'Claudius', 'Agrippa', 'Hadrian', 'Marcellus', 'Lucian'
        ],
        2 => [ // Teutons
            'Alaric', 'Theodoric', 'Odoacer', 'Hermann', 'Arminius', 'Gunnar', 'Konrad',
            'Wolfgang', 'Gero', 'Siegfried', 'Otto', 'Dietrich', 'Brunner', 'Fritz',
            'Klaus', 'Gunter', 'Volker', 'Baldur', 'Raimund', 'Gottfried'
        ],
        3 => [ // Gauls
            'Brennus', 'Asterix', 'Vercingetorix', 'Ambiorix', 'Dumnorix', 'Camulogenus',
            'Boudica', 'Lugotorix', 'Belovesus', 'Diviciacus', 'Teutates', 'Moritasgus',
            'Cavarnos', 'Casticus', 'Segovax', 'Taximagulus', 'Catuvolcus'
        ],
        6 => [ // Huns
            'Attila', 'Bleda', 'Rugila', 'Uldin', 'Octar', 'Mundzuk', 'Dengizich',
            'Ernak', 'Ellac', 'Khan_Temur', 'Tarkan', 'Batukhan', 'Subutai', 'Arpakh',
            'Ilkhan', 'Kuber', 'Berik', 'Alp_Arslan', 'Timur', 'Nogai'
        ],
        7 => [ // Egyptians
            'Ramses', 'Anubis', 'Osiris', 'Horus', 'Thutmose', 'Tutankhamun', 'Akhenaten',
            'Seti', 'Khafre', 'Amenhotep', 'Cleopatra', 'Nefertiti', 'Khufu', 'Sneferu',
            'Mentuhotep', 'Djoser', 'Pepi', 'Ahmose', 'Horemheb', 'Imhotep'
        ],
        8 => [ // Spartans
            'Leonidas', 'Agesilaus', 'Pausanias', 'Lysander', 'Brasidas', 'Cleomenes',
            'Archidamus', 'Lycurgus', 'Dienekes', 'Aristodemus', 'Gorgo', 'Pleistoanax',
            'Leotychidas', 'Agis', 'Chares', 'Alcamenes', 'Polydorus', 'Teleclus'
        ],
        9 => [ // Vikings
            'Ragnar', 'Bjorn', 'Ivar', 'Sigurd', 'Harald', 'Rollo', 'Floki',
            'Ubba', 'Halfdan', 'Leif', 'Erik', 'Canute', 'Olaf', 'Torstein',
            'Gorm', 'Sweyn', 'Aslaug', 'Lagertha', 'Hakon', 'Torvald'
        ],
        10 => [ // Nusantara
            'GajahMada', 'HayamWuruk', 'RadenWijaya', 'Kertanegara', 'KenArok',
            'Tribhuwana', 'Adityawarman', 'EmpuNala', 'AryaWiraraja', 'Bhayangkara',
            'Suryawisesa', 'Jayakatwang', 'PatihNambi', 'DyahGitarja', 'BraWijaya'
        ],
    ];

    /**
     * Casual modern gamer names with numbers/initials
     */
    private static $modernGamerNames = [
        'Alex', 'Marcus', 'Stefan', 'David', 'Adrian', 'Julian', 'Felix', 'Erik',
        'Lucas', 'Oliver', 'Kevin', 'Daniel', 'Nico', 'Florian', 'Tobias', 'Jonas',
        'Simon', 'Sebastian', 'Michael', 'Patrick', 'Christian', 'Fabian', 'Marco',
        'Jan', 'Dominik', 'Manuel', 'Dennis', 'Lukas', 'Andreas', 'Thomas'
    ];

    /**
     * Indonesian / Local player handles
     */
    private static $indonesianNames = [
        'BimaSakti', 'Arya_K', 'Dimas', 'Rizky', 'Satria', 'GatotKaca', 'Bayu',
        'Fajar', 'Rian', 'Surya', 'Pratama', 'Aditya', 'Agung', 'Bagus', 'Cahyo',
        'Darmawan', 'Eko_P', 'Gilang', 'Hendra', 'Indra', 'Joko_W', 'Mahesa',
        'Nugroho', 'Pandu', 'Raden', 'Seno', 'Teguh', 'Wahyu', 'Yudha'
    ];

    /**
     * Classic RPG / Travian clan handles
     */
    private static $rpgNicknames = [
        'ShadowWolf', 'IronHeart', 'StormRider', 'NightHawk', 'SilentBlade',
        'WarLord', 'BloodHunter', 'SteelHawk', 'FrostBite', 'ApexPredator',
        'DragonBane', 'DarkKnight', 'IronClad', 'SilverArrow', 'ThunderStrike',
        'GhostRider', 'Vanguard', 'DeathBringer', 'OverLord', 'PeaceMaker',
        'IronFist', 'WildFire', 'StormBreaker', 'BlackCobra', 'CrimsonGhost'
    ];

    /**
     * Realistic village names
     */
    private static $villageNames = [
        1 => ['Rome', 'Ravenna', 'Mediolanum', 'Capua', 'Aquileia', 'Pompeii', 'Antium', 'Ostia', 'Tivoli', 'Arretium'],
        2 => ['Magdeburg', 'Nuremberg', 'Augsburg', 'Worms', 'Heidelberg', 'Coburg', 'Bamberg', 'Marburg', 'Erfurt', 'Fulda'],
        3 => ['Lutetia', 'Lugdunum', 'Alesia', 'Bibracte', 'Gergovia', 'Tolosa', 'Burdigala', 'Nemossos', 'Vienn', 'Cenabum'],
        6 => ['Camp_Attila', 'Steppe_Hold', 'Altay', 'Danube_Camp', 'Ordos_Ridge', 'Black_Steppe', 'Tengri_Fort', 'Volga_Camp'],
        7 => ['Alexandria', 'Thebes', 'Memphis', 'Giza', 'Luxor', 'Aswan', 'Karnak', 'Abydos', 'Edfu', 'Tanis'],
        8 => ['Sparta', 'Laconia', 'Messenia', 'Amfissa', 'Peloponnese', 'Therapne', 'Geraki', 'Gythium', 'Sellasia'],
        9 => ['Kattegat', 'Uppsala', 'Jorvik', 'Hedeby', 'Birka', 'Trondheim', 'Roskilde', 'Ribe', 'Gudvangen', 'Stavanger'],
        10 => ['Wilwatikta', 'Trowulan', 'Daha', 'Kahuripan', 'Tumapel', 'Wengker', 'Mataun', 'Pajang', 'Janggala', 'Lasem'],
    ];

    /**
     * General fantasy / medieval / Indonesian village names
     */
    private static $generalVillages = [
        'Valhalla', 'Camelot', 'Avalon', 'Ironhold', 'Oasis', 'Sunrise', 'Stonehaven',
        'Winterfell', 'Blackrock', 'Falconridge', 'Rivendell', 'Silverkeep', 'Oakhaven',
        'Riverbend', 'Sanctuary', 'Elysium', 'Singhasari', 'Majapahit', 'Batavia',
        'Pajajaran', 'Kalingga', 'Nusantara', 'Highland', 'Pinecrest', 'Clearwater'
    ];

    /**
     * Realistic player bios
     */
    private static $playerBios = [
        'Si vis pacem, para bellum.',
        'Membangun desa santai, jangan diganggu :)',
        'Just casual gameplay.',
        'Trade and peace welcome!',
        'No attack without reason.',
        'Veni, vidi, vici.',
        'Defend or die!',
        'Petani santai.',
        'Building my empire stone by stone.',
        'Blood and honor.',
        '' // Empty bio like many real players
    ];

    /**
     * Generate a natural-looking player username.
     * Tries online API first with strict timeout, falls back to local bank.
     *
     * @param int $tribe Tribe ID (1..9)
     * @param bool $allowApi Whether to try fetching from randomuser API
     * @return string Realistic username
     */
    public static function generateUsername(int $tribe = 0, bool $allowApi = true): string {
        // 20% chance to fetch a real world name from online API if allowed
        if ($allowApi && rand(1, 100) <= 25) {
            $apiName = self::fetchFromApi();
            if ($apiName !== null) {
                return $apiName;
            }
        }

        // Style selector:
        // 1 = Historical / Mythological (aligned with tribe) (40%)
        // 2 = Modern casual gamer (e.g. Alex94, Marcus_R) (25%)
        // 3 = Indonesian local name (e.g. BimaSakti, Rizky99) (20%)
        // 4 = Classic gamer handle (e.g. ShadowWolf, IronClad) (15%)
        $roll = rand(1, 100);

        if ($roll <= 40 && isset(self::$tribeNames[$tribe])) {
            $base = self::$tribeNames[$tribe][array_rand(self::$tribeNames[$tribe])];
            return self::applyCasualSuffix($base);
        } elseif ($roll <= 65) {
            $base = self::$modernGamerNames[array_rand(self::$modernGamerNames)];
            return self::applyGamerNumber($base);
        } elseif ($roll <= 85) {
            $base = self::$indonesianNames[array_rand(self::$indonesianNames)];
            return (rand(1, 100) <= 50) ? self::applyGamerNumber($base) : $base;
        } else {
            return self::$rpgNicknames[array_rand(self::$rpgNicknames)];
        }
    }

    /**
     * Generate a natural-sounding village name.
     */
    public static function generateVillageName(string $username, int $tribe = 0): string {
        $roll = rand(1, 100);

        if ($roll <= 45 && isset(self::$villageNames[$tribe])) {
            // Tribe historical settlement
            return self::$villageNames[$tribe][array_rand(self::$villageNames[$tribe])];
        } elseif ($roll <= 75) {
            // General fantasy / medieval / Indonesian city
            return self::$generalVillages[array_rand(self::$generalVillages)];
        } else {
            // Player personalized village
            $suffixes = [' Haven', ' Stronghold', ' Village', ' Realm', ' Sanctuary', ' Citadel'];
            $suffix = $suffixes[array_rand($suffixes)];
            return $username . $suffix;
        }
    }

    /**
     * Pick a realistic player bio / quote.
     */
    public static function generateBio(int $tribe = 0): string {
        return self::$playerBios[array_rand(self::$playerBios)];
    }

    /**
     * Fetch a natural name from randomuser.me API with short timeout.
     */
    private static function fetchFromApi(): ?string {
        $url = 'https://randomuser.me/api/?inc=name,login';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 1.5,
                'ignore_errors' => true,
                'user_agent' => 'TravianZ-BotEngine/1.0'
            ]
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) {
            return null;
        }

        $json = @json_decode($raw, true);
        if (empty($json['results'][0])) {
            return null;
        }

        $res = $json['results'][0];
        $firstName = preg_replace('/[^a-zA-Z]/', '', $res['name']['first'] ?? '');

        if (strlen($firstName) >= 3 && strlen($firstName) <= 12) {
            return self::applyGamerNumber($firstName);
        }

        $loginUser = preg_replace('/[^a-zA-Z0-9_]/', '', $res['login']['username'] ?? '');
        if (strlen($loginUser) >= 3 && strlen($loginUser) <= 15) {
            return ucfirst($loginUser);
        }

        return null;
    }

    /**
     * Adds natural numbers like birth year (90..99) or lucky numbers (7, 88, 99).
     */
    private static function applyGamerNumber(string $name): string {
        $roll = rand(1, 100);
        if ($roll <= 30) {
            return $name . rand(90, 99); // Birth years e.g. Alex94
        } elseif ($roll <= 50) {
            $initials = ['A', 'B', 'K', 'M', 'P', 'R', 'S', 'W'];
            return $name . '_' . $initials[array_rand($initials)]; // e.g. Marcus_K
        } elseif ($roll <= 70) {
            return $name . '_' . rand(10, 99); // e.g. David_24
        } elseif ($roll <= 85) {
            $lucky = [7, 8, 88, 99, 101, 777];
            return $name . $lucky[array_rand($lucky)];
        }
        return $name; // Just pure name
    }

    /**
     * Applies subtle roman/warrior suffix or leaves clean.
     */
    private static function applyCasualSuffix(string $name): string {
        if (rand(1, 100) <= 25) {
            $suffixes = ['_IX', '_VII', '_X', '99', '88', '_R'];
            return $name . $suffixes[array_rand($suffixes)];
        }
        return $name;
    }
}
