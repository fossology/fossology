<?php
require_once "lib/athp.php";
require_once "lib/db.php";
require_once "pkgconstants.php";
require_once "pkg.inc.php";

// 
function getcolvalsandcount($table, $colname)
{
    $newarray = array();
    $sql = "select $colname, count(*) from $table group by $colname order by $colname";
    $result = mysql_query($sql)
               or die("getcolvals($sql) Invalid query: ".mysql_error());
    while ($row = mysql_fetch_assoc($result)) $newarray[] = $row;
    mysql_free_result($result);
    return $newarray;
}


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
$base = "";

$fvalarray = getcolvalsandcount("pkgs", "pkg_group");

print "<h3>Package Group List</h3>";
printf("There are %d package groups.", count($fvalarray));

print "<table border=1>";

// print heading
print "<tr>";
print "<th>Package Group Name</th>";
print "<th>Package Group Count</th>";
print "</tr>";

$recno = 0;
foreach ($fvalarray as $gnamearray)
{
    $gname = $gnamearray["pkg_group"];
    $gcount = $gnamearray["count(*)"];
    $exp = explode("/", $gname);
    $recbase = $exp[0];
//print "<tr><td>recbase: $recbase</td><td>$gcount</td></tr>";
    if ($recbase != $base)
    {
        $color = $pkgarch_colorlist[$recno % count($pkgarch_colorlist)];
        $base = $recbase;
    }
    print "<tr>";
    printf("<td bgcolor='$color'>%s</td><td>%s</td>", 
           $gname, $gcount);
    print "</tr>";
    $recno++;
}
print "</table>";

html_footer($sa, $tooltip=true);
?>
