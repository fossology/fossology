<?php
/***********************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2013-2016, 2018 Siemens AG

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

/**
 * \file list.php
 * \brief This plugin is used to:
 * List files for a given copyright statement/email/url in a given
 * uploadtree.
 */

define("TITLE_copyright_list", _("List Files for Copyright/Email/URL"));

class copyright_list extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;
  
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "copyright-list";
    $this->Title = TITLE_copyright_list;
    $this->Version = "1.0";
    $this->Dependency = array("copyright-hist", "ecc-hist");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();
    global $container;
    $this->dbManager = $container->get('db.manager');
    $this->uploadDao = $container->get('dao.upload');
  }

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
    $Excl = GetParm("excl",PARM_RAW);

    $URL = $this->Name . "&agent=$agent_pk&item=$uploadtree_pk&hash=$hash&type=$type&page=-1";
    if (!empty($Excl)) {
      $URL .= "&excl=$Excl";
    }
    $text = _("Show All Files");
    menu_insert($this->Name."::Show All",0, $URL, $text);
  } // RegisterMenus()

  /**
   * \return return rows to process, and $upload_pk
   * @param $Uploadtree_pk
   * @param $Agent_pk
   * @param $upload_pk
   * @param $hash
   * @param $type
   * @param $tableName
   * @throws Exception
   * @return array
   */
  function GetRows($Uploadtree_pk, $Agent_pk, &$upload_pk, $hash, $type, $tableName)
  {
    /*******  Get license names and counts  ******/
    $row = $this->uploadDao->getUploadEntry($Uploadtree_pk);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];

    /* get all the copyright records for this uploadtree.  */
    $sql = "SELECT content, type, uploadtree_pk, ufile_name, PF
              from $tableName,
              (SELECT uploadtree_pk, pfile_fk as PF, ufile_name from uploadtree 
                 where upload_fk=$1
                   and uploadtree.lft BETWEEN $2 and $3) as SS
              where PF=pfile_fk and agent_fk=$4 and hash=$5 and type=$6 order by uploadtree_pk";
    $statement = __METHOD__.$tableName;
    $this->dbManager->prepare($statement, $sql);
    $result = $this->dbManager->execute($statement,array($upload_pk, $lft, $rgt, $Agent_pk, $hash, $type));

    $rows = $this->dbManager->fetchAll($result);
    $this->dbManager->freeResult($result);

    return $rows;
  }

  /**
   * \brief Remove unwanted rows by hash and type and
   * exclusions and filter
   * \param $NumRows - the number of instances.
   * \return new array and $NumRows
   */
  function GetRequestedRows($rows, $excl, &$NumRows, $filter)
  {
    $NumRows = count($rows);
    $prev = 0;
    $ExclArray = explode(":", $excl);

    /* filter will need to know the rf_pk of "No_license_found" or "Void" */
    if (!empty($filter))
    {
      $NoLicStr = "No_license_found";
      $VoidLicStr = "Void";
      $rf_clause = "";

      $sql = "select rf_pk from license_ref where rf_shortname IN ($1, $2)";
      $statement = __METHOD__."NoLicenseFoundORVoid";
      $this->dbManager->prepare($statement, $sql);
      $result = $this->dbManager->execute($statement,array("$NoLicStr", "$VoidLicStr"));
      $rf_rows = $this->dbManager->fetchAll($result);
      if(!empty($rf_rows)){
        foreach($rf_rows as $row) 
        {
          if (!empty($rf_clause)) $rf_clause .= " or ";
          $rf_clause .= " rf_fk=$row[rf_pk]";
        }
      }
      $this->dbManager->freeResult($result);
    }

    for($RowIdx = 0; $RowIdx < $NumRows; $RowIdx++)
    {
      $row = $rows[$RowIdx];
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
      if (($filter == "nolic") and ($rf_clause))
      {
        /* discard file unless it has no license */
        $sql = "select rf_fk from license_file where ($rf_clause) and pfile_fk=$1";
        $statement = __METHOD__."CheckForNoLicenseFound";
        $this->dbManager->prepare($statement, $sql);
        $result = $this->dbManager->execute($statement,array("{$row['pf']}"));
        $FoundRows = $this->dbManager->fetchAll($result);
        if (empty($FoundRows))
        {
          unset($rows[$RowIdx]);
          continue;
        }         
      }
    }

    /* reset array keys, keep order (uploadtree_pk) */
    $rows2 = array();
    foreach ($rows as $row) {
      $rows2[] = $row;
    }
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

  function OutputOpen()
  {

    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    return parent::OutputOpen();
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
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
      $this->vars['pageContent'] = $this->Name . _("is missing required parameters");
      return;
    }

    /* Check item1 and item2 upload permissions */
    $Row = $this->uploadDao->getUploadEntry($uploadtree_pk);
    if (!$this->uploadDao->isAccessible($Row['upload_fk'], Auth::getGroupId()))
    {
      $this->vars['pageContent'] = "<h2>" . _("Permission Denied") . "</h2>";
      return;
    }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) {
      $Page=0;
    }

    list($tableName,$modBack,$viewName) = $this->getTableName($type);

    /* get all rows */
    $upload_pk = -1;
    $allRows = $this->GetRows($uploadtree_pk, $agent_pk, $upload_pk, $hash, $type, $tableName);
    $uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload_pk);

    /* slim down to all rows with this hash and type,  and filter */
    $NumInstances = 0;
    $rows = $this->GetRequestedRows($allRows, $excl, $NumInstances, $filter);

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
        case "ecc":
          $TypeStr = _("export restriction");
          break;
        case "keyword":
          $TypeStr = _("Keyword Analysis");
          break;
      }
      $OutBuf .= "$NumInstances $TypeStr instances found in $RowCount  $text";

      $OutBuf .= ": <b>$Content</b>";

      $text = _("Display excludes files with these extensions");
      if (!empty($excl)) $OutBuf .= "<br>$text: $excl";

      /* Get the page menu */
      if (($RowCount >= $Max) && ($Page >= 0))
      {
        $PagingMenu = "<P />\n" . MenuPage($Page,intval((($RowCount+$Offset)/$Max))) . "<P />\n";
        $OutBuf .= $PagingMenu;
      }
      else
      {
        $PagingMenu = "";
      }

      /* Offset is +1 to start numbering from 1 instead of zero */
      $LinkLast = "$viewName&agent=$agent_pk";
      $ShowBox = 1;
      $ShowMicro=NULL;

      $baseURL = "?mod=" . $this->Name . "&agent=$agent_pk&item=$uploadtree_pk&hash=$hash&type=$type&page=-1";

      // display rows
      $RowNum = 0;
      foreach($rows as $row)
      {
        ++$RowNum;
        if ($RowNum < $Offset)
          continue;

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

        if ($ok)
        {
          $OutBuf .= Dir2Browse($modBack, $row['uploadtree_pk'], $LinkLast, $ShowBox, $ShowMicro, $RowNum, $Header, '', $uploadtree_tablename);
        }
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

    $this->vars['pageContent'] = $OutBuf;
    return;
  }

  function getTemplateName()
  {
    return 'copyrightlist.html.twig';
  }

  private function getTableName($type)
  {

    switch ($type) {
      case "ecc" :
        $tableName = "ecc";
        $modBack = "ecc-hist";
        $viewName = "ecc-view";
        break;
      case "keyword" :
        $tableName = "keyword";
        $modBack = "keyword-hist";
        $viewName = "keyword-view";
        break;
      case "statement" :
        $tableName = "copyright";
        $modBack = "copyright-hist";
        $viewName = "copyright-view";
        break;
      default:
        $tableName = "author";
        $modBack = "email-hist";
        $viewName = "copyright-view";
    }
    return array($tableName, $modBack,$viewName);
  }
}

$NewPlugin = new copyright_list;
$NewPlugin->Initialize();
