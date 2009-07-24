<?php
require_once "lib/selectlist.php";

// Default sort order
$default_sort_order = array(
                      array("distro_name", "ASC"),
                      array("pkg_name", "ASC"),
                      array("pkg_arch", "ASC")
                     );
$sort_order = array();

//
function subwhere_filter($val_array, $keyname, $keyisval=false, $op="or")
{
    $subwhere = "";
    $init = false;

    if ($keyisval)
    {
        foreach (array_keys($val_array) as $val)
           build_where($init, $subwhere, "($keyname=\"$val\")", $op);
    }
    else
    {
        foreach ($val_array as $val)
           build_where($init, $subwhere, "($keyname=\"$val\")", $op);
    }
    // remove leading "where "
    $swhere = substr($subwhere, 6);
    
    return $swhere;
}

//////////////////////////////////////////////////////////////////////
// create sql Where clause for from the filter
// Returns (like): "where card_id=23 and ..."
// Note: the caller needs to test for a returned string with a lenght of 
//       zero.  A zero length means that there is no filter criteria.
//       A length of 1 (a space) simply means there is a blank Where clause
//       so all records will be returned.
function where_filter()
{
//print "<hr>filter:<pre>";
//print_r($_REQUEST["filter"]);
//print "<hr></pre>";
  // build where clause to filter results
   $init = false;
   $where = "";
                                                                                
   // was Show All button pressed?
   if (!empty($_REQUEST["showall"]))
   {
      $where .= " ";
   }
   else
   {
      if (!empty($_REQUEST["filter"]))
      {
          $filter = $_REQUEST["filter"];
          if (!empty($filter["whereclause"])) 
               build_where($init, $where, '('.stripslashes($filter["whereclause"]).')');

          foreach ($filter as $key => $val)
          {
              if ($key == "whereclause") continue;
              if ($key == "pkg_diff") continue;
              if ($key == "distro_name")
              {
                  $swhere = subwhere_filter($val, $key, $keyisval=false, $op="or");
                  build_where($init, $where, '('.$swhere.')');
              }
              else
              if ($key == "distro_arch")
              {
                  $swhere = subwhere_filter($val, $key, $keyisval=true, $op="or");
                  build_where($init, $where, '('.$swhere.')');
              }
              else
              if ($key == "pkg_arch")
              {
                  $swhere = subwhere_filter($val, $key, $keyisval=true, $op="or");
                  build_where($init, $where, '('.$swhere.')');
              }
              else
              if ($val) build_where($init, $where, $key ." like \"%$val%\"");
          }
      }
   }
   return $where;
}


//////////////////////////////////////////////////////////////////////
// layout print filter and sort dialogs
function printfilter()
{
   print "<table>";  // master layout
   print "<tr><td valign=top>";
   printfilterdialog();
   print "</td><td valign=top>";
//   printsortdialog();
   print "</td></tr>";
   print "</table>";  // master layout
//   print "<a href=#end>Jump to bottom</a>";
}


//////////////////////////////////////////////////////////////////////
// return an array of table column attributes
// Each array element is an assoc array with keys that are the result column
// headings: "Field", "Type", "Null", "Key", "Default", "Extra"
// this belongs in db.php
function show_cols2str($table)
{
   $farray = array();
   $result = mysql_query("show columns from $table")
               or die("show_cols() Invalid query: ".mysql_error());
   while ($row = mysql_fetch_assoc($result)) $farray[] = $row;
   mysql_free_result($result);

   return $farray;
}


//////////////////////////////////////////////////////////////////////
// print the filter dialog
function printfilterdialog()
{
   global $pkgcolnames;

   // there can be lots of configs, so allow user to filter them
   $color = "#FFBBBB";

   print "<div style=\"background-color: $color\">";
   print "<table>";  // filter

   print "<tr><th align='center' colspan=3><h3>Selection filter</h3></th></tr>";
   print "<tr><th align='left' colspan=3>Text input is used for case insensitive substring searches.  You can use '%' to match any 0-n characters and  '_' (underscore) to match exactly one character.<br>Don't use logical operators (and, or, etc) except in 'Add to where clause'.<br>In multiple choice, selecting none is the same as selecting all the choices.<hr></th></tr>";
   $vals = array();
   if (array_key_exists("filter", $_REQUEST))
   {
       $requestfilter = $_REQUEST["filter"];
//print "<hr>requestfilter:<pre>";
//print_r($requestfilter);
//print "</pre><hr>";
       foreach (array_keys($pkgcolnames) as $colname) 
       {
           if (($colname == "distro_name") or ($colname == "distro_arch")
               or ($colname == "pkg_arch")) continue;

           $vals[$colname] = valorblank($colname, $requestfilter);
       }
       $whereclause = $requestfilter["whereclause"];

       if (array_key_exists("distro_name", $requestfilter))
       {
           $distro_names = $requestfilter["distro_name"];
           foreach ($distro_names as $distroname) $vals['distro_name'][$distroname] = true;
       }
       else
           $vals["distro_name"] = array();

       if (array_key_exists("distro_arch", $requestfilter))
       {
           $distro_archs = $requestfilter["distro_arch"];
           foreach (array_keys($distro_archs) as $distroarch) $vals['distro_arch'][$distroarch] = true;
       }
       else
           $vals["distro_arch"] = array();

       if (array_key_exists("pkg_arch", $requestfilter))
       {
           $pkg_archs = $requestfilter["pkg_arch"];
           foreach (array_keys($pkg_archs) as $pkgarch) $vals['pkg_arch'][$pkgarch] = true;
       }
       else
           $vals["pkg_arch"] = array();
   }
   else
   {
       foreach (array_keys($pkgcolnames) as $colname) 
       {
           if (($colname == "distro_name") or ($colname == "distro_arch")
               or ($colname == "pkg_arch"))
               $vals[$colname] = array();
           else
               $vals[$colname] = "";
       }
       $whereclause = "";
   }

   // Filter: Distro name
   $topdistro = array("where distro_name like 'rhel%'", 
                      "where distro_name like 'sles%'",
                      "where distro_name not like 'sles%' and distro_name not like 'rhel%'"
                     );
   $fname = "distro_name";
   print "<tr><td valign='top'><b>$pkgcolnames[$fname]</b><br>Use the ctrl key for multiple selections or click and drag</td><td colspan=2>";
   foreach ($topdistro as $distrowhere)
   {
       $distro_names = getcolvals("pkgs", $fname, $distrowhere);
//       $distro_count = count($distro_names);
       $checked_array = $vals[$fname];
       $iname = "filter[distro_name][]";
       $useval = true;
       if (count($distro_names))
          print_mselect_array($distro_names, $iname, $useval, $checked_array, $blankval=false, $cols=5);
   }
   print "</td></tr>";

   // Filter: Distro Arch
   $fname = "distro_arch";
   $fvals = getcolvals("pkgs", $fname);
   print "<tr><td valign='top'><b>$pkgcolnames[$fname]</b></td><td colspan=2 valign='top'>";
   $max_cols = 6;
   $checked_array = $vals[$fname];
   $iname = "filter[$fname]";
   print_checkradio_table($fvals, $checked_array, $max_cols, "checkbox", $iname);
   print "</td></tr>";

   // Filter: Package name
   $fname = "pkg_name";
   print "<tr><td valign='top' ><b>$pkgcolnames[$fname]</b></td><td valign='top' >";
   printf("<input type=text name=\"filter[$fname]\" value='%s' size='25'>",
           htmlentities($vals[$fname]), ENT_QUOTES);
   print "</td><td valign='top' >Remember these are substring searches, so you only need a snippet.";
   print "</td></tr>";

   // Filter: Package Arch
   $fname = "pkg_arch";
   $fvals = getcolvals("pkgs", $fname);
   print "<tr><td valign='top'><b>$pkgcolnames[$fname]</b></td><td colspan=2 valign='top'>";
   $max_cols = 10;
   $checked_array = $vals[$fname];
   $iname = "filter[$fname]";
   print_checkradio_table($fvals, $checked_array, $max_cols, "checkbox", $iname);
   print "</td></tr>";

   // Filter: version release
   $fname = "version_release";
   print "<tr><td valign='top' ><b>$pkgcolnames[$fname]</b></td><td valign='top' >";
   printf("<input type=text name=\"filter[$fname]\" value='%s' size='25'>",
           htmlentities($vals[$fname]), ENT_QUOTES);
   print "</td><td valign='top' >&nbsp;";
   print "</td></tr>";

   // Filter: pkg group
   $fname = "pkg_group";
   print "<tr><td valign='top' ><b>$pkgcolnames[$fname]</b></td><td valign='top' >";
   printf("<input type=text name=\"filter[$fname]\" value='%s' size='25'>",
           htmlentities($vals[$fname]), ENT_QUOTES);
   print "</td><td valign='top' >There are almost 200 package groups and RH and Novell use different categories.  <a href=pkggroups.php target=blank> Click Here </a> to see the package groups in a new window.";
   print "</td></tr>";

   // Filter: Package Description
   $fname = "pkg_description";
   print "<tr><td valign='top' ><b>$pkgcolnames[$fname]</b></td><td valign='top' >";
   printf("<input type=text name=\"filter[$fname]\" value='%s' size='25'>",
           htmlentities($vals[$fname]), ENT_QUOTES);
   print "</td><td valign='top' >Search long package description";
   print "</td></tr>";

   // Filter: where clause
   print "<tr><td valign='top'><b>Add to where clause</b><br>e.g. <i>version_release &gt; '1.0' and version_release &lt; 5</i><br>or <i>distro_arch != pkg_arch</i></td><td colspan=2 valign='top'>";
   print "<textarea name=\"filter[whereclause]\" rows='5' cols='60'>";
   printf("%s</textarea>", stripslashes(htmlentities($whereclause)), ENT_QUOTES);
   print "</td></tr>";

   // Filter: pkd diff
   $diff_options = array("All", "different", "the same");
   $fname = "pkg_diff";
   print "<tr><td valign='top' ><b>Display packages</b></td><td valign='top' >\n";
   print_select_array($diff_options, "filter[$fname]", true, $vals[$fname]);
   print "</td><td valign='top' >Show <b>all</b> the packages, or only packages that have <b>different</b> versions between distos, or only packages that have <b>the same</b> verions between distros.";
   print "</td></tr>";

   print "<tr><td colspan=3>&nbsp;</td></tr>";

   print "<tr><td colspan=3 align=middle>";
   print "<input type='submit' name='filterbtn' value='Get Packages'>";
   print "&nbsp;&nbsp;&nbsp;&nbsp;";
   print "<a href='index.php'>Top</a>";
   print "&nbsp;&nbsp;&nbsp;&nbsp;";
   print "<a href='pkgschema.php' target='blank'>Table Schema</a>";
   print "</td></tr>";
   
   print "</table></div>";  // filter

}


//////////////////////////////////////////////////////////////////////
// set sort_order 
// Inputs are sort[] and default_sort_order
// Output is an initialized global $sort_order 
function init_sort_order()
{
    global $default_sort_order;
    global $sort_order;

    // check if all sort keys are blank.  If so, use default
    $nokeys = true;
    if (!empty($_REQUEST["sort"]))
    {
        foreach ($_REQUEST["sort"] as $sortkey)
            if (!empty($sortkey[0])) {$nokeys = false; break;}
    }

    if ($nokeys)
        $sort_order = $default_sort_order;
    else
        $sort_order = $_REQUEST["sort"];
}


//////////////////////////////////////////////////////////////////////
// print the sort dialog
function printsortdialog()
{
   global $sort_order;

   $filterprefix = "";
   $color = "#B8E2EF";

   print "<div style=\"background-color: $color\">";
   print "<table>";  
   print "<tr><th colspan=3>Sort:</th></tr>";

   $spaceover = 0;
   $blankval = true;
   $valarray = array("box_codename"=>"Name", "card_name"=>"Card Name", "distro_name"=>"Distro Name", "sys_fw_name"=>"Firmware Name", "card_fw_rev"=>"Firmware Rev", "iochipset"=>"I/O Subsystem", "core_io"=>"Core I/O", "D1"=>"OD1", "boot_support"=>"Boot Support", "max_cards"=>"Max. Cards", "released_date"=>"Release Date");
   $ascdesc = array("ASC"=>"Ascending", "DESC"=>"Descending");
   init_sort_order();

   for ($i=0; $i<5; $i++)
   {
       $orderarray = $sort_order[$i];
       $label = "Sort key ".$i;
       $name  = "sort[".$i."][0]";
       print "<tr><td>$label</td><td>";
       print_select_array($valarray, $name, false,
                          $orderarray[0], $blankval);
   
       print "</td><td>";
       $name  = "sort[".$i."][1]";
       print_select_array($ascdesc, $name, false,
                          $orderarray[1], false);
       print "</td></tr>";
   }

   print "<tr><td colspan=3 align=middle>";
   print "<input type='submit' name='sortbtn' value='Sort'>";
//   print "&nbsp;&nbsp;&nbsp;";
//   print "<input type='reset' name='clearbtn' value='Clear'>";
   print "</td></tr>";
//   print "<tr><td colspan=2 align=left>";
//   print "Note: you can sort on a single key by clicking on the column headings";
//   print "</td></tr>";
   print "</table></div>";

}


////////////////////////////////////////////////////////////
// function to create an array of the supported I/O records
// return a sorted object (the list)
// key is pkg_name, pkg_arch, value is an array or row arrays
function reclist($result)
{
   //$sortobj = new mdasort;
   //$sortobj->aSortkeys = $sort_order;
   $sortobj = array();

   // store all the records in an array of records
   while ($row = mysql_fetch_assoc($result))
   {
//      $sortobj->aData[] = $row;
      $key = $row["pkg_name"].",".$row["pkg_arch"];
      $sortobj[$key][] = $row;
   }
   mysql_free_result($result);

//   $sortobj->sort();
   return $sortobj;
}


////////////////////////////////////////////////////////////
// show colors
function showcolors()
{
   global $pkgarch_colorlist;

   print "<table border=1>";
   foreach ($pkgarch_colorlist as $color)
   {
       print "<tr><td bgcolor='$color'>$color</td></tr>";
   }
   print "</border>";
}

////////////////////////////////////////////////////////////
// function to check if all the packages in the row are the same rev
// return true if all the packages are the same rev, else false
function pkg_rowsame($colhead_array, $pkg_row)
{
    // if the number of entries is different, the rows must be different
    if (count($colhead_array) != count($pkg_row)) return false;

    $first = true;
    foreach ($pkg_row as $pkg_row) 
    {
        if ($first)
        {
            $ver = $pkg_row["version_release"];
            $first = false;
            continue;
        }

        if ($pkg_row["version_release"] != $ver) return false;
    }
    return true;
}

////////////////////////////////////////////////////////////
// function to print the package records
// Each sortobj data row is an assoc array of the pkgs row
function printlist($sortobj, $where)
{
   global $pkgcolnames;
   global $pkgarch_colorlist;

   print "<a name='results'><p></a>";

   $rowcount = count($sortobj);
   if ($rowcount == 0)
   {
       print "Zero packages found.";
       return;
   }

   // extra filter[] parms
   $filter = $_REQUEST["filter"];
   $pkg_diff = $filter["pkg_diff"];  // All, Different, Same

   // get the column headings
   $sql = "select distro_name, distro_arch from pkgs $where group by distro_name, distro_arch order by distro_name, distro_arch";
   $result = mysql_query($sql)
               or die("printlist($where) Invalid query: ".mysql_error());
   $colhead_array = array();
   while ($row = mysql_fetch_assoc($result)) $colhead_array[] = $row;
   mysql_free_result($result);

   // get the used pkg archs so that colors can be assigned
   $sql = "select pkg_arch from pkgs $where group by pkg_arch";
   $result = mysql_query($sql)
               or die("pkg arch list($where) Invalid query: ".mysql_error());
   $pkgarch_colors = array();
   $i = 0;
   while ($row = mysql_fetch_assoc($result)) 
   {
       $pkgarch_colors[$row["pkg_arch"]] = $pkgarch_colorlist[$i % count($pkgarch_colorlist)];
       $i++;
   }
   mysql_free_result($result);

   print "<table border='1' cellpadding='5' rules=rows frame=box>";
   printf("<tr>");
   $pkgmsg = "$rowcount packages";
   printf("<th align=center colspan=2>%s</th>", $pkgmsg);
   foreach ($colhead_array as $colhead) printheading($colhead["distro_name"], "center");
   printf("</tr>");

   printf("<tr>");
   printheading($pkgcolnames["pkg_name"], "left");
   printheading($pkgcolnames["pkg_arch"], "center");
   foreach ($colhead_array as $colhead) printheading($colhead["distro_arch"], "center");
   printf("</tr>");

//   $filsort = "";
//   array2urlparm($_REQUEST["filter"], "filter", $filsort);
//   array2urlparm($_REQUEST["sort"], "sort", $filsort);

   // populate the table from the sorted arrays
   //foreach ($sortobj->aData as $data_row)
   $print_total = 0;
   foreach ($sortobj as $pkg_row)
   {
      if ($pkg_diff != "All")
      {
          $rowsame = pkg_rowsame($colhead_array, $pkg_row);
          if (($pkg_diff == "the same") and !$rowsame) continue;
          if (($pkg_diff == "different") and $rowsame) continue;
      }

      $print_total++;
      $rowcolor = $pkgarch_colors[$pkg_row[0]["pkg_arch"]];
      print "<tr bgcolor='$rowcolor'>";
      printf("<td valign='top' >%s</td>", $pkg_row[0]["pkg_name"]);
      printf("<td valign='top' align='center'>%s</td>", $pkg_row[0]["pkg_arch"]);

      foreach ($colhead_array as $colhead)
      {
          print "<td align='center' valign='top' >";
          $first = true;
          // for this col heading (distro and distro arch), print all
          // the recs for this pkg and pkg version_release
          foreach ($pkg_row as $data_row) 
          {
              if (($data_row["distro_arch"] == $colhead["distro_arch"])
                 and ($data_row["distro_name"] == $colhead["distro_name"]))
              {
                  if (!$first) print "<br>";
                  $first = false;
                  $tiptxt = "";
                  $tiptxt .= "Package: $data_row[pkg_name]<br>";
                  $tiptxt .= "Package arch: $data_row[pkg_arch]<br>";
                  $tiptxt .= "Package ver: $data_row[version_release]<br>";
                  $tiptxt .= "Distro: $data_row[distro_name]<br>";
                  $tiptxt .= "Distro arch: $data_row[distro_arch]<br>";
                  $summary = htmlentities($data_row["pkg_summary"], ENT_QUOTES);
                  $tiptxt .= "Summary: $summary<br>";
//                  $lic = htmlentities($data_row["license_issue"]);
//                  $tiptxt .= "License issue: $lic<br>";
//                  $tiptxt .= "Click for details.";
                  $tip = "onMouseOver=\"doTip(1, '', '$tiptxt')\"; onMouseOut=\"hideTip();\"";
                  printf("<a href='pkgview.php?rec_id=$data_row[rec_id]' %s>%s</a>", 
                         $tip, $data_row["version_release"]);
              }
          }
          if ($first) print "&nbsp;";
          print "</td>";
      }
      print "</tr>";
   }
   print "</table>";

   if ($pkg_diff == "All")
       print "All $print_total packages printed.";
   else
       print "$print_total packages are $pkg_diff.";

   print "<p>";
}


////////////////////////////////////////////////////////////
// function to print the supported I/O records as
// comma seperated values
function printcsv($sortobj)
{
   global $button_edit;
   global $userperm, $write_access;

   // populate the table from the sorted arrays
   $cols = array("box_codename", "card_name", "distro_name", 
                 "sys_fw_name", "card_fw_name", "card_fw_rev",
                 "iochipset", "core_io", "D1", "boot_support",
                 "max_cards", "released_date");

   foreach ($sortobj->aData as $data_row)
   {
     $comma = "";
     foreach ($cols as $colname)
     {
         printf("%s%s", $comma, $data_row[$colname]);
         $comma = ',';
     }
     print "<br>\n";
   }
}

//////////////////////////////////////////////////////////////////////
// print column headings
function printheading($col_name, $halign)
{
    printf("<th align=$halign><b>%s</b></th>", $col_name);
}
                                                                                
?>
