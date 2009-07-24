<?php
require_once "lib/athp.php";
require_once "lib/db.php";
require_once "pkgconstants.php";
require_once "pkg.inc.php";

$cache = "n";
$title = "OSLO Package Database";
$meta = array();
$meta["title"] = $title;
$meta["keywords"] = "package, database, linux, OSLO";
$meta["ATHP_Date_Created"] = "2005-04-24";
$meta["ATHP_Date_Modified"] = "2005-04-24";
$meta["ATHP_Publisher"] = "OSLO - Web Services";
$meta["ATHP_Creator_Email"] = "linuxweb@linux.corp.hp.com";
$styles = array();
$styles[] = "http://portal.hp.com/lib/navigation/css/homepages-v5.css";
$scripts = array();
$scripts[] = "http://portal.hp.com/lib/navigation/header.js";
$scripts[] = "http://tsgonline.hp.com/templates/tsghorizontal.js";
$scripts[] = "http://linux.fc.hp.com/includes/javascript/linux.js";
$scripts[] = "http://linux.fc.hp.com/includes/javascript/localleft.js";
$always_scripts = array();
$always_scripts[] = "/webtools/lib/tiptool.js";
athp_top($sa, $cache, $title, $meta, $styles, $scripts, $always_scripts);

showcolors();

html_footer($sa, $tooltip=true);
?>
