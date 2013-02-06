<?php
/***********************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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
 * \file list.php
 * \brief This plugin is used to:
 * List files for a given copyright statement/email/url in a given
 * uploadtree.
 */

define("TITLE_copyright_list", _("List Files for Copyright/Email/URL"));

class copyright_list extends FO_Plugin
{
  var $Name       = "copyrightlist";
  var $Title      = TITLE_copyright_list;
  var $Version    = "1.0";
  var $Dependency = array("copyrighthist");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    // micro-menu
    $agent_pk = GetParm("agent",PARM_INTEGER);
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $hash = GetParm("hash",PARM_RAW);
    $type = GetParm("type",PARM_RAW);
    $Page = GetParm("page",PARM_INTEGER);
    $Excl = GetParm("excl",PARM_RAW);
    $filter = GetParm("filter",PARM_RAW);

    $URL = $this->Name . "&agent=$agent_pk&item=$uploadtree_pk&hash=$hash&type=$type&page=-1";
    if (!empty($Excl)) $URL .= "&excl=$Excl";
    $text = _("Show All Files");
    menu_insert($this->Name."::Show All",0, $URL, $text);

  } // RegisterMenus()


  /**
   * \return return rows to process, and $upload_pk
   * If there are too many rows (see $MaxTreeRecs)
   * then a text error message is returned, not an array.
   */
  function GetRows($Uploadtree_pk, $Agent_pk, &$upload_pk )
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

    /* Check for too many uploadtree recs to display.
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

    /* get all the copyright records for this uploadtree.  */
    $sql = "SELECT content, type, uploadtree_pk, ufile_name, PF
              from copyright,
              (SELECT uploadtree_pk, pfile_fk as PF, ufile_name from uploadtree 
                 where upload_fk=$upload_pk 
                   and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$Agent_pk order by uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    if (pg_num_rows($result) == 0)
    {
      return _("No results to display.");
    }

    $rows = pg_fetch_all($result);
    pg_free_result($result);

    return $rows;
  }


  /**
   * \brief Remove unwanted rows by hash and type and
   * exclusions and filter
   * \param $NumRows - the number of instances.
   * \return new array and $NumRows
   */
  function GetRequestedRows($rows, $hash, $type, $excl, &$NumRows, $filter)
  {
    global $PG_CONN;

    $NumRows = count($rows);
    $prev = 0;
    $ExclArray = explode(":", $excl);

    /* filter will need to know the rf_pk of "No_license_found" or "Void" */
    if (!empty($filter))
    {
      $NoLicStr = "No_license_found";
      $VoidLicStr = "Void";
      $rf_clause = "";
      $sql = "select rf_pk from license_ref where rf_shortname IN  ('$NoLicStr', '$VoidLicStr')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) > 0)
      {
        $rf_rows = pg_fetch_all($result);
        foreach($rf_rows as $row) 
        {
          if (!empty($rf_clause)) $rf_clause .= " or ";
          $rf_clause .= " rf_fk=$row[rf_pk]";
        }
      }
      pg_free_result($result);
    }

    for($RowIdx = 0; $RowIdx < $NumRows; $RowIdx++)
    {
      $row = $rows[$RowIdx];
      /* Remove undesired types */
      if ($rows[$RowIdx]['type'] != $type)
      {
        unset($rows[$RowIdx]);
        continue;
      }

      /* remove excluded files */
      if ($excl)
      {
        $FileExt = GetFileExt($rows[$RowIdx]['ufile_name']);
        if (in_array($FileExt, $ExclArray))
        {
          unset($rows[$RowIdx]);
          continue;
        }
      }

      /* apply filters */
      if (($filter == "nolics") and ($rf_clause))
      {
        /* discard file unless it has no license */
        $sql = "select rf_fk from license_file where ($rf_clause) and pfile_fk={$row['pf']}";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $FoundRows = pg_num_rows($result);
        pg_free_result($result);
        if ($FoundRows == 0)
        {
          unset($rows[$RowIdx]);
          continue;
        }
      }


      /* rewrite content for easier human assimilation */
      if (MassageContent($rows[$RowIdx], $hash))
      unset($rows[$RowIdx]);
    }

    /* reset array keys, keep order (uploadtree_pk) */
    $rows2 = array();
    foreach ($rows as $row) $rows2[] = $row;
    unset($rows);

    /* remove duplicate files */
    $NumRows = count($rows2);
    $prev = 0;
    for($RowIdx = 0; $RowIdx < $NumRows; $RowIdx++)
    {
      if ($RowIdx > 0)
      {
        /* Since rows are ordered by uploadtree_pk,
         * remove duplicate uploadtree_pk's.  This can happen if there
         * are multiple same copyrights in one file.
         */
        if ($rows2[$RowIdx-1]['uploadtree_pk'] == $rows2[$RowIdx]['uploadtree_pk'])
        unset($rows2[$RowIdx-1]);
      }
    }

    /* sort by name so output has some order */
    usort($rows2, 'copyright_namecmp');

    return $rows2;
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    global $Plugins;
    global $PG_CONN;

    // make sure there is a db connection
    if (!$PG_CONN) {
      echo _("NO DB connection");
    }

    $OutBuf = "";
    $Time = microtime(true);

    $Max = 50;

    /*  Input parameters */
    $agent_pk = GetParm("agent",PARM_INTEGER);
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $hash = GetParm("hash",PARM_RAW);
    $type = GetParm("type",PARM_RAW);
    $excl = GetParm("excl",PARM_RAW);
    $filter = GetParm("filter",PARM_RAW);
    if (empty($uploadtree_pk) || empty($hash) || empty($type) || empty($agent_pk))
    {
      $text = _("is missing required parameters");
      echo $this->Name . " $text.";
      return;
    }

    /* Check item1 and item2 upload permissions */
    $Row = GetSingleRec("uploadtree", "WHERE uploadtree_pk = $uploadtree_pk");
    $UploadPerm = GetUploadPerm($Row['upload_fk']);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }


    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) {
      $Page=0;
    }

    /* get all rows */
    $rows = $this->GetRows($uploadtree_pk, $agent_pk, $upload_pk);

    /* Get uploadtree_tablename */
    $uploadtree_tablename = GetUploadtreeTableName($upload_pk);

    /* slim down to all rows with this hash and type,  and filter */
    $NumInstances = 0;
    $rows = $this->GetRequestedRows($rows, $hash, $type, $excl, $NumInstances, $filter);

    //debugprint($rows, "rows");

    switch($this->OutputType)
    {
      case "XML":
        break;

      case "HTML":
        // micro menus
        $OutBuf .= menu_to_1html(menu_find($this->Name, $MenuDepth),0);

        $RowCount = count($rows);
        if ($RowCount)
        {
          $Content = htmlentities($rows[0]['content']);
          $Offset = ($Page < 0) ? 0 : $Page*$Max;
          $PkgsOnly = false;
          $text = _("files");
          $text1 = _("unique");
          $text3 = _("copyright");
          $text4 = _("email");
          $text5 = _("url");
          switch ($type)
          {
            case "statement":
              $TypeStr = "$text3";
              break;
            case "email":
              $TypeStr = "$text4";
              break;
            case "url":
              $TypeStr = "$text5";
              break;
          }
          $OutBuf .= "$NumInstances $TypeStr instances found in $RowCount  $text";

          $OutBuf .= ": <b>$Content</b>";

          $text = _("Display excludes files with these extensions");
          if (!empty($excl)) $OutBuf .= "<br>$text: $excl";

          /* Get the page menu */
          if (($RowCount >= $Max) && ($Page >= 0))
          {
            $PagingMenu = "<P />\n" . MenuEndlessPage($Page,intval((($RowCount+$Offset)/$Max))) . "<P />\n";
            $OutBuf .= $PagingMenu;
          }
          else
          {
            $PagingMenu = "";
          }

          /* Offset is +1 to start numbering from 1 instead of zero */
          $RowNum = $Offset;
          $LinkLast = "copyrightview&agent=$agent_pk";
          $ShowBox = 1;
          $ShowMicro=NULL;

          // base url
          $ucontent = rawurlencode($Content);
          $baseURL = "?mod=" . $this->Name . "&agent=$agent_pk&item=$uploadtree_pk&hash=$hash&type=$type&page=-1";

          // display rows
          foreach($rows as $row)
          {
            // Allow user to exclude files with this extension
            $FileExt = GetFileExt($row['ufile_name']);
            if (empty($excl))
            $URL = $baseURL . "&excl=$FileExt";
            else
            $URL = $baseURL . "&excl=$excl:$FileExt";

            $text = _("Exclude this file type");
            $Header = "<a href=$URL>$text.</a>";

            $ok = true;
            if ($excl)
            {
              $ExclArray = explode(":", $excl);
              if (in_array($FileExt, $ExclArray)) $ok = false;
            }

            if ($ok) $OutBuf .= Dir2Browse("browse", $row['uploadtree_pk'], $LinkLast, $ShowBox, $ShowMicro, ++$RowNum, $Header, '', $uploadtree_tablename);
          }

        }
        else
        {
          $OutBuf .= _("No files found");
        }

        if (!empty($PagingMenu)) {
          $OutBuf .= $PagingMenu . "\n";
        }
        $OutBuf .= "<hr>\n";
        $Time = microtime(true) - $Time;
        $text = _("Elapsed time");
        $text1 = _("seconds");
        $OutBuf .= sprintf("<small>$text: %.2f $text1</small>\n", $Time);
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($OutBuf);
    }
    print($OutBuf);
    return;
  } // Output()


};
$NewPlugin = new copyright_list;
$NewPlugin->Initialize();

?>
