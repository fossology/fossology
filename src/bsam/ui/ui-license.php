<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/
/**
 * \file ui_license.php
 * \biref bSAM License Browser
 */

define("TITLE_ui_license", _("bSAM License Browser (deprecated)"));

class ui_license extends FO_Plugin
{
  var $Name       = "license";
  var $Title      = TITLE_ui_license;
  var $Version    = "1.0";
  // var $MenuList= "Jobs::License";
  var $Dependency = array("browse","view-license");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $UpdCache   = 0;

  /**
   * \brief Create and configure database tables
   */
  function Install()
  {
    global $PG_CONN;

    // Stubbed out since pfile_liccount is currently not used.
    /* Update all license counts */
    $TempTable = "counts" . time() . "_" . rand();
    $SQL = "BEGIN;
    SELECT pfile_fk,COUNT(pfile_fk) AS count INTO TEMP $TempTable
      FROM licterm_name
      INNER JOIN pfile ON pfile_liccount IS NULL
      AND pfile_fk = pfile_pk
      GROUP BY pfile_fk ORDER BY pfile_fk;
    UPDATE pfile
      SET pfile_liccount = $TempTable.count
      FROM $TempTable
      WHERE pfile.pfile_pk = $TempTable.pfile_fk
      ;
    DROP TABLE $TempTable;
    COMMIT;
    ";
    //    $DB->Action($SQL);
    return(0);
  } // Install()

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
        menu_insert("Browse::bsam License",-5);
        menu_insert("Browse::[BREAK]",-3);
        $text = _("Clear");
        menu_insert("Browse::Clear",-4,NULL,NULL,NULL,"<a href='javascript:LicColor(\"\",\"\",\"\",\"\");'>$text</a>");
      }
      else
      {
        $text = _("View bsam license histogram");
        menu_insert("Browse::[BREAK]",-3);
        menu_insert("Browse::bsam License",-5,$URI,$text);
      }
    }
  } // RegisterMenus()

  /**
   * \brief This is called before the plugin is used.
   * It should assume that Install() was already run one time
   * (possibly years ago and not during this object's creation).
   *
   * \return true on success, false on failure.
   * A failed initialize is not used by the system.
   * \note  This function must NOT assume that other plugins are installed.
   */
  function Initialize()
  {
    global $_GET;
    if ($this->State != PLUGIN_STATE_INVALID) { return(1); } // don't re-run
    if ($this->Name !== "") // Name must be defined
    {
      global $Plugins;
      $this->State=PLUGIN_STATE_VALID;
      array_push($Plugins,$this);
    }

    /* Remove "updcache" from the GET args and set $this->UpdCache
     * This way all the url's based on the input args won't be
     * polluted with updcache
     */
    if ($_GET['updcache'])
    {
      $this->UpdCache = $_GET['updcache'];
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/","",$_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
    }
    else
    {
      $this->UpdCache = 0;
    }
    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()


  /**
   * \brief Given two elements sort them by name.
   * Callback Used for sorting the histogram.
   */
  function SortName ($a,$b)
  {
    list($A0,$A1,$A2) = explode("\|",$a,3);
    list($B0,$B1,$B2) = explode("\|",$b,3);
    /* Sort by count */
    if ($A0 < $B0) { return(1); }
    if ($A0 > $B0) { return(-1); }
    /* Same count? sort by root name.
       Same root? place real before style before partial. */
    $A0 = str_replace('-partial$',"",$A1);
    if ($A0 != $A1) { $A1 = '-partial'; }
    else
    {
      $A0 = str_replace('-style',"",$A1);
      if ($A0 != $A1) { $A1 = '-style'; }
      else { $A1=''; }
    }
    $B0 = str_replace('-partial$',"",$B1);
    if ($B0 != $B1) { $B1 = '-partial'; }
    else
    {
      $B0 = str_replace('-style',"",$B1);
      if ($B0 != $B1) { $B1 = '-style'; }
      else { $B1=''; }
    }
    if ($A0 != $B0) { return(strcmp($A0,$B0)); }
    if ($A1 == "") { return(-1); }
    if ($B1 == "") { return(1); }
    if ($A1 == "-partial") { return(-1); }
    if ($B1 == "-partial") { return(1); }
    return(strcmp($A1,$B1));
  } // SortName()

  /**
   * \brief Given an Upload and UploadtreePk item, display:
   * - The histogram for the directory BY LICENSE.
   * - The file listing for the directory, with license navigation.
   */
  function ShowUploadHist($Upload,$Item,$Uri)
  {
    /*****
      Get all the licenses PER item (file or directory) under this
      UploadtreePk.
      Save the data 3 ways:
      - Number of licenses PER item.
      - Number of items PER license.
      - Number of items PER license family.
     *****/
    $VF=""; // return values for file listing
    $VH=""; // return values for license histogram
    $V=""; // total return value
    global $Plugins;
    global $PG_CONN;
    $Lics = array(); // license summary for an item in the directory
    $ModLicView = &$Plugins[plugin_find_id("view-license")];

    /* Arrays for storying item->license and license->item mappings */
    $LicGID2Item = array();
    $LicItem2GID = array();
    $MapLic2GID = array(); /* every license should have an ID number */

    /*  Get the counts for each license under this UploadtreePk*/
    LicenseGetAll($Item,$Lics);   // key is license name, value is count
    $LicTotal = $Lics[' Total '];

    /* Ensure that every license is associated with an ID */
    /* MapLic2Gid key is license name, value is a sequence number (GID) */
    $MapNext=0;
    foreach($Lics as $Key => $Val) $MapLic2GID[$Key] = $MapNext++;


    /****************************************/
    /* Get ALL the items under this UploadtreePk */
    $Children = DirGetList($Upload,$Item);
    $ChildCount=0;
    $ChildLicCount=0;
    $ChildDirCount=0; /* total number of directory or containers */
    foreach($Children as $C)
    {
      if (Iscontainer($C['ufile_mode'])) { $ChildDirCount++; }
    }

    $VF .= "<table border=0>";
    foreach($Children as $C)
    {
      if (empty($C)) { continue; }
      /* Store the item information */
      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);

      /* Determine the hyperlinks */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
      {
        $LinkUri = "$Uri&item=" . $C['uploadtree_pk'];
        $LinkUri = preg_replace("/mod=license/","mod=view-license",$LinkUri);
      }
      else
      {
        $LinkUri = NULL;
      }

      if (Iscontainer($C['ufile_mode']))
      {
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk']);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      }
      else
      {
        $LicUri = NULL;
      }


      /* Populate the output ($VF) - file list */

      /* Find number of licenses in child */
      //      if (($ChildDirCount < 20) || (!$IsContainer))
      //        { $LicCount = LicenseCount($C['uploadtree_pk']); }
      //      else { $LicCount=0; }
      $LicCount=0;

      $VF .= '<tr><td id="Lic-' . $LicCount . '" align="left">';
      $HasHref=0;
      $HasBold=0;
      if ($IsContainer)
      {
        $VF .= "<a href='$LicUri'>"; $HasHref=1;
        $VF .= "<b>"; $HasBold=1;
      }
      else if (!empty($LinkUri)) //  && ($LicCount > 0))
      {
        $VF .= "<a href='$LinkUri'>"; $HasHref=1;
      }
      $VF .= $C['ufile_name'];
      if ($IsDir) { $VF .= "/"; };
      if ($HasBold) { $VF .= "</b>"; }
      if ($HasHref) { $VF .= "</a>"; }
      $VF .= "</td><td>";
      if ($LicCount)
      {
        $VF .= "[" . number_format($LicCount,0,"",",") . "&nbsp;";
        //$VF .= "<a href=\"javascript:LicColor('Lic-$ChildCount','LicGroup-','" . trim($LicItem2GID[$ChildCount]) . "','lightgreen');\">";
        $VF .= "license" . ($LicCount == 1 ? "" : "s");
        $VF .= "</a>";
        $VF .= "]";
        $ChildLicCount += $LicCount;
      }
      $VF .= "</td>";
      $VF .= "</tr>\n";

      $ChildCount++;
    }
    $VF .= "</table>\n";
    // print "ChildCount=$ChildCount  ChildLicCount=$ChildLicCount\n";

    /***************************************
Problem: $ChildCount can be zero!
This happens if you have a container that does not
unpack to a directory.  For example:
file.gz extracts to archive.txt that contains a license.
Same problem seen with .pdf and .Z files.
Solution: if $ChildCount == 0, then just view the license!

$ChildCount can also be zero if the directory is empty.
     ***************************************/
    if ($ChildCount == 0)
    {
      $sql = "SELECT * FROM uploadtree WHERE uploadtree_pk = '$Item';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      if (IsDir($row['ufile_mode'])) { return; }
      $ModLicView = &$Plugins[plugin_find_id("view-license")];
      return($ModLicView->Output() );
    }

    /****************************************/
    /* List the licenses */
    $VH .= "<table border=1 width='100%'>\n";
    $SFbL = plugin_find_id("search_file_by_license");
    $text = _("Count");
    $VH .= "<tr><th width='10%'>$text</th>";
    $text = _("Files");
    if ($SFbL >= 0) { $VH .= "<th width='10%'>$text</th>"; }
    $text = _("License");
    $VH .= "<th>$text</th>\n";

    /* krsort + arsort = consistent sorting order */
    arsort($Lics);
    /* Redo the sorting */
    $SortOrder=array();
    foreach($Lics as $Key => $Val)
    {
      if (empty($Val)) { continue; }
      $SortOrder[] = $Val . "|" . str_replace("'","",$Key) . "|" . $Key;
    }
    usort($SortOrder,array($this,"SortName"));
    $LicsTotal = array();
    foreach($SortOrder as $Key => $Val)
    {
      if (empty($Val)) { continue; }
      list($x,$y,$z) = explode("\|",$Val,3);
      $LicsTotal[$z]=$x;
    }

    $Total=0;
    foreach($Lics as $Key => $Val)
    {
      if ($Key != ' Total ')
      {
        $GID = $MapLic2GID[$Key];
        $VH .= "<tr><td align='right'>$Val</td>";
        $Total += $Val;
        if ($SFbL >= 0)
        {
          $VH .= "<td align='center'><a href='";
          $VH .= Traceback_uri();
          $text = _("Show");
          $VH .= "?mod=search_file_by_license&item=$Item&lic=" . urlencode($Key) . "'>$text</a></td>";
        }
        $VH .= "<td id='LicGroup-$GID'>";
        $Uri = Traceback_uri() . "?mod=license_listing&item=$Item&lic=$GID";
        // $VH .= "<a href=\"javascript:LicColor('LicGroup-$GID','Lic-','" . trim($LicGID2Item[$GID]) . "','yellow'); ";
        // $VH .= "\">";
        $VH .= htmlentities($Key);
        $VH .= "</a>";
        $VH .= "</td></tr>\n";
      }
    }
    $VH .= "</table>\n";
    $VH .= "<br>\n";
    $text = _("Total licenses");
    $VH .= "$text: $Total\n";

    /****************************************/
    /* Licenses use Javascript to highlight */
    $VJ = ""; // return values for the javascript
    $VJ .= "<script language='javascript'>\n";
    $VJ .= "<!--\n";
    $VJ .= "var LastSelf='';\n";
    $VJ .= "var LastPrefix='';\n";
    $VJ .= "var LastListing='';\n";
    $VJ .= "function LicColor(Self,Prefix,Listing,color)\n";
    $VJ .= "{\n";
    $VJ .= "if (LastSelf!='')\n";
    $VJ .= "  { document.getElementById(LastSelf).style.backgroundColor='white'; }\n";
    $VJ .= "LastSelf = Self;\n";
    $VJ .= "if (LastPrefix!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  List = LastListing.split(' ');\n";
    $VJ .= "  for(var i in List)\n";
    $VJ .= "    {\n";
    $VJ .= "    document.getElementById(LastPrefix + List[i]).style.backgroundColor='white';\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";
    $VJ .= "LastPrefix = Prefix;\n";
    $VJ .= "LastListing = Listing;\n";
    $VJ .= "if (Self!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  document.getElementById(Self).style.backgroundColor=color;\n";
    $VJ .= "  }\n";
    $VJ .= "if (Listing!='')\n";
    $VJ .= "  {\n";
    $VJ .= "  List = Listing.split(' ');\n";
    $VJ .= "  for(var i in List)\n";
    $VJ .= "    {\n";
    $VJ .= "    document.getElementById(Prefix + List[i]).style.backgroundColor=color;\n";
    $VJ .= "    }\n";
    $VJ .= "  }\n";
    $VJ .= "}\n";
    $VJ .= "// -->\n";
    $VJ .= "</script>\n";

    /* Combine VF and VH */
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td valign='top' width='50%'>$VH</td><td valign='top'>$VF</td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";
    $V .= $VJ;
    return($V);
  } // ShowUploadHist()

  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);

    switch(GetParm("show",PARM_STRING))
    {
      case 'detail':
        $Show='detail';
        break;
      case 'summary':
      default:
        $Show='summary';
        break;
    }

    /* Use Traceback_parm_keep to ensure that all parameters are in order */
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","folder")) . "&show=$Show";
    if ($this->UpdCache != 0)
    {
      $V = "";
      $Err = ReportCachePurgeByKey($CacheKey);
    }
    else
      $V = ReportCacheGet($CacheKey);

    if (empty($V) )  // no cache exists
    {
      switch($this->OutputType)
      {
        case "XML":
          break;
        case "HTML":
          $V .= "<font class='text'>\n";

        /************************/
        /* Show the folder path */
        /************************/
        $V .= Dir2Browse($this->Name,$Item,NULL,1,"Browse") . "<P />\n";

        /******************************/
        /* Get the folder description */
        /******************************/
        if (!empty($Folder))
        {
          // $V .= $this->ShowFolder($Folder);
        }
        if (!empty($Upload))
        {
          $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
          $V .= $this->ShowUploadHist($Upload,$Item,$Uri);
        }
        $V .= "</font>\n";
        break;
        case "Text":
          break;
        default:
        break;
      }

      $Cached = false;
      /*  Cache Report if this took longer than 1 seconds */
      if ($Time > 1) ReportCachePut($CacheKey, $V);

    }
    else
      $Cached = true;

    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<small>$text</small>", $Time);
    $text = _("cached");
    $text1 = _("Update");
    if ($Cached) echo " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
    return;
  }

};
$NewPlugin = new ui_browse_license;
$NewPlugin->Initialize();

?>
