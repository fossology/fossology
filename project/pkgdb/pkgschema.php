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

db_connect($user, $pass, $db);
$msg = "";
$farray = show_cols2str("pkgs");

print "<table border=1>";

// print heading
print "<tr>";
foreach (array_keys($farray[0]) as $label) 
    printf("<th>%s</th>", empty($label)? "&nbsp;" : $label);;
print "</tr>";

foreach ($farray as $frow)
{
    print "<tr>";
    foreach ($frow as $field) 
        printf("<td>%s</td>", empty($field)? "&nbsp;" : $field);;
    print "</tr>";
}
print "</table>";

html_footer($sa, $tooltip=true);
?>
