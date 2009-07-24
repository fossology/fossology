<?php
require_once "lib/db.php";
require_once "pkg.inc.php";

function viewpkg($rec_id)
{
   global $pkgcolnames;

   printf("<h3>Package View</h3>");
   print "<p>";

   $row = select_row("pkgs", "where rec_id=$rec_id");

   print "<table border='1' rules=rows >";
   foreach ($pkgcolnames as $akey => $pkgname)
   {
       printf("<tr><td>%s</td>", $pkgname);
       printf("<td>%s</td></tr>", $row[$akey]);
   }
   print "</table>";

   print "<a href=index.php>Select new packages</a>";
}
 ?>
