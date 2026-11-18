<?php

/**
 * Unit Test for Bot Taunt & Reaction Messaging System (BotTaunt.php)
 */

require_once __DIR__ . '/../GameEngine/BotTaunt.php';

class BotTauntTest {
    private int $passes = 0;
    private int $fails = 0;

    private function assert(bool $condition, string $testName): void {
        if ($condition) {
            $this->passes++;
            echo "  [PASS] $testName\n";
        } else {
            $this->fails++;
            echo "  [FAIL] $testName\n";
        }
    }

    public function runAll(): void {
        echo "=== Running BotTauntTest ===\n";
        $this->testSentenceCounts();
        $this->testPlaceholderReplacement();
        $this->testIsRealPlayerGuard();
        $this->testCooldownAndEventTypes();
        echo "\nResults: {$this->passes} passed, {$this->fails} failed.\n";
        if ($this->fails > 0) {
            exit(1);
        }
    }

    private function testSentenceCounts(): void {
        echo "\nTesting Sentence Counts & Content Variety:\n";

        // Reflection to inspect message arrays directly
        $method = new ReflectionMethod('BotTaunt', 'getRandomTaunt');
        
        $playerFailSamples = [];
        for ($i = 0; $i < 300; $i++) {
            $res = BotTaunt::getRandomTaunt(BotTaunt::EVENT_PLAYER_FAIL);
            $this->assert(!empty($res['topic']), "Topic for EVENT_PLAYER_FAIL is not empty");
            $this->assert(!empty($res['body']), "Body for EVENT_PLAYER_FAIL is not empty");
            $playerFailSamples[$res['body']] = true;
        }
        $uniquePlayerFail = count($playerFailSamples);
        $this->assert($uniquePlayerFail >= 20, "EVENT_PLAYER_FAIL has at least 20 unique sentences (found $uniquePlayerFail)");

        $botRaidSuccessSamples = [];
        for ($i = 0; $i < 300; $i++) {
            $res = BotTaunt::getRandomTaunt(BotTaunt::EVENT_BOT_RAID_SUCCESS);
            $this->assert(!empty($res['topic']), "Topic for EVENT_BOT_RAID_SUCCESS is not empty");
            $this->assert(!empty($res['body']), "Body for EVENT_BOT_RAID_SUCCESS is not empty");
            $botRaidSuccessSamples[$res['body']] = true;
        }
        $uniqueBotRaid = count($botRaidSuccessSamples);
        $this->assert($uniqueBotRaid >= 20, "EVENT_BOT_RAID_SUCCESS has at least 20 unique sentences (found $uniqueBotRaid)");

        // Bonus events
        $botFailRes = BotTaunt::getRandomTaunt(BotTaunt::EVENT_BOT_FAIL);
        $this->assert(!empty($botFailRes['topic']) && !empty($botFailRes['body']), "EVENT_BOT_FAIL produces valid topic and body");

        $playerRaidRes = BotTaunt::getRandomTaunt(BotTaunt::EVENT_PLAYER_RAID_SUCCESS);
        $this->assert(!empty($playerRaidRes['topic']) && !empty($playerRaidRes['body']), "EVENT_PLAYER_RAID_SUCCESS produces valid topic and body");
    }

    private function testPlaceholderReplacement(): void {
        echo "\nTesting Placeholder Replacement Logic:\n";

        $search = ['{player}', '{bot}', '{village}', '{stolen}', '{losses}'];
        $replace = ['SultanGanteng', 'Bot_Surabaya', 'Desa Majapahit', '12,500', '350'];

        $sampleText = "Halo {player}, desa {village} kena rampok {stolen} resource oleh {bot}, dan {losses} pasukanmu punah!";
        $replaced = str_replace($search, $replace, $sampleText);

        $this->assert(strpos($replaced, 'SultanGanteng') !== false, "Player placeholder replaced correctly");
        $this->assert(strpos($replaced, 'Bot_Surabaya') !== false, "Bot placeholder replaced correctly");
        $this->assert(strpos($replaced, 'Desa Majapahit') !== false, "Village placeholder replaced correctly");
        $this->assert(strpos($replaced, '12,500') !== false, "Stolen placeholder replaced correctly");
        $this->assert(strpos($replaced, '350') !== false, "Losses placeholder replaced correctly");
        $this->assert(strpos($replaced, '{player}') === false, "No unreplaced tags remain");
    }

    private function testIsRealPlayerGuard(): void {
        echo "\nTesting isRealPlayer Safety Guard:\n";

        // IDs <= 3 (Nature, Natars, etc) must never be treated as real players
        $this->assert(BotTaunt::isRealPlayer(0) === false, "UID 0 is not a real player");
        $this->assert(BotTaunt::isRealPlayer(1) === false, "UID 1 (Nature) is not a real player");
        $this->assert(BotTaunt::isRealPlayer(2) === false, "UID 2 (Nature/System) is not a real player");
        $this->assert(BotTaunt::isRealPlayer(3) === false, "UID 3 (Natars) is not a real player");
    }

    private function testCooldownAndEventTypes(): void {
        echo "\nTesting Event Types Constants:\n";

        $this->assert(BotTaunt::EVENT_PLAYER_FAIL === 'player_fail', "EVENT_PLAYER_FAIL constant is 'player_fail'");
        $this->assert(BotTaunt::EVENT_BOT_RAID_SUCCESS === 'bot_raid_success', "EVENT_BOT_RAID_SUCCESS constant is 'bot_raid_success'");
        $this->assert(BotTaunt::EVENT_BOT_FAIL === 'bot_fail', "EVENT_BOT_FAIL constant is 'bot_fail'");
        $this->assert(BotTaunt::EVENT_PLAYER_RAID_SUCCESS === 'player_raid_success', "EVENT_PLAYER_RAID_SUCCESS constant is 'player_raid_success'");
    }
}

$test = new BotTauntTest();
$test->runAll();
