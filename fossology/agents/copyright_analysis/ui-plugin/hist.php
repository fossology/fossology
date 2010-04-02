<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class copyright_hist extends FO_Plugin
{
  var $Name       = "copyrighthist";
  var $Title      = "Copyright/Email/Url Browser";
  var $Version    = "1.0";
  var $Dependency = array("db","browse","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $UpdCache   = 0;

  /***********************************************************
   Install(): Create and configure database tables
   ***********************************************************/
  // function Install()
  // {
  //   global $DB;
  //   if (empty($DB)) { return(1); } /* No DB */

  //   return(0);
  // } // Install()

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
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
       menu_insert("Browse::Copyright/Email/Url",1);
       menu_insert("Browse::[BREAK]",100);
       //menu_insert("Browse::Clear",101,NULL,NULL,NULL,"<a href='javascript:LicColor(\"\",\"\",\"\",\"\");'>Clear</a>");
      }
      else
      {
       menu_insert("Browse::Copyright/Email/Url",10,$URI,"View copyright/email/url histogram");
      }
    }
  } // RegisterMenus()

  /***********************************************************
   ShowUploadHist(): Given an $Uploadtree_pk, display:
   (1) The histogram for the directory BY LICENSE.
   (2) The file listing for the directory.
   ***********************************************************/
  function ShowUploadHist($Uploadtree_pk,$Uri)
  {
    global $PG_CONN;

    $VF=""; // return values for file listing
    $VLic=""; // return values for license histogram
    $V=""; // total return value
    global $Plugins;
    global $DB;

    $ModLicView = &$Plugins[plugin_find_id("view-copyright")];

    /*******  Get license names and counts  ******/
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM uploadtree 
              WHERE uploadtree_pk = $Uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $Agent_name = "copyright";
    $Agent_desc = "copyright analysis agent";
    if (array_key_exists("agent_pk", $_POST))
      $Agent_pk = $_POST["agent_pk"];
    else
      $Agent_pk = GetAgentKey($Agent_name, $Agent_desc);

    /*  Get the counts for each license under this UploadtreePk*/
    $sql = "SELECT DISTINCT ON (count(content),content, hash, type) content, hash, type, count(content) as copyright_count
              from copyright,
                  (SELECT distinct(pfile_fk) as PF from uploadtree 
                     where upload_fk=$upload_pk 
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$Agent_pk 
              group by content, hash, type 
              order by copyright_count desc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    /* Get agent list */
    $VLic .= "<form action='" . Traceback_uri()."?" . $_SERVER["QUERY_STRING"] . "' method='POST'>\n";

    $AgentSelect = AgentSelect($Agent_name, $upload_pk, "copyright", true, "agent_pk", $Agent_pk);
    $VLic .= $AgentSelect;
    $VLic .= "<input type='submit' value='Go'>";

    /* Write license histogram to $VLic  */
    $CopyrightCount = 0;
    $UniqueCopyrightCount = 0;
    $NoCopyrightFound = 0;
    $VCopyright = "<table border=1 width='100%' id='copyright'>\n";
    $VCopyright .= "<tr><th width='10%'>Count</th>";
    $VCopyright .= "<th width='10%'>Files</th>";
    $VCopyright .= "<th>Copyright Statements</th></tr>\n";

    $EmailCount = 0;
    $UniqueEmailCount = 0;
    $NoEmailFound = 0;
    $VEmail = "<table border=1 width='100%'id='copyrightemail'>\n";
    $VEmail .= "<tr><th width='10%'>Count</th>";
    $VEmail .= "<th width='10%'>Files</th>";
    $VEmail .= "<th>Email</th></tr>\n";

    $UrlCount = 0;
    $UniqueUrlCount = 0;
    $NoUrlFound = 0;
    $VUrl = "<table border=1 width='100%' id='copyrighturl'>\n";
    $VUrl .= "<tr><th width='10%'>Count</th>";
    $VUrl .= "<th width='10%'>Files</th>";
    $VUrl .= "<th>URL</th></tr>\n";

    while ($row = pg_fetch_assoc($result))
    {

        if ($row['content'] == '') {
            continue;
        }

        if ($row['type'] == 'statement') {
            $UniqueCopyrightCount++;
            $CopyrightCount += $row['copyright_count'];
            $VCopyright .= "<tr><td align='right'>$row[copyright_count]</td>";
            $VCopyright .= "<td align='center'><a href='";
            $VCopyright .= Traceback_uri();
            $VCopyright .= "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $row['hash'] . "&type=" . $row['type'] . "'>Show</a></td>";
            $VCopyright .= "<td align='left'>";
            $VCopyright .= $row['content'];
            $VCopyright .= "</td>";
            $VCopyright .= "</tr>\n";
        } else if ($row['type'] == 'email') {
            $UniqueEmailCount++;
            $EmailCount += $row['copyright_count'];
            $VEmail .= "<tr><td align='right'>$row[copyright_count]</td>";
            $VEmail .= "<td align='center'><a href='";
            $VEmail .= Traceback_uri();
            $VEmail .= "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $row['hash'] . "&type=" . $row['type'] . "'>Show</a></td>";
            $VEmail .= "<td align='left'>";
            $VEmail .= $row['content'];
            $VEmail .= "</td>";
            $VEmail .= "</tr>\n";
        } else if ($row['type'] == 'url') {
            $UniqueUrlCount++;
            $UrlCount += $row['copyright_count'];
            $VUrl .= "<tr><td align='right'>$row[copyright_count]</td>";
            $VUrl .= "<td align='center'><a href='";
            $VUrl .= Traceback_uri();
            $VUrl .= "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $row['hash'] . "&type=" . $row['type'] . "'>Show</a></td>";
            $VUrl .= "<td align='left'>";
            $VUrl .= $row['content'];
            $VUrl .= "</td>";
            $VUrl .= "</tr>\n";
        }
    }

    $VCopyright .= "</table>\n";
    $VCopyright .= "<p>\n";
    $VCopyright .= "Unique Copyrights: $UniqueCopyrightCount<br>\n";
    $NetCopyright = $CopyrightCount;
    $VCopyright .= "Total Copyrights: $NetCopyright";

    $VEmail .= "</table>\n";
    $VEmail .= "<p>\n";
    $VEmail .= "Unique Emails: $UniqueEmailCount<br>\n";
    $NetEmail = $EmailCount;
    $VEmail .= "Total Emails: $NetEmail";

    $VUrl .= "</table>\n";
    $VUrl .= "<p>\n";
    $VUrl .= "Unique URLs: $UniqueUrlCount<br>\n";
    $NetUrl = $UrlCount;
    $VUrl .= "Total URLs: $NetUrl";

    pg_free_result($result);


    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = DirGetList($upload_pk,$Uploadtree_pk);
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

      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);

      /* Determine the hyperlink for non-containers to view-license  */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
      {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=view-license&agent=$Agent_pk&upload=$upload_pk&item=$C[uploadtree_pk]";
      }
      else
      {
        $LinkUri = NULL;
      }

      /* Determine link for containers */
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
      /* id of each element is its uploadtree_pk */
      $LicCount=0;

      $VF .= "<tr><td id='$C[uploadtree_pk]' align='left'>";
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
      $Results = $DB->Action("SELECT * FROM uploadtree WHERE uploadtree_pk = '$Item';");
      if (IsDir($Results[0]['ufile_mode'])) { return; }
      $ModLicView = &$Plugins[plugin_find_id("view-license")];
      return($ModLicView->Output() );
    }

    /* Combine VF and VLic */
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td><a name=\"statements\"></a>Jump to: <a href=\"#emails\">Emails</a> | <a href=\"#urls\">URLs</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VCopyright</td><td valign='top'>$VF</td></tr>\n";
    $V .= "<tr><td><a name=\"emails\"></a>Jump to: <a href=\"#statements\">Copyright Statements</a> | <a href=\"#urls\">URLs</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VEmail</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"urls\"></a>Jump To: <a href=\"#statements\">Copyright Statements</a> | <a href=\"#emails\">Emails</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VUrl</td><td valign='top'></td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";

    return($V);
  } // ShowUploadHist()

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
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
    }

    /* Use Traceback_parm_keep to ensure that all parameters are in order */
/********  disable cache to see if this is fast enough without it *****
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","folder")) . "&show=$Show";
    if ($this->UpdCache != 0)
    {
      $V = "";
      $Err = ReportCachePurgeByKey($CacheKey);
    }
    else
      $V = ReportCacheGet($CacheKey);
***********************************************/

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

        if (!empty($Upload))
        {
          $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
          $V .= $this->ShowUploadHist($Item,$Uri);
        }
        $V .= "</font>\n";
/*$V .= "<div id='ajax_waiting'><img src='images/ajax-loader.gif'>Loading...</div>"; */
        break;
      case "Text":
        break;
      default:
      }

      /*  Cache Report */
/********  disable cache to see if this is fast enough without it *****
      $Cached = false;
      ReportCachePut($CacheKey, $V);
**************************************************/
    }
    else
      $Cached = true;

    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    printf( "<small>Elapsed time: %.2f seconds</small>", $Time);

/********  disable cache to see if this is fast enough without it *****
    if ($Cached) echo " <i>cached</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> Update </a>";
**************************************************/
    return;
  }

};

$NewPlugin = new copyright_hist;
$NewPlugin->Initialize();

?>
