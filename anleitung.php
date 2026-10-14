<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : anleitung.php                      	                   ##
##  Type           : In Game Part of Main Page                                 ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Dzoki & Advocaite & Donnchadh                             ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  URLs:          : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/config.php");
include_once("GameEngine/Database.php");
require_once __DIR__ . "/GameEngine/Lang/loader.php";
tz_load_language(LANG);
AccessLogger::logRequest();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo SERVER_NAME; ?></title>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link rel="stylesheet" type="text/css" href="img/tutorial/main.css"/>
	<link rel="stylesheet" type="text/css" href="img/tutorial/flaggs.css"/>
	<link rel="stylesheet" type="text/css" href="css/mobile_engine.css?v=20261009_m2"/>
	<meta name="content-language" content="en"/>
	<meta http-equiv="imagetoolbar" content="no"/>
	<script src="mt-core.js" type="text/javascript"></script>
	<script src="mt-more.js" type="text/javascript"></script>
	<script src="new.js" type="text/javascript"></script>
	<script src="new2.js" type="text/javascript"></script>
	<style type="text/css" media="screen">

	</style>
</head>
<body class="webkit contentPage">
<div class="wrapper">
<div id="country_select">

</div>
<div id="header">
	<h1><?php echo defined('PUBLIC_WELCOME_TO') ? PUBLIC_WELCOME_TO : 'Welcome to'; ?> <?php echo SERVER_NAME; ?></h1>
</div>

<div id="navigation">

<a href="index.php" class="home"><img src="img/x.gif" alt="Travian"/></a>

	<table class="menu">

	<tr>

		<td><a href="tutorial.php"><span><?php echo defined('TUTORIAL') ? TUTORIAL : 'Tutorial'; ?></span></a></td>

		<td><a href="anleitung.php"><span><?php echo defined('PUBLIC_MANUAL') ? PUBLIC_MANUAL : 'Manual'; ?></span></a></td>

		<td><a href="https://github.com/Shadowss/TravianZ/discussions" target="_blank"><span><?php echo defined('FORUM') ? FORUM : 'Forum'; ?></span></a></td>





		<td><a href="index.php?signup"><span><?php echo defined('PUBLIC_REGISTER') ? PUBLIC_REGISTER : 'Register'; ?></span></a></td>

		<td><a href="index.php?login"><span><?php echo defined('LOGIN') ? LOGIN : 'Login'; ?></span></a></td>

</tr>

	</table>

</div>






<div id="content">

	<div class="grit">


<h1><?php echo defined('PUBLIC_MANUAL') ? PUBLIC_MANUAL : 'Manual'; ?></h1>



<p class="submenu">

<a href="anleitung.php"><?php echo defined('PUBLIC_TRIBES') ? PUBLIC_TRIBES : 'The tribes'; ?></a> |

<a href="anleitung.php?s=1"><?php echo defined('BUILDINGS') ? BUILDINGS : 'Buildings'; ?></a> |

<a href="anleitung.php?s=3"><?php echo defined('MANUAL_FAQ') ? MANUAL_FAQ : 'Travian FAQ'; ?></a>

</p>



<?php
if(!isset($_GET['s'])) {
$_GET['s'] = ""; }
if ($_GET['s'] == "") {
include("Templates/Anleitung/0.tpl"); }
if ($_GET['s'] == "1") {
include("Templates/Anleitung/1.tpl"); }
if ($_GET['s'] == "3") {
include("Templates/Anleitung/3.tpl"); }
if ($_GET['s'] == "4") {
include("Templates/Anleitung/4.tpl"); }
?>



</ul>

<div class="footer"></div>

</div>

</div>

<div id="iframe_layer" class="overlay">



<div class="mask closer"></div>







<div class="overlay_content">

<a href="index.php" class="closer"><img class="dynamic_img" alt="<?php echo defined('PUBLIC_CLOSE') ? PUBLIC_CLOSE : 'Close'; ?>" src="img/un/x.gif" /></a>

<h2>Anleitung</h2>



<div id="frame_box" >

</div>

<div class="footer"></div>

</div>



</div>




</body>
</html>
