<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : village_action.php                                        ##
##  Type           : Action handler for Weather & Plague mechanics             ##
##  Purpose        : Processes traditional herbal cures, quarantine toggles    ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

include_once("GameEngine/Session.php");
include_once("GameEngine/Village.php");
include_once("GameEngine/Weather.php");
include_once("GameEngine/Plague.php");

if (!$session->logged_in) {
    header("Location: login.php");
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$wid = (int)($village->wid ?? $_SESSION['wid'] ?? 0);

// Verify ownership
$owner = (int)$database->getVillageField($wid, 'owner');
if ($wid > 0 && $owner === (int)$session->uid) {
    if ($action === 'cure_plague') {
        $result = Plague::applyTraditionalMedicine($wid, $session->uid);
        $_SESSION['weather_plague_msg'] = $result['message'];
        $_SESSION['weather_plague_type'] = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'toggle_quarantine') {
        Plague::toggleQuarantine($wid);
        $plague = Plague::getVillagePlague($wid);
        $isQ = !empty($plague['quarantine']);
        $_SESSION['weather_plague_msg'] = $isQ 
            ? 'Desa berhasil dikarantina! Pasar dan gerbang desa ditutup sementara untuk mencegah penularan wabah.' 
            : 'Karantina telah dicabut! Warga dan pedagang kembali leluasa beraktivitas.';
        $_SESSION['weather_plague_type'] = 'info';
    }
}

$ref = $_SERVER['HTTP_REFERER'] ?? 'dorf1.php';
header("Location: " . $ref);
exit;
