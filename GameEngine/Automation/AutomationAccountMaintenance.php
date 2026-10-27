<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Project:       TravianZ                                                    ##
##  Filename:      AutomationAccountMaintenance.php                            ##
##  Split&Refactor Shadow													   ##
##  Purpose:       Account deletion, inactive accounts, bans, invitations,     ##
##                 climbers                                                    ##
##                                                                             ##
##  Phase S2: Trait extracted from GameEngine/Automation.php                   ##
##            (Automation class).                                              ##
##  Methods were moved IDENTICALLY, with no logic changes.                     ##
##                                                                             ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##  URLs:          https://travianz.org                                        ##
##                 https://github.com/Shadowss/TravianZ                        ##
#################################################################################

trait AutomationAccountMaintenance {


    private function clearDeleting() {
    	global $database;
        
        $needDelete = $database->getNeedDelete();
        if(count($needDelete) > 0) {
        	
        	//Remove the time limit, otherwise deleting players with 80 or more villages couldn't be deleted in one run
        	@set_time_limit(0);
        	
            foreach($needDelete as $need) {
                $need['uid'] = (int) $need['uid'];
                
                //Get the villages which have to be deleted
                $needVillages = $database->getVillagesID($need['uid']);
                
                //Delete all villages
                $database->DelVillage($needVillages);

                for($i = 0;$i < 20; $i++){
                    $q = "SELECT id FROM ".TB_PREFIX."users where friend".$i." = ".$need['uid']." or friend".$i."wait = ".$need['uid']."";
                    $array = $database->query_return($q);
                    foreach($array as $friend){
                        $database->deleteFriend($friend['id'],"friend".$i);
                        $database->deleteFriend($friend['id'],"friend".$i."wait");
                    }
                }

                $database->updateUserField($need['uid'], 'alliance', 0, 1);

                if($database->isAllianceOwner($need['uid'])){
                    $alliance = $database->getUserAllianceID($need['uid']);
                    $newowner = $database->getAllMember2($alliance);
                    $newleader = $newowner['id'];
                    $q = "UPDATE " . TB_PREFIX . "alidata set leader = ".(int) $newleader." where id = ".(int) $alliance."";
                    $database->query($q);
                    $database->updateAlliPermissions($newleader, $alliance, "Leader", 1, 1, 1, 1, 1, 1, 1);
                    Automation::updateMax($newleader);
                }

                if (isset($alliance)) $database->deleteAlliance($alliance);
                
                $q = "DELETE FROM ".TB_PREFIX."hero where uid = ".$need['uid'];
                $database->query($q);

                $q = "DELETE FROM ".TB_PREFIX."mdata where target = ".$need['uid']." or owner = ".$need['uid'];
                $database->query($q);

                $q = "DELETE FROM ".TB_PREFIX."ndata where uid = ".$need['uid'];
                $database->query($q);

                $q = "DELETE FROM ".TB_PREFIX."users where id = ".$need['uid'];
                $database->query($q);

                $q = "DELETE FROM ".TB_PREFIX."deleting where uid = ".$need['uid'];
                $database->query($q);
            }
        }
    }

    private function ClearUser() {
        global $database;
        
        if(AUTO_DEL_INACTIVE) {
            $time = time() - UN_ACT_TIME;
            $q = "INSERT INTO ".TB_PREFIX."deleting SELECT id, UNIX_TIMESTAMP() FROM ".TB_PREFIX."users WHERE timestamp < $time AND tribe IN(1, 2, 3, 6, 7, 8, 9)";
            $database->query($q);
        }
    }



    private function checkInvitedPlayes() {
        global $database;
        
        $q = "SELECT id, invited FROM ".TB_PREFIX."users WHERE invited > 0";
        $array = $database->query_return($q);

        // preload villages data
        $userIDs = [];
        foreach($array as $user) {
            $userIDs[] = $user['id'];
        }
        $database->getProfileVillages($userIDs);

        // continue...
        foreach($array as $user) {
            $numusers = mysqli_fetch_array(mysqli_query($database->dblink,"SELECT Count(*) as Total FROM ".TB_PREFIX."users WHERE id = ".(int) $user['invited']), MYSQLI_ASSOC);
            if($numusers['Total'] > 0){
                $varray = count($database->getProfileVillages($user['id']));
                if($varray > 1){
                    $usergold = $database->getUserField($user['invited'],"gold",0);
                    $gold = $usergold+50;
                    $database->updateUserField($user['invited'],"gold",$gold,1);
                    $database->updateUserField($user['id'],"invited",0,1);
                }
            }
        }
    }

    private function updateGeneralAttack() {
        global $database;

        mysqli_query($database->dblink, "
            UPDATE ".TB_PREFIX."general
                SET
                    shown = 0
                WHERE
                    shown = 1 AND
                    `time` < (UNIX_TIMESTAMP() - (86400 * 8))");
    }

    private function procNewClimbers() {
    	global $database, $ranking;

        $ranking->procRankArray();
        $climbers = $ranking->getRank();
        if(count($climbers) > 0){
            $q = "SELECT week FROM ".TB_PREFIX."medal order by week DESC LIMIT 0, 1";
            $result = mysqli_query($database->dblink,$q);
            if(mysqli_num_rows($result)) {
                $row = mysqli_fetch_assoc($result);
                $week = $row['week'] + 1;
            }
            else $week = 1;

            $q = "SELECT id FROM ".TB_PREFIX."users where oldrank = 0 and id > 5";
            $array = $database->query_return($q);
            foreach($array as $user){
                $newrank = $ranking->getUserRank($user['id']);
                if($week > 1){
                    for($i = $newrank + 1; $i < count($climbers); $i++) {
                        if(isset($climbers[$i]['userid'])){
                            $oldrank = $ranking->getUserRank($climbers[$i]['userid']);
                            $totalpoints = $oldrank - $climbers[$i]['oldrank'];
                            $database->removeclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }                      
                    }
                    $database->updateoldrank($user['id'], $newrank);
                }else{
                    $totalpoints = count($climbers) - $newrank;
                    $database->setclimberrankpop($user['id'], $totalpoints);
                    $database->updateoldrank($user['id'], $newrank);
                    for($i = 1; $i < $newrank; $i++){
                        if(isset($climbers[$i]['userid'])){
                            $oldrank = $ranking->getUserRank($climbers[$i]['userid']);
                            $totalpoints = count($climbers) - $oldrank;
                            $database->setclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }                     
                    }
                    for($i = $newrank + 1; $i < count($climbers); $i++){
                        if(isset($climbers[$i]['userid'])){
                            $oldrank = $ranking->getUserRank($climbers[$i]['userid']);
                            $totalpoints = count($climbers) - $oldrank;
                            $database->setclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }                      
                    }
                }
            }
        }
    }

    private function procClimbers($uid) {
        global $database, $ranking;
        
        $ranking->procRankArray();
        $climbers = $ranking->getRank();
        if(count($ranking->getRank()) > 0){
            $q = "SELECT week FROM ".TB_PREFIX."medal order by week DESC LIMIT 0, 1";
            $result = mysqli_query($database->dblink,$q);
            if(mysqli_num_rows($result)) {
                $row = mysqli_fetch_assoc($result);
                $week = $row['week'] + 1;
            }
            else $week = 1;

            $myrank = $ranking->getUserRank($uid);
            if(isset($climbers[$myrank]['oldrank']) && $climbers[$myrank]['oldrank'] > $myrank){
                for($i = $myrank + 1; $i <= $climbers[$myrank]['oldrank']; $i++) {
                    if(isset($climbers[$i]['oldrank'])){
                        $oldrank = $ranking->getUserRank($climbers[$i]['userid']);
                        if($week > 1){
                            $totalpoints = $oldrank - $climbers[$i]['oldrank'];
                            $database->removeclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }else{
                            $totalpoints = count($ranking->getRank()) - $oldrank;
                            $database->setclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }
                    }              
                }
                if(isset($climbers[$myrank]['oldrank'])){
                    if($week > 1){
                        $totalpoints = $climbers[$myrank]['oldrank'] - $myrank;
                        $database->addclimberrankpop($climbers[$myrank]['userid'], $totalpoints);
                        $database->updateoldrank($climbers[$myrank]['userid'], $myrank);
                    }else{
                        $totalpoints = count($ranking->getRank()) - $myrank;
                        $database->setclimberrankpop($climbers[$myrank]['userid'], $totalpoints);
                        $database->updateoldrank($climbers[$myrank]['userid'], $myrank);
                    }
                }        
            }else if(isset($climbers[$myrank]['oldrank']) && $climbers[$myrank]['oldrank'] < $myrank){
                for($i = $climbers[$myrank]['oldrank']; $i < $myrank; $i++) {
                    if(isset($climbers[$i]['oldrank'])){
                        $oldrank = $ranking->getUserRank($climbers[$i]['userid']);
                        if($week > 1){
                            $totalpoints = $climbers[$i]['oldrank'] - $oldrank;
                            $database->addclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }else{
                            $totalpoints = count($ranking->getRank()) - $oldrank;
                            $database->setclimberrankpop($climbers[$i]['userid'], $totalpoints);
                            $database->updateoldrank($climbers[$i]['userid'], $oldrank);
                        }
                    }          
                }
                if(isset($climbers[$myrank-1]['oldrank'])){
                    if($week > 1){
                        $totalpoints = $myrank - $climbers[$myrank-1]['oldrank'];
                        $database->removeclimberrankpop($climbers[$myrank-1]['userid'], $totalpoints);
                        $database->updateoldrank($climbers[$myrank-1]['userid'], $myrank);
                    }else{
                        $totalpoints = count($ranking->getRank()) - $myrank;
                        $database->setclimberrankpop($climbers[$myrank-1]['userid'], $totalpoints);
                        $database->updateoldrank($climbers[$myrank-1]['userid'], $myrank);
                    }
                }               
            }
        }
        $ranking->procARankArray();
        $aid = $database->getUserField($uid,"alliance",0);
        if(count($ranking->getRank()) > 0 && $aid != 0){
            $ally = $database->getAlliance($aid);
            $memberlist = $database->getAllMember($ally['id']);
            $oldrank = 0;

            $memberIDs = [];
            foreach($memberlist as $member) {
                $memberIDs[] = $member['id'];
            }
            $data = $database->getVSumField($memberIDs,"pop");

            if (count($data)) {
                foreach ($data as $row) {
                    $oldrank += $row['Total'];
                }
            }

            if($ally['oldrank'] != $oldrank){
                if($ally['oldrank'] < $oldrank) {
                    $totalpoints = $oldrank - $ally['oldrank'];
                    $database->addclimberrankpopAlly($ally['id'], $totalpoints);
                    $database->updateoldrankAlly($ally['id'], $oldrank);
                } else
                    if($ally['oldrank'] > $oldrank) {
                        $totalpoints = $ally['oldrank'] - $oldrank;
                        $database->removeclimberrankpopAlly($ally['id'], $totalpoints);
                        $database->updateoldrankAlly($ally['id'], $oldrank);
                    }
            }
        }
    }

    private function checkBan() {
        global $database;

        mysqli_query($database->dblink, "
            UPDATE ".TB_PREFIX."banlist as b
                JOIN ".TB_PREFIX."users as u ON b.uid = u.id
                    SET
                        b.active = 0,
                        u.access = 2
                    WHERE
                        b.active = 1 AND
                        b.`end` < UNIX_TIMESTAMP() AND
                        b.`end` > 0");
    }

    public static function updateMax($leader) {
        if (empty($GLOBALS['bid18'])) {
            require_once dirname(__DIR__) . '/Data/buidata.php';
        }
        global $bid18, $database;
        
        $leader = (int) $leader;
        if ($leader <= 0) {
            return;
        }

        $q = mysqli_fetch_array(mysqli_query($database->dblink,"SELECT Count(*) as Total FROM " . TB_PREFIX . "alidata where leader = ". $leader), MYSQLI_ASSOC);
        if ($q['Total'] > 0) {
            $villages = $database->getVillagesID2($leader);
            $max = 0;

            if (!empty($villages)) {
                // cache resource levels
                $vilIDs = [];
                foreach($villages as $village){
                    if (isset($village['wref'])) {
                        $vilIDs[$village['wref']] = true;
                    }
                }
                if (!empty($vilIDs)) {
                    $database->cacheResourceLevels(array_keys($vilIDs));
                }

                foreach($villages as $village){
                    $wref = $village['wref'] ?? 0;
                    if (!$wref) continue;
                    $field = $database->getResourceLevel($wref, false);
                    for($i = 19; $i <= 40; $i++){
                        if(isset($field['f'.$i.'t']) && $field['f'.$i.'t'] == 18){
                            $level = (int)($field['f'.$i] ?? 0);
                            $attri = isset($bid18[$level]['attri']) ? (int)$bid18[$level]['attri'] : ($level * 3);
                            if($attri > $max){
                                $max = $attri;
                            }
                        }
                    }
                }
            }

            // In Travian, creating an alliance requires Embassy Level 3 (capacity 9).
            // Any existing valid alliance must have a baseline capacity of at least 9 members.
            $max = max(9, (int) $max);

            $q = "UPDATE ".TB_PREFIX."alidata set max = ".(int) $max." where leader = ".$leader;
            $database->query($q);
            if (method_exists($database, 'clearQueryCache')) {
                $database->clearQueryCache('alliance');
            }
        }
    }
}
