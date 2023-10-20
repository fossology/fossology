<?php
/*
 SPDX-FileCopyrightText: © 2009-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Data\AgentRef;

/**
 * \file ui-list-lic-files.php
 * \brief This plugin is used to:
 * List files for a given license shortname in a given
 * uploadtree.
 */

define("TITLE_LICENSE_LIST_FILES", _("List Files for License"));

class LicenseListFiles extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;

  /** @var UploadDao */
  private $uploadDao;

  /** @var LicenseDao */
  private $licenseDao;

  /** @var AgentDao */
  private $agentDao;

  /** @var Array */
  protected $agentNames = AgentRef::AGENT_LIST;

  function __construct()
  {
    $this->Name = "license_list_files";
    $this->Title = TITLE_LICENSE_LIST_FILES;
    $this->Dependency = array("browse", "view");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
    $this->agentDao = $GLOBALS['container']->get('dao.agent');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    }

    // micro-menu
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    $rf_shortname = GetParm("lic", PARM_RAW);
    $Excl = GetParm("excl", PARM_RAW);
    $URL = $this->Name . "&item=$uploadtree_pk&lic=".urlencode($rf_shortname)."&page=-1";
    if (!empty($Excl)) {
      $URL .= "&excl=$Excl";
    }
    $text = _("Show All Files");
    menu_insert($this->Name . "::Show All", 0, $URL, $text);
  } // RegisterMenus()

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    $rf_shortname = GetParm("lic", PARM_RAW);
    $tag_pk = GetParm("tag", PARM_INTEGER);
    $Excl = GetParm("excl", PARM_RAW);
    $Exclic = GetParm("exclic", PARM_RAW);
    if (empty($uploadtree_pk) || empty($rf_shortname)) {
      $text = _("is missing required parameters.");
      return $this->Name . " $text";
    }

    $Max = 50;
    $Page = GetParm("page", PARM_INTEGER);
    if (empty($Page)) {
      $Page = 0;
    }

    // Get upload_pk and $uploadtree_tablename
    $UploadtreeRec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtree_pk");
    $uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($UploadtreeRec['upload_fk']);

    // micro menus
    $V = menu_to_1html(menu_find($this->Name, $MenuDepth), 0);

    /* Load licenses */
    $Offset = ($Page < 0) ? 0 : $Page * $Max;
    $order = "";
    $PkgsOnly = false;

    // Count is uploadtree recs, not pfiles
    $agentId = GetParm('agentId', PARM_INTEGER);
    if (empty($agentId)) {
      $scannerAgents = array_keys($this->agentNames);
      $scanJobProxy = new ScanJobProxy($this->agentDao, $UploadtreeRec['upload_fk']);
      $scannerVars = $scanJobProxy->createAgentStatus($scannerAgents);
      $agentId = $scanJobProxy->getLatestSuccessfulAgentIds();
    }
    $CountArray = $this->countFilesWithLicense($agentId, $rf_shortname, $uploadtree_pk, $tag_pk, $uploadtree_tablename);

    if (empty($CountArray)) {
      $V .= _("<b> No files found for license $rf_shortname !</b>\n");
    } else {
      $Count = $CountArray['count'];
      $Unique = $CountArray['unique'];

      $text = _("files found");
      $text2 = _("with license");
      $text3 = _("files are unique with same file hash.");
      $V .= "Total $Count $text $text2 <b>$rf_shortname</b>, $Unique $text3";
      if ($Count < $Max) {
        $Max = $Count;
      }
      $limit = ($Page < 0) ? "ALL" : $Max;
      $order = " order by ufile_name asc";
      /** should delete $filesresult yourself */
      $filesresult = GetFilesWithLicense($agentId, $rf_shortname, $uploadtree_pk,
          $PkgsOnly, $Offset, $limit, $order, $tag_pk, $uploadtree_tablename);
      $NumFiles = pg_num_rows($filesresult);

      $file_result_temp = pg_fetch_all($filesresult);
      $sorted_file_result = array(); // the final file list will display
      $max_num = $NumFiles;
      /** sorting by ufile_name from DB, then reorder the duplicates indented */
      for ($i = 0; $i < $max_num; $i++) {
        $row = $file_result_temp[$i];
        if (empty($row)) {
          continue;
        }
        $sorted_file_result[] = $row;
        for ($j = $i + 1; $j < $max_num; $j ++) {
          $row_next = $file_result_temp[$j];
          if (! empty($row_next) && ($row['pfile_fk'] == $row_next['pfile_fk'])) {
            $sorted_file_result[] = $row_next;
            $file_result_temp[$j] = null;
          }
        }
      }

      $text = _("Display");
      $text1 = _("excludes");
      $text2 = _("files with these extensions");
      if (! empty($Excl)) {
        $V .= "<br>$text <b>$text1</b> $text2: $Excl";
      }

      $text2 = _("files with these licenses");
      if (!empty($Exclic)) {
        $V .= "<br>$text <b>$text1</b> $text2: $Exclic";
      }

      /* Get the page menu */
      if (($Max > 0) && ($Count >= $Max) && ($Page >= 0)) {
        $VM = "<P />\n" . MenuEndlessPage($Page, intval((($Count + $Offset) / $Max))) . "<P />\n";
        $V .= $VM;
      } else {
        $VM = "";
      }

      /* Offset is +1 to start numbering from 1 instead of zero */
      $RowNum = $Offset;
      $LinkLast = "view-license";
      $ShowBox = 1;
      $ShowMicro = null;

      // base url
      $ushortname = rawurlencode($rf_shortname);
      $baseURL = "?mod=" . $this->Name . "&item=$uploadtree_pk&lic=$ushortname&page=-1";

      $V .= "<table>";
      $LastPfilePk = -1;
      $ExclArray = explode(":", $Excl);
      $ExclicArray = explode(":", $Exclic);
      foreach ($sorted_file_result as $row) {
        $pfile_pk = $row['pfile_fk'];
        $licstring = GetFileLicenses_string($row['agent_pk'], $pfile_pk, $row['uploadtree_pk'], $uploadtree_tablename);
        $URLlicstring = urlencode($licstring);

        // Allow user to exclude files with this extension
        $FileExt = GetFileExt($row['ufile_name']);
        $URL = $baseURL;
        if (!empty($Excl)) {
          $URL .= "&excl=$Excl:$FileExt";
        } else {
          $URL .= "&excl=$FileExt";
        }
        if (!empty($Exclic)) {
          $URL .= "&exclic=" . urlencode($Exclic);
        }
        $text = _("Exclude this file type.");
        $Header = "<a href=$URL>$text</a>";

        /* Allow user to exclude files with this exact license list */
        $URL = $baseURL;
        if (!empty($Exclic)) {
          $URL .= "&exclic=" . urlencode($Exclic) . ":" . $URLlicstring;
        } else {
          $URL .= "&exclic=$URLlicstring";
        }
        if (!empty($Excl)) {
          $URL .= "&excl=$Excl";
        }

        $text = _("Exclude files with license");
        $Header .= "<br><a href=$URL>$text: $licstring.</a>";

        $excludeByType = $Excl && in_array($FileExt, $ExclArray);
        $excludeByLicense = $Exclic && in_array($licstring, $ExclicArray);

        if (!empty($licstring) && !$excludeByType && !$excludeByLicense) {
          $V .= "<tr><td>";
          /* Tack on pfile to url - information only */
          $LinkLastpfile = $LinkLast . "&pfile=$pfile_pk";
          if ($LastPfilePk == $pfile_pk) {
            $indent = "<div style='margin-left:2em;'>";
            $outdent = "</div>";
          } else {
            $indent = "";
            $outdent = "";
          }
          $V .= $indent;
          $V .= Dir2Browse("browse", $row['uploadtree_pk'], $LinkLastpfile,
            $ShowBox, $ShowMicro, ++$RowNum, $Header, '', $uploadtree_tablename);
          $V .= $outdent;
          $V .= "</td>";
          $V .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>";
          $V .= "<td>$row[agent_name]: $licstring</td></tr>";
          $V .= "<tr><td colspan=3><hr></td></tr>";
        }
        $LastPfilePk = $pfile_pk;
      }
      pg_free_result($filesresult);
      $V .= "</table>";

      if (!empty($VM)) {
        $V .= $VM . "\n";
      }
    }

    return $V;
  }

  /**
   * @brief Cloned from commen-license-file.php to refactor it
   *
   * \param $agent_pk - agent id or array(agent id)
   * \param $rf_shortname - short name of one license, like GPL, APSL, MIT, ...
   * \param $uploadtree_pk - sets scope of request
   * \param $uploadtree_tablename
   *
   * \return Array "count"=>{total number of pfiles}, "unique"=>{number of unique pfiles}
   */
  protected function countFilesWithLicense($agent_pk, $rf_shortname, $uploadtree_pk, $tag_pk, $uploadtree_tablename)
  {
    $license = $this->licenseDao->getLicenseByShortname($rf_shortname);
    if (null == $license) {
      return array();
    }
    $itemBounds = $this->uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtree_tablename);

    $viewRelavantFiles = "SELECT pfile_fk as PF, uploadtree_pk, ufile_name FROM $uploadtree_tablename";
    $params = array();
    $stmt = __METHOD__;
    if (!empty($tag_pk)) {
      $params[] = $tag_pk;
      $viewRelavantFiles .= " INNER JOIN tag_file ON PF=tag_file.pfile_fk and tag_fk=$".count($params);
      $stmt .= '.tag';
    }
    $params[] = $itemBounds->getLeft();
    $params[] = $itemBounds->getRight();
    $viewRelavantFiles .= ' WHERE lft BETWEEN $'.(count($params)-1).' AND $'.count($params);
    if ($uploadtree_tablename == "uploadtree_a" || $uploadtree_tablename == "uploadtree") {
      $params[] = $itemBounds->getUploadId();
      $viewRelavantFiles .= " AND upload_fk=$".count($params);
      $stmt .= '.upload';
    }

    $params[] = $license->getId();
    $sql = "SELECT count(license_file.pfile_fk) as count, count(distinct license_file.pfile_fk) as unique
          FROM license_file, ($viewRelavantFiles) as SS
          WHERE PF=license_file.pfile_fk AND rf_fk = $".count($params);

    if (is_array($agent_pk)) {
      $params[] = '{' . implode(',', $agent_pk) . '}';
      $sql .= ' AND agent_fk=ANY($'.count($params).')';
      $stmt .= '.agents';
    } elseif (!empty($agent_pk)) {
      $params[] = $agent_pk;
      $sql .= " AND agent_fk=$".count($params);
      $stmt .= '.agent';
    }

    $RetArray = $this->dbManager->getSingleRow($sql,$params,$stmt);
    return $RetArray;
  }
}
$NewPlugin = new LicenseListFiles();
$NewPlugin->Initialize();
