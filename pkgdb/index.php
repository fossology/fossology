<?php
require_once "lib/db.php";
require_once "pkgconstants.php";

$sa = 'n';
$cache = "n";
$title = "OSLO Package Database";
$meta = array();
$meta["title"] = $title;
$meta["keywords"] = "package, database, linux, OSLO";
$styles = array();
$scripts = array();
$always_scripts = array();
$always_scripts[] = "/webtools/lib/tiptool.js";
athp_top($sa, $cache, $title, $meta, $styles, $scripts, $always_scripts);

db_connect($user, $pass, $db);
$msg = "";
require "pkgbody.php";

html_footer($sa, $tooltip=true);
?>
