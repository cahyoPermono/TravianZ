<?php

#################################################################################
##  Filename       : WeatherPlagueTest.php                                     ##
##  Type           : Unit and Integration Test for Weather & Plague modules   ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Weather.php';
require_once dirname(__DIR__) . '/GameEngine/Plague.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running Weather & Plague Unit Tests ===\n";

// 1. Test Weather Table and Active Weather retrieval
echo "1. Testing Weather Generation and Persistence...\n";
Weather::ensureTable();
$weather = Weather::getCurrentWeather('global');
assertTrue(!empty($weather), "Active weather returned");
assertTrue(isset($weather['weather_type']), "Weather contains weather_type");
assertTrue((int)$weather['weather_type'] >= 1 && (int)$weather['weather_type'] <= 5, "Weather type is within valid range 1..5");
assertTrue((int)$weather['end_time'] > time(), "Weather end_time is in the future");

// 2. Test Weather Metadata Info
echo "2. Testing Weather Metadata and Multipliers...\n";
$infoClear = Weather::getWeatherInfo(Weather::TYPE_CLEAR);
assertEqual('Musim Subur / Cuaca Cerah', $infoClear['name'], "Clear weather Indonesian name matches");
assertEqual('☀️', $infoClear['icon'], "Clear weather icon matches");

$infoMonsoon = Weather::getWeatherInfo(Weather::TYPE_MONSOON);
assertEqual('Hujan Badai Muson', $infoMonsoon['name'], "Monsoon Indonesian name matches");
assertEqual('🌧️', $infoMonsoon['icon'], "Monsoon weather icon matches");

// Force Clear Weather for exact numerical verification
Weather::generateNextWeather('global', Weather::TYPE_CLEAR);
assertEqual(1.15, Weather::getProductionMultiplier(1, 'crop'), "Clear weather boosts crop production by 15%");
assertEqual(1.10, Weather::getProductionMultiplier(1, 'wood'), "Clear weather boosts wood production by 10%");
assertEqual(1.20, Weather::getSpeedMultiplier(1, true), "Clear weather boosts merchant speed by 20%");
assertEqual(1.05, Weather::getSpeedMultiplier(1, false), "Clear weather boosts troop speed by 5%");

// Force Monsoon Weather
Weather::generateNextWeather('global', Weather::TYPE_MONSOON);
assertEqual(1.15, Weather::getProductionMultiplier(1, 'clay'), "Monsoon boosts clay production by 15%");
assertEqual(0.90, Weather::getProductionMultiplier(1, 'wood'), "Monsoon slows wood production to 90%");
assertEqual(1.15, Weather::getWallBonusMultiplier(), "Monsoon grants 15% wall defense bonus");
assertEqual(0.80, Weather::getSpeedMultiplier(1, false), "Monsoon slows normal troops to 80% speed");
assertEqual(0.95, Weather::getSpeedMultiplier(10, false), "Nusantara troops resist monsoon slowdown (95% speed)");

// Force Fog Weather
Weather::generateNextWeather('global', Weather::TYPE_FOG);
assertEqual(1.30, Weather::getScoutBonusMultiplier(), "Fog grants 30% scouting efficiency bonus");

// 3. Test Plague Engine
echo "3. Testing Plague Engine Infection & Quarantine...\n";
Plague::ensureTable();
$testVid = 999999;

// Ensure clean initial state
Plague::cureVillage($testVid);
assertTrue(!Plague::isVillageInfected($testVid), "Clean village is not infected");
assertEqual(1.0, Plague::getProductionMultiplier($testVid, 'crop'), "Healthy village has 1.0x production multiplier");

// Test Infection with Cholera
Plague::infectVillage($testVid, Plague::PLAGUE_CHOLERA, 1, 7200);
assertTrue(Plague::isVillageInfected($testVid), "Village successfully infected with Cholera");

$plagueData = Plague::getVillagePlague($testVid);
assertEqual(Plague::PLAGUE_CHOLERA, (int)$plagueData['plague_type'], "Plague type matches CHOLERA");
assertEqual(0.80, Plague::getProductionMultiplier($testVid, 'crop'), "Cholera applies -20% production penalty (0.80x)");
assertEqual(0.85, Plague::getCombatMultiplier($testVid), "Cholera applies -15% combat power penalty");

// Test Quarantine toggle
assertEqual(0, (int)$plagueData['quarantine'], "Initial quarantine status is 0");
Plague::toggleQuarantine($testVid);
$plagueQ = Plague::getVillagePlague($testVid);
assertEqual(1, (int)$plagueQ['quarantine'], "Quarantine toggled to 1");

Plague::toggleQuarantine($testVid);
$plagueUnQ = Plague::getVillagePlague($testVid);
assertEqual(0, (int)$plagueUnQ['quarantine'], "Quarantine toggled back to 0");

// Test Cure
Plague::cureVillage($testVid);
assertTrue(!Plague::isVillageInfected($testVid), "Village successfully cured");
assertEqual(1.0, Plague::getProductionMultiplier($testVid, 'crop'), "Cured village returns to 1.0x production");

// 4. Test Push Protection setting
echo "4. Testing Push Protection configuration...\n";
assertTrue(defined('PUSH_PROTECTION_ENFORCE'), "PUSH_PROTECTION_ENFORCE constant is defined");
assertEqual(false, PUSH_PROTECTION_ENFORCE, "PUSH_PROTECTION_ENFORCE is set to false (unrestricted resource sending)");

echo "\nAll Weather & Plague Unit Tests Passed Successfully!\n";
