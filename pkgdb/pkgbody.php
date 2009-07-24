<?php
require_once "lib/db.php";
require_once "pkg.inc.php";


//////////////////////////////////////////////////////////////////////
   ////////   main   ////////
   $selectcols = array("rec_id", "distro_name", "pkg_name", "distro_arch", "pkg_arch",
                         "version_release", "rpm_filename", "license_issue",
                         "source_rpm", "pkg_group", "pkg_summary");

   print "<a name=top></a>";
   printf("<h3>Linux Package Database query</h3>");
   print "This is handy to see what packages are in a distro or to compare distros.";
   if ($msg)
   {
       printf("<p><hr>%s<hr>", $msg);
   }
   print "<p>";

   //printf("<form action=\"$_SERVER[REQUEST_URI]\" method='post'>");
   printf("<form action=\"index.php#results\" method='post'>");

   // print the Filter and Sort dialog boxes
   printfilter();

   if (array_key_exists("filterbtn", $_POST))
   {
       // build where clause to filter results
       $where = where_filter();
       if (strlen($where) > 0) $filterapplied = true;
       $cols = implode(",", $selectcols);
       $sql = "select $cols from pkgs $where order by pkg_name";
       $result = mysql_query($sql)
               or die("Invalid query: $sql<br>".mysql_error());

       $sortobj = reclist($result);
       print "<hr>$sql<hr>";
       printlist($sortobj, $where);  // print the requested records
   }
   print "</form>";
 ?>
