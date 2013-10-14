<?php
/***********************************************************
 Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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
 * \file hist.php
 * \brief Copyright/Email/URL Browser, you can see the Copyright/Email/URL information in this directory
 */

define("TITLE_copyright_hist", _("Copyright/Email/URL Browser"));

class copyright_hist extends FO_Plugin
{
  var $Name       = "copyrighthist";
  var $Title      = TITLE_copyright_hist;
  var $Version    = "1.0";
  var $Dependency = array("browse","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $UpdCache   = 0;

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
        menu_insert("Browse::Copyright/Email/URL",1);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View copyright/email/url histogram");
        menu_insert("Browse::Copyright/Email/URL",10,$URI,$text);
      }
    }
  } // RegisterMenus()


  /**
   * \brief Combine copyright holders by name  \n
   * Input records contain: content and type \n
   * Output records: copyright_count, content, type, hash \n
   * where content has been simplified from
   * the raw records and hash is the md5 of this
   * new content.
   * \return If $hash non zero, only rows with that hash will
   * be returned.
   */
  function GroupHolders(&$rows, $hash)
  {
    /* Step 1: Clean up content, and add hash
     */
    $NumRows = count($rows);
    for($RowIdx = 0; $RowIdx < $NumRows; $RowIdx++)
    {
      if (MassageContent($rows[$RowIdx], $hash))
      unset($rows[$RowIdx]);
      /* debug to compare original with new content
       else
      {
      echo "<br>row $RowIdx: ".htmlentities($rows[$RowIdx]['content']) . "<br>";
      echo "row $RowIdx: ".htmlentities($rows[$RowIdx]['original']) . "<br>";
      }
      */
    }

    /* Step 2: sort the array by the new content */
    usort($rows, 'hist_rowcmp');

    /* Step 3: group content (remove dups, add counts) */
    $NumRows = count($rows);
    for($RowIdx = 1; $RowIdx < $NumRows; $RowIdx++)
    {
      if ($rows[$RowIdx]['content'] == $rows[$RowIdx-1]['content'])
      {
        $rows[$RowIdx]['copyright_count'] = $rows[$RowIdx-1]['copyright_count'] + 1;
        unset($rows[$RowIdx-1]);
      }
    }

    /** sorting */
    $ordercount = '-1';
    $ordercopyright = '-1';

    if (isset($_GET['orderc'])) $ordercount = $_GET['orderc'];
    if (isset($_GET['ordercp'])) $ordercopyright = $_GET['ordercp'];
    // sort by count
    if (1 == $ordercount) usort($rows, 'hist_rowcmp_count_desc');
    else if (0 == $ordercount) usort($rows, 'hist_rowcmp_count_asc'); 
    // sort by copyrigyht statement
    else if (1 == $ordercopyright) usort($rows, 'hist_rowcmp_desc');
    else if (0 == $ordercopyright) usort($rows, 'hist_rowcmp');
    else usort($rows, 'hist_rowcmp_count_desc'); // default as sorting by count desc

    /* note $rows indexes may not be contiguous due to unset in step 3 */
    return $rows;
  }  /* End GroupHolders() */


  /**
   * /return rows to process, and $upload_pk
   * If there are too many rows (see $MaxTreeRecs)
   *  then a text error message is returned, not an array.
   * If the optional $hash is supplied, only rows
   * with that hash will be returned.
   */
  function GetRows($Uploadtree_pk, $Agent_pk, &$upload_pk, $hash=0, $filter)
  {
    global $PG_CONN;

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

    /* Check for too many uploadtree rows to process.
     * This is arbitrarily set to 100000.  The copyright display
     * isn't very useful with more records and this check
     * give the user immediate feedback, as opposed to
     * waiting on a very long query.
     * $MaxTreeRecs / 2 = number of uploadtree entries
     */
    $MaxTreeRecs = 200000;
    if (($rgt - $lft) > $MaxTreeRecs)
    {
      $text = _("Too many rows to display");
      return "<h2>$text</h2>";
    }

    $sql = "";
    if ($filter == "nolics")
    {
      /* find rf_pk for "No_license_found" or "Void" */
      $rf_clause = "";
      $NoLicStr = "No_license_found";
      $VoidLicStr = "Void";
      $sql_lr = "select rf_pk from license_ref where rf_shortname IN ('$NoLicStr', '$VoidLicStr')";
      $result = pg_query($PG_CONN, $sql_lr);
      DBCheckResult($result, $sql_lr, __FILE__, __LINE__);
      if (pg_num_rows($result) > 0)
      {
        $rows = pg_fetch_all($result);
        pg_free_result($result);
        foreach($rows as $row)
        {
          if (!empty($rf_clause)) $rf_clause .= " or ";
          $rf_clause .= " (rf_fk=$row[rf_pk])";
        }

        /* select copyright records that have No_license_found */
        $sql = "SELECT substring(content from 1 for 150) as content, type from copyright, license_file,
                (SELECT distinct(pfile_fk) as pf from uploadtree 
                  where upload_fk=$upload_pk and uploadtree.lft BETWEEN $lft and $rgt) as SS
               where copyright.pfile_fk=license_file.pfile_fk and ($rf_clause) 
                     and copyright.pfile_fk=pf and copyright.agent_fk=$Agent_pk";
      }
    }

    if (empty($sql))
    {
      /* get all the copyright records for this uploadtree.  */
      $sql = "SELECT substring(content from 1 for 150) as content, type from copyright,
              (SELECT distinct(pfile_fk) as PF from uploadtree 
                 where upload_fk=$upload_pk 
                   and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$Agent_pk";
    }
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    if (pg_num_rows($result) == 0)
    {
      $text = _("No results to display.");
      return "<h2>$text</h2>";
    }

    $rows = pg_fetch_all($result);
    pg_free_result($result);

    /* Combine results to attempt to group copyright holders */
    $rows = $this->GroupHolders($rows, $hash);

    return $rows;
  }


  /**
   * \brief Given an $Uploadtree_pk, display: \n
   * (1) The histogram for the directory BY LICENSE. \n
   * (2) The file listing for the directory.
   *
   * \param $Uploadtree_pk
   * \param $Uri
   * \param $filter
   * \param $uploadtree_tablename
   * \param $Agent_pk - agent id
   */
  function ShowUploadHist($Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk)
  {
    global $PG_CONN;

    $VF=""; // return values for file listing
    $VLic=""; // return values for license histogram
    $V=""; // total return value
    $upload_pk = "";
    $VCopyright = '';
    global $Plugins;

    $ModLicView = &$Plugins[plugin_find_id("copyrightview")];

    $rows = $this->GetRows($Uploadtree_pk, $Agent_pk, $upload_pk, 0, $filter);
    if (!is_array($rows)) return $rows;

    $orderBy = array('count', 'copyright');
    static $ordercount = 1;
    static $ordercopyright = 1;
    $order = "";
    /** sorting by count/copyright statement */
    if (isset($_GET['orderBy']) && in_array($_GET['orderBy'], $orderBy)) {
      $order = $_GET['orderBy'];
      if (isset($_GET['orderc'])) $ordercount = $_GET['orderc'];
      if (isset($_GET['ordercp'])) $ordercopyright = $_GET['ordercp'];
      if ('count' == $order && 1 == $ordercount)
      {
        $ordercount = 0;
      }
      else if ('count' == $order && 0 == $ordercount)
      {
        $ordercount = 1;
      }
      else if ('copyright' == $order && 1 == $ordercopyright)
      {
        $ordercopyright = 0;
      }
      else if ('copyright' == $order && 0 == $ordercopyright)
      {
        $ordercopyright = 1;
      }
    }

    /* Write license histogram to $VLic  */
    $CopyrightCount = 0;
    $UniqueCopyrightCount = 0;
    $NoCopyrightFound = 0;
    $VCopyright = "";

    $VCopyright .= "<table border=1 width='100%' id='copyright'>\n";
    $text = _("Count");
    $text1 = _("Files");
    $text2 = _("Copyright Statements");
    $text3 = _("Email");
    $text4 = _("URL");
    $VCopyright .= "<tr><th>";
    $VCopyright .= "<a href=?mod=" . "$this->Name" . Traceback_parm_keep(array("upload", "item", "filter", "agent")) . "&orderBy=count&orderc=$ordercount>$text</a>";
    $VCopyright .= "</th>";
    $VCopyright .= "<th width='10%'>$text1</th>";
    $VCopyright .= "<th>";
    $VCopyright .= "<a href=?mod=" . "$this->Name" . Traceback_parm_keep(array("upload", "item", "filter", "agent")) . "&orderBy=copyright&ordercp=$ordercopyright>$text2</a>";
    $VCopyright .= "</th>";
    $VCopyright .= "</th></tr>\n";

    $EmailCount = 0;
    $UniqueEmailCount = 0;
    $NoEmailFound = 0;
    $VEmail = "<table border=1 width='100%'id='copyrightemail'>\n";
    $VEmail .= "<tr><th width='10%'>$text</th>";
    $VEmail .= "<th width='10%'>$text1</th>";
    $VEmail .= "<th>$text3</th></tr>\n";

    $UrlCount = 0;
    $UniqueUrlCount = 0;
    $NoUrlFound = 0;
    $VUrl = "<table border=1 width='100%' id='copyrighturl'>\n";
    $VUrl .= "<tr><th width='10%'>$text</th>";
    $VUrl .= "<th width='10%'>$text1</th>";
    $VUrl .= "<th>$text4</th></tr>\n";

    if (!is_array($rows))
    $VCopyright .= "<tr><td colspan=3>$rows</td></tr>";
    else
    foreach($rows as $row)
    {
      $hash = $row['hash'];
      if ($row['type'] == 'statement')
      {
        $UniqueCopyrightCount++;
        $CopyrightCount += $row['copyright_count'];
        $VCopyright .= "<tr><td align='right'>$row[copyright_count]</td>";
        $VCopyright .= "<td align='center'><a href='";
        $VCopyright .= Traceback_uri();
        $URLargs = "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $hash . "&type=" . $row['type'];
        if (!empty($filter)) $URLargs .= "&filter=$filter";
        $VCopyright .= $URLargs. "'>Show</a></td>";
        $VCopyright .= "<td align='left'>";

        /* strip out characters we don't want to see
         This is a hack until the agent stops writing these chars to the db.
        */
        $S = $row['content'];
        $S = htmlentities($S);
        $S = str_replace("&Acirc;","",$S);  // comes from utf-8 copyright symbol
        $VCopyright .= $S;

        /* Debugging
         $hex = bin2hex($S);
        $VCopyright .= "<br>$hex" ;
        End Debugging */

        $VCopyright .= "</td>";
        $VCopyright .= "</tr>\n";
      }
      else if ($row['type'] == 'email')
      {
        $UniqueEmailCount++;
        $EmailCount += $row['copyright_count'];
        $VEmail .= "<tr><td align='right'>$row[copyright_count]</td>";
        $VEmail .= "<td align='center'><a href='";
        $VEmail .= Traceback_uri();
        $VEmail .= "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $hash . "&type=" . $row['type'] . "'>Show</a></td>";
        $VEmail .= "<td align='left'>";
        $VEmail .= htmlentities($row['content']);
        $VEmail .= "</td>";
        $VEmail .= "</tr>\n";
      }
      else if ($row['type'] == 'url')
      {
        $UniqueUrlCount++;
        $UrlCount += $row['copyright_count'];
        $VUrl .= "<tr><td align='right'>$row[copyright_count]</td>";
        $VUrl .= "<td align='center'><a href='";
        $VUrl .= Traceback_uri();
        $VUrl .= "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $hash . "&type=" . $row['type'] . "'>Show</a></td>";
        $VUrl .= "<td align='left'>";
        $VUrl .= htmlentities($row['content']);
        $VUrl .= "</td>";
        $VUrl .= "</tr>\n";
      }
    }

    $VCopyright .= "</table>\n";
    $VCopyright .= "<p>\n";
    $text = _("Unique Copyrights");
    $text1 = _("Total Copyrights");
    $VCopyright .= "$text: $UniqueCopyrightCount<br>\n";
    $NetCopyright = $CopyrightCount;
    $VCopyright .= "$text1: $NetCopyright";

    $VEmail .= "</table>\n";
    $VEmail .= "<p>\n";
    $text = _("Unique Emails");
    $text1 = _("Total Emails");
    $VEmail .= "$text: $UniqueEmailCount<br>\n";
    $NetEmail = $EmailCount;
    $VEmail .= "$text1: $NetEmail";

    $VUrl .= "</table>\n";
    $VUrl .= "<p>\n";
    $text = _("Unique URLs");
    $text1 = _("Total URLs");
    $VUrl .= "$text: $UniqueUrlCount<br>\n";
    $NetUrl = $UrlCount;
    $VUrl .= "$text1: $NetUrl";


    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk, $uploadtree_tablename);
    $ChildCount=0;
    $ChildLicCount=0;
    $ChildDirCount=0; /* total number of directory or containers */
    foreach($Children as $C)
    {
      if (Iscontainer($C['ufile_mode'])) {
        $ChildDirCount++;
      }
    }

    $VF .= "<table border=0>";
    foreach($Children as $C)
    {
      if (empty($C)) {
        continue;
      }

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
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk'], $uploadtree_tablename);
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
      if ($IsDir) {
        $VF .= "/";
      };
      if ($HasBold) {
        $VF .= "</b>";
      }
      if ($HasHref) {
        $VF .= "</a>";
      }
      $VF .= "</td><td>";

      if ($LicCount)
      {
        $VF .= "[" . number_format($LicCount,0,"",",") . "&nbsp;";
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
      $sql = "SELECT * FROM uploadtree WHERE uploadtree_pk = '$Uploadtree_pk';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      if (IsDir($row['ufile_mode'])) {
        return;
      }
      $ModLicView = &$Plugins[plugin_find_id("copyrightview")];
      return($ModLicView->Output() );
    }

    /* Combine VF and VLic */
    $text = _("Jump to");
    $text1 = _("Emails");
    $text2 = _("Copyright Statements");
    $text3 = _("URLs");
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td><a name=\"statements\"></a>$text: <a href=\"#emails\">$text1</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VCopyright</td><td valign='top'>$VF</td></tr>\n";
    $V .= "<tr><td><a name=\"emails\"></a>Jump to: <a href=\"#statements\">$text2</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VEmail</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"urls\"></a>Jump To: <a href=\"#statements\">$text2</a> | <a href=\"#emails\">$text1</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VUrl</td><td valign='top'></td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";

    return($V);
  } // ShowUploadHist()


  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }
    $OutBuf="";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $filter = GetParm("filter",PARM_STRING);

    /* check upload permissions */
    $UploadPerm = GetUploadPerm($Upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }

    /* Get uploadtree_tablename */
    $uploadtree_tablename = GetUploadtreeTableName($Upload);

    /* Use Traceback_parm_keep to ensure that all parameters are in order */
    /********  disable cache to see if this is fast enough without it *****
     $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","folder")) . "&show=$Show";
    if ($this->UpdCache != 0)
    {
    $OutBuf .= "";
    $Err = ReportCachePurgeByKey($CacheKey);
    }
    else
    $OutBuf .= ReportCacheGet($CacheKey);
    ***********************************************/

    if (empty($OutBuf) )  // no cache exists
    {
      switch($this->OutputType)
      {
        case "XML":
          break;
        case "HTML":
          $OutBuf .= "\n<script language='javascript'>\n";
          /* function to replace this page specifying a new filter parameter */
          $OutBuf .= "function ChangeFilter(selectObj, upload, item){";
          $OutBuf .= "  var selectidx = selectObj.selectedIndex;";
          $OutBuf .= "  var filter = selectObj.options[selectidx].value;";
          $OutBuf .= '  window.location.assign("?mod=' . $this->Name .'&upload="+upload+"&item="+item +"&filter=" + filter); ';
          $OutBuf .= "}</script>\n";

          $OutBuf .= "<font class='text'>\n";

          /************************/
          /* Show the folder path */
          /************************/
          $OutBuf .= Dir2Browse($this->Name,$Item,NULL,1,"Browse",-1,'','',$uploadtree_tablename) . "<P />\n";
          if (!empty($Upload))
          {
            /** advanced interface allowing user to select dataset (agent version) */
            $Agent_name = "copyright";
            $dataset = "copyright_dataset";
            $arstable = "copyright_ars";
            /** get proper agent_id */
            $Agent_pk = GetParm("agent", PARM_INTEGER);
            if (empty($Agent_pk))
            {
              $Agent_pk = LatestAgentpk($Upload, $arstable);
            }

            if ($Agent_pk == 0)
            {
              $text = _("No data available.  Use Jobs > Agents to schedule a copyright scan.");
              $Msg = "<b>$text</b><p>";
              $OutBuf .= $Msg;
              break;
            }

            $AgentSelect = AgentSelect($Agent_name, $Upload, true, $dataset, $dataset, $Agent_pk,
                "onchange=\"addArsGo('newds', 'copyright_dataset');\"");

            /** change the copyright  result when selecting one version of copyright */
            if (!empty($AgentSelect))
            {
              $action = Traceback_uri() . "?mod=copyrighthist&upload=$Upload&item=$Item";

              $OutBuf .= "<script type='text/javascript'>
                function addArsGo(formid, selectid)
                {
                  var selectobj = document.getElementById(selectid);
                  var Agent_pk = selectobj.options[selectobj.selectedIndex].value;
                  document.getElementById(formid).action='$action'+'&agent='+Agent_pk;
                  document.getElementById(formid).submit();
                  return;
                }
              </script>";

              /* form to select new dataset, show dataset */
              $OutBuf .= "<form action='$action' id='newds' method='POST'>\n";
              $OutBuf .= $AgentSelect;
              $OutBuf .= "</form>";
            }

            $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());

            /* Select list for filters */
            $SelectFilter = "<select name='view_filter' id='view_filter' onchange='ChangeFilter(this,$Upload, $Item)'>";

            $text = _("Show all");
            $Selected = ($filter == 'none') ? "selected" : "";
            $SelectFilter .= "<option $Selected value='none'>$text";

            $text = _("Show files without licenses");
            $Selected = ($filter == 'nolics') ? "selected" : "";
            $SelectFilter .= "<option $Selected value='nolics'>$text";

            $SelectFilter .= "</select>";
            $OutBuf .= $SelectFilter;

            $OutBuf .= $this->ShowUploadHist($Item, $Uri, $filter, $uploadtree_tablename, $Agent_pk);
          }
          $OutBuf .= "</font>\n";
          break;
        case "Text":
          break;
        default:
      }

      /*  Cache Report */
      /********  disable cache to see if this is fast enough without it *****
       $Cached = false;
      ReportCachePut($CacheKey, $OutBuf);
      **************************************************/
    }
    else
    $Cached = true;

    if (!$this->OutputToStdout) {
      return($OutBuf);
    }
    print "$OutBuf";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<small>$text</small>", $Time);

    /********  disable cache to see if this is fast enough without it *****
     $text = _("cached");
    $text1 = _("Update");
    if ($Cached) echo " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
    **************************************************/
    return;
  }

};

$NewPlugin = new copyright_hist;
$NewPlugin->Initialize();

?>
