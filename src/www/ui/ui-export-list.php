<?php
/*
 SPDX-FileCopyrightText: © 2014-2017, 2020 Siemens AG
 SPDX-FileCopyrightText: © 2021 LG Electronics Inc.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * Print the founded and concluded license or copyrights as a list or CSV.
 */

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Proxy\ScanJobProxy;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class UIExportList
 * Print the founded and concluded license or copyrights as a list or CSV.
 */
class UIExportList extends FO_Plugin
{
  /** @var UploadDao $uploadDao
   * Upload Dao object */
  private $uploadDao;

  /** @var LicenseDao $licenseDao
   * License Dao object */
  private $licenseDao;

  /** @var ClearingDao $clearingDao
   * Clearing Dao object */
  private $clearingDao;

  /** @var CopyrightDao $copyrightDao
   * CopyrightDao object */
  private $copyrightDao;

  /** @var ClearingDecisionFilter $clearingFilter
   * Clearing filer */
  private $clearingFilter;

  /** @var TreeDao $treeDao
   * TreeDao to get file path */
  private $treeDao;

  /** @var string $delimiter
   * Delimiter for CSV */
  protected $delimiter = ',';

  /** @var string $enclosure
   * Enclosure for strings in CSV */
  protected $enclosure = '"';

  function __construct()
  {
    $this->Name = "export-list";
    $this->Title = _("Export Lists");
    $this->Dependency = array("browse");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoHeader = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
    $this->copyrightDao = $GLOBALS['container']->get('dao.copyright');
    $this->treeDao = $GLOBALS['container']->get('dao.tree');
    $this->clearingFilter = $GLOBALS['container']->get('businessrules.clearing_decision_filter');
  }

  /**
   * Set the delimiter for CSV
   * @param string $delimiter The delimiter to be used (max len 1)
   */
  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter, 0, 1);
  }

  /**
   * Set the enclosure for CSV
   * @param string $enclosure The enclosure to be used (max len 1)
   */
  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure, 0, 1);
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array(
                "show",
                "format",
                "page",
                "upload",
                "item",
    ));
    $MenuDisplayString = _("Export List");
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Item) || empty($Upload)) {
      return;
    }
    if (GetParm("mod", PARM_STRING) == $this->Name) {
      menu_insert("Browse::$MenuDisplayString", 1);
    } else {
      menu_insert("Browse::$MenuDisplayString", 1, $URI, $MenuDisplayString);
      /* bobg - This is to use a select list in the micro menu to replace the above List
        and Download, but put this select list in a form
        $LicChoices = array("Lic Download" => "Download", "Lic display" => "Display");
        $LicChoice = Array2SingleSelect($LicChoices, $SLName="LicDL");
        menu_insert("Browse::Nomos License List Download2", 1, $URI . "&output=dltext", NULL,NULL, $LicChoice);
       */
    }
  }

  /**
   * Get the agent IDs for requested agents.
   * @param integer $upload_pk  Current upload id
   * @return array  Array with agent name as key and agent id if found or false
   *                as value.
   */
  function getAgentPksFromRequest($upload_pk)
  {
    $agents = array_keys(AgentRef::AGENT_LIST);
    $agent_pks = array();

    foreach ($agents as $agent) {
      if (GetParm("agentToInclude_".$agent, PARM_STRING)) {
        /* get last nomos agent_pk that has data for this upload */
        $AgentRec = AgentARSList($agent."_ars", $upload_pk, 1);
        if (!empty($AgentRec)) {
          $agent_pks[$agent] = $AgentRec[0]["agent_fk"];
        } else {
          $agent_pks[$agent] = false;
        }
      }
    }
    return $agent_pks;
  }

  /**
   * Get the list of lines for the given item.
   * @param string  $uploadtreeTablename Upload tree table for upload
   * @param integer $uploadtree_pk       Item ID
   * @param array   $agent_pks           Agents to be fetched
   * @param integer $NomostListNum       Max limit of items (-1 for unlimited)
   * @param boolean $includeSubfolder    True to include subfolders
   * @param string  $exclude             Files to be excluded
   * @param boolean $ignore              Ignore empty folders
   * @return array Array with each element containing `filePath`, list of
   *               `agentFindings` and list of `conclusions`.
   */
  public function createListOfLines($uploadtreeTablename, $uploadtree_pk,
    $agent_pks, $NomostListNum, $includeSubfolder, $exclude, $ignore)
  {
     $licensesPerFileName = array();
    /** @var ItemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadtree_pk,
      $uploadtreeTablename);
    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds,
      Auth::getGroupId());
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisionsForLicenseList($allDecisions);
    $licensesPerFileName = $this->licenseDao->getLicensesPerFileNameForAgentId($itemTreeBounds,
      $agent_pks, $includeSubfolder, $exclude, $ignore, $editedMappedLicenses,
      true);
    /* how many lines of data do you want to display */
    $currentNum = 0;
    $lines = [];
    foreach ($licensesPerFileName as $fileName => $licenseNames) {
      if ($licenseNames !== false && count($licenseNames) > 0) {
        if ($NomostListNum > -1 && ++$currentNum > $NomostListNum) {
          $lines["warn"] = _("<br><b>Warning: Only the first $NomostListNum " .
            "lines are displayed.  To see the whole list, run " .
            "fo_nomos_license_list from the command line.</b><br>");
          // TODO: the following should be done using a "LIMIT" statement in the sql query
          break;
        }

        $row = array();
        $row['filePath'] = $fileName;
        if (array_key_exists('scanResults', $licenseNames)) {
          $row['agentFindings'] = $licenseNames['scanResults'];
        } else {
          $row['agentFindings'] = null;
        }
        $row['conclusions'] = null;
        $row['uploadtree_pk'] = $licenseNames['uploadtree_pk'][0];
        if (array_key_exists('concludedResults', $licenseNames) && !empty($licenseNames['concludedResults'])) {
          $row['conclusions'] = $this->consolidateConclusions($licenseNames['concludedResults']);
        }
        $lines[] = $row;
      }
      if (!$ignore && $licenseNames === false) {
        $row = array();
        $row['filePath'] = $fileName;
        $row['agentFindings'] = null;
        $row['conclusions'] = null;
        $lines[] = $row;
      }
    }
    return $lines;
  }

  /**
   * @copydoc FO_Plugin::getTemplateName()
   * @see FO_Plugin::getTemplateName()
   */
  public function getTemplateName()
  {
    return "ui-export-list.html.twig";
  }

  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    global $PG_CONN;
    global $SysConf;
    $formVars = array();
    if (!$PG_CONN) {
      echo _("NO DB connection");
    }

    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    }
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    if (empty($uploadtree_pk)) {
      return;
    }

    $upload_pk = GetParm("upload", PARM_INTEGER);
    if (empty($upload_pk)) {
      return;
    }
    if (!$this->uploadDao->isAccessible($upload_pk, Auth::getGroupId())) {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }
    $uploadtreeTablename = GetUploadtreeTableName($upload_pk);

    $warnings = array();
    $exportCopyright = GetParm('export_copy', PARM_STRING);
    if (!empty($exportCopyright) && $exportCopyright == "yes") {
      $exportCopyright = true;
      $copyrightType = GetParm('copyright_type', PARM_STRING);
      $formVars["export_copy"] = "1";
      if ($copyrightType == "all") {
        $formVars["copy_type_all"] = 1;
      } else {
        $formVars["copy_type_nolic"] = 1;
      }
    } else {
      $exportCopyright = false;
      $agent_pks_dict = $this->getAgentPksFromRequest($upload_pk);
      $agent_pks = array();
      foreach ($agent_pks_dict as $agent_name => $agent_pk) {
        if ($agent_pk === false) {
          $warnings[] = _("No information for agent: $agent_name");
        } else {
          $agent_pks[] = $agent_pk;
          $formVars["agentToInclude_".$agent_name] = "1";
        }
      }
    }

    // Make sure all copyrights is selected in the form be default
    if (!(array_key_exists('copy_type_all', $formVars) ||
      array_key_exists('copy_type_nolic', $formVars))) {
      $formVars["copy_type_all"] = 1;
    }

    $dltext = (GetParm("output", PARM_STRING) == 'dltext');
    $formVars["dltext"] = $dltext;
    $dlspreadsheet = (GetParm("output", PARM_STRING) == 'dlspreadsheet');
    $formVars["dlspreadsheet"] = $dlspreadsheet;
    if (!$dltext&&!$dlspreadsheet) {
      $formVars["noDownload"] = true;
    } else {
      $formVars["noDownload"] = false;
    }

    $NomostListNum = @$SysConf['SYSCONFIG']['NomostListNum'];
    $formVars["NomostListNum"] = $NomostListNum;

    $includeSubfolder = (GetParm("doNotIncludeSubfolder", PARM_STRING) !== "yes");
    $formVars["includeSubfolder"] = $includeSubfolder;

    $ignore = (GetParm("showContainers", PARM_STRING) !== "yes");
    $formVars["showContainers"] = !$ignore;
    $exclude = GetParm("exclude", PARM_STRING);
    $formVars["exclude"] = $exclude;

    $consolidateLicenses = (GetParm("consolidate", PARM_STRING) == "perFile");
    $formVars["perFile"] = $consolidateLicenses;
    $consolidatePerDirectory = (GetParm("consolidate", PARM_STRING) == "perDirectory");
    $formVars["perDirectory"] = $consolidatePerDirectory;
    if (!$consolidateLicenses&&!$consolidatePerDirectory) {
      $formVars["rawResult"] = true;
    } else {
      $formVars["rawResult"] = false;
    }

    $this->vars = array_merge($this->vars, $formVars);

    if ($exportCopyright) {
      $lines = $this->getCopyrights($upload_pk, $uploadtree_pk,
        $uploadtreeTablename, $NomostListNum, $exclude, $copyrightType);
    } else {
      $lines = $this->createListOfLines($uploadtreeTablename, $uploadtree_pk,
        $agent_pks, $NomostListNum, $includeSubfolder, $exclude, $ignore);
    }

    $this->vars['warnings'] = array();
    if (array_key_exists("warn",$lines)) {
      $warnings[] = $lines["warn"];
      unset($lines["warn"]);
    }
    foreach ($warnings as $warning) {
      $this->vars['warnings'][] = "<br><b>$warning</b><br>";
    }
    if (empty($lines)) {
      $this->vars['warnings'][] = "<br /><b>Result empty</b><br />";
    }

    if ($consolidateLicenses||$consolidatePerDirectory) {
      $lines = $this->consolidateResult($lines);
    }
    if ($consolidatePerDirectory) {
      $lines = $this->consolidateFindingsPerDirectory($lines);
    }

    if ($dltext) {
      return $this->printCSV($lines, $uploadtreeTablename, $exportCopyright);
    } elseif ($dlspreadsheet) {
      return $this->printSpreadsheet($lines, $uploadtreeTablename);
    } else {
      $this->vars['listoutput'] = $this->printLines($lines, $exportCopyright);
      return;
    }
  }

  /**
   * Get the list of copyrights
   * @param integer $uploadId            Upload ID
   * @param integer $uploadtree_pk       Item ID
   * @param integer $uploadTreeTableName Upload tree table name
   * @param integer $NomostListNum       Limit of lines to print
   * @param integer $exclude             Files to be excluded
   * @param string  $copyrightType       Which copyrights to print (`"all"` to
   *                                     print everything, `"nolic"` to print
   *                                     only files with no scanner findings and
   *                                     no license as conclusion)
   * @return array List of copyrights with `filePath` and `content`
   */
  public function getCopyrights($uploadId, $uploadtree_pk, $uploadTreeTableName,
    $NomostListNum, $exclude, $copyrightType = "all")
  {
    $agentName = array('copyright', 'reso');
    $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'),
      $uploadId);
    $scanJobProxy->createAgentStatus($agentName);
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName[0], $selectedScanners)) {
      return array();
    }
    $latestAgentIds[] = $selectedScanners[$agentName[0]];
    if (array_key_exists($agentName[1], $selectedScanners)) {
      $latestAgentIds[] = $selectedScanners[$agentName[1]];
    }
    $ids = implode(',', $latestAgentIds);
    $agentFilter = ' AND C.agent_fk IN ('.$ids.')';

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadtree_pk,
      $uploadTreeTableName);
    $extrawhere = "UT.lft BETWEEN " . $itemTreeBounds->getLeft() . " AND " .
      $itemTreeBounds->getRight();
    if (! empty($exclude)) {
      $extrawhere .= " AND UT.ufile_name NOT LIKE '%$exclude%'";
    }
    $lines = [];

    $copyrights =  $this->copyrightDao->getScannerEntries($agentName[0],
      $uploadTreeTableName, $uploadId, null, $extrawhere . $agentFilter);
    $this->updateCopyrightList($lines, $copyrights, $NomostListNum,
      $uploadTreeTableName, "content");

    $copyrights = $this->copyrightDao->getEditedEntries('copyright_decision',
      $uploadTreeTableName, $uploadId, [], $extrawhere);
    $this->updateCopyrightList($lines, $copyrights, $NomostListNum,
      $uploadTreeTableName, "textfinding");

    if ($copyrightType != "all") {
      $agentList = [];
      foreach (AgentRef::AGENT_LIST as $agentname => $value) {
        $AgentRec = AgentARSList($agentname."_ars", $uploadId, 1);
        if (!empty($AgentRec)) {
          $agentList[] = $AgentRec[0]["agent_fk"];
        }
      }
      $this->removeCopyrightWithLicense($lines, $itemTreeBounds, $agentList,
        $exclude);
    }
    return $this->reduceCopyrightLines($lines);
  }

  /**
   * Update the list of copyrights with new list
   * @param array[in,out] $list     List of copyrights
   * @param array   $newCopyrights  List of copyrights to be included
   * @param integer $NomostListNum  Limit of copyrights
   * @param string  $uploadTreeTableName Upload tree table name
   * @param string  $key            Key of the array holding copyright
   */
  private function updateCopyrightList(&$list, $newCopyrights, $NomostListNum,
    $uploadTreeTableName, $key)
  {
    foreach ($newCopyrights as $copyright) {
      if ($NomostListNum > -1 && count($list) >= $NomostListNum) {
        $list["warn"] = _("<br><b>Warning: Only the first $NomostListNum lines
 are displayed. To see the whole list, run fo_nomos_license_list from the
 command line.</b><br>");
        break;
      }
      $row = [];
      $row["content"] = $copyright[$key];
      $row["filePath"] = $this->treeDao->getFullPath($copyright["uploadtree_pk"],
        $uploadTreeTableName);
      $list[$row["filePath"]][] = $row;
    }
  }

  /**
   * Remove all files which either have license findings and not remove, or
   * have at least one license as conclusion
   * @param array[in,out] $lines            Lines to be filtered
   * @param ItemTreeBounds $itemTreeBounds  Item bounds
   * @param array $agentList                List of agent IDs
   * @param string $exclude                 Files to be excluded
   */
  private function removeCopyrightWithLicense(&$lines, $itemTreeBounds,
    $agentList, $exclude)
  {
    $licensesPerFileName = array();
    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds,
      Auth::getGroupId());
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisionsForCopyrightList(
      $allDecisions);
    $licensesPerFileName = $this->licenseDao->getLicensesPerFileNameForAgentId(
      $itemTreeBounds, $agentList, true, $exclude, true, $editedMappedLicenses);
    foreach ($licensesPerFileName as $fileName => $licenseNames) {
      if ($licenseNames !== false && count($licenseNames) > 0) {
        if (array_key_exists('concludedResults', $licenseNames)) {
          $conclusions = $this->consolidateConclusions($licenseNames['concludedResults']);
          if (in_array("Void", $conclusions)) {
            // File has all licenses removed or irrelevant decision
            continue;
          }
          // File has license conclusions
          $this->removeIfKeyExists($lines, $fileName);
        }
        if ((! empty($licenseNames['scanResults'])) &&
          ! (in_array("No_license_found", $licenseNames['scanResults']) ||
          in_array("Void", $licenseNames['scanResults']))) {
          $this->removeIfKeyExists($lines, $fileName);
        }
      }
    }
  }

  /**
   * Reduce the 2D list of conclusions on a file to a linear array
   * @param array $conclusions 2D array of conclusions
   * @return array List of unique conclusions on the file
   */
  private function consolidateConclusions($conclusions)
  {
    $consolidatedConclusions = array();
    foreach ($conclusions as $conclusion) {
      $consolidatedConclusions = array_merge($consolidatedConclusions,
        $conclusion);
    }
    return array_unique($consolidatedConclusions);
  }

  /**
   * Remove key from a list if it exists
   *
   * @note Uses strpos to find the key
   * @param array[in,out] $lines Array
   * @param string $key          Key to be removed
   */
  private function removeIfKeyExists(&$lines, $key)
  {
    foreach (array_keys($lines) as $file) {
      if (strpos($file, $key) !== false) {
        unset($lines[$file]);
        break;
      }
    }
  }

  /**
   * Print the lines for browser
   * @param array   $lines     Lines to be printed
   * @param boolean $copyright Results are copyright?
   * @return string
   */
  private function printLines($lines, $copyright=false)
  {
    $V = '';
    if ($copyright) {
      foreach ($lines as $row) {
        $V .= $row['filePath'] . ": " . htmlentities($row['content']) . "\n";
      }
    } else {
      foreach ($lines as $row) {
        $V .= $row['filePath'];
        if ($row['agentFindings'] !== null) {
          $V .= ": " . implode(' ', $row['agentFindings']);
          if ($row['conclusions'] !== null) {
            $V .= ", " . implode(' ', $row['conclusions']);
          }
        }
        $V .= "\n";
      }
    }
    return $V;
  }

  /**
   * Reduce license findings from agents into one
   * @param array $lines     Scanned results of agents and conclusions
   * @return array Lines with consolidated license list
   */
  private function consolidateResult($lines)
  {
    $newLines = [];
    foreach ($lines as $row) {
      $consolidatedLicenses = array();
      if ($row['agentFindings'] == null) {
        continue;
      }
      if ($row['conclusions'] !== null) {
        $row['agentFindings'] = array();
        foreach ($row['conclusions'] as $key => $value) {
          $row['agentFindings'][$key] = $row['conclusions'][$key];
        }
        $row['conclusions'] = null;
      } elseif ($row['agentFindings'] == null) {
        continue;
      } else {
        foreach ($row['agentFindings'] as $key => $value) {
          if ($value == "No_license_found") {
            unset($row['agentFindings'][$key]);
          } else {
            $consolidatedLicenses[] = $row['agentFindings'][$key];
          }
        }
        $consolidatedLicenses = array_unique($consolidatedLicenses);
        foreach ($row['agentFindings'] as $key => $value) {
          if (array_key_exists($key, $consolidatedLicenses) && $consolidatedLicenses[$key] !== null) {
            $row['agentFindings'][$key] = $consolidatedLicenses[$key];
          } else {
            unset($row['agentFindings'][$key]);
          }
        }
      }
      $newLines[] = $row;
    }

    return $newLines;
  }

  /**
   * Remove basename from filePath
   * and reduce lines that has same result.
   * @param array $lines     License and results per file
   * @return array Lines by directories without duplicated result
   */
  private function consolidateFindingsPerDirectory($lines)
  {
    $newLines = [];
    $consolidatedByDirectory = [];
    foreach ($lines as $row) {
      $path_parts = pathinfo($row['filePath']);
      sort($row['agentFindings']);
      if (array_key_exists($path_parts['dirname'], $consolidatedByDirectory)) {
        if (in_array($row['agentFindings'], $consolidatedByDirectory[$path_parts['dirname']])) {
          continue;
        } else {
          $consolidatedByDirectory[$path_parts['dirname']][] = $row['agentFindings'];
        }
      } else {
        $consolidatedByDirectory[$path_parts['dirname']][] = $row['agentFindings'];
      }
    }
    foreach ($consolidatedByDirectory as $key => $value) {
      foreach ($consolidatedByDirectory[$key] as $newKey => $newValue) {
        $newRow = [];
        $newRow['filePath'] = $key . "/";
        $newRow['agentFindings'] = $newValue;
        $newRow['conclusions'] = null;
        $newLines[] = $newRow;
      }
    }
    return $newLines;
  }


  /**
   * Print the lines as CSV
   * @param array   $lines     Lines to be printed
   * @param string  $uploadtreeTablename Upload tree table name
   * @param boolean $copyright Results are copyright?
   * @return Response CSV file as a response
   */
  private function printCSV($lines, $uploadtreeTablename, $copyright = false)
  {
    $request = $this->getRequest();
    $itemId = intval($request->get('item'));
    $path = Dir2Path($itemId, $uploadtreeTablename);
    $fileName = $path[count($path) - 1]['ufile_name']."-".date("Ymd");
    if ($copyright) {
      $fileName .= "-copyrights";
    } else {
      $fileName .= "-licenses";
    }

    $out = fopen('php://output', 'w');
    ob_start();
    if (!$copyright) {
      $head = array('file path', 'scan results', 'concluded results');
    } else {
      $head = array('file path', 'copyright');
    }
    fputcsv($out, $head, $this->delimiter, $this->enclosure);
    foreach ($lines as $row) {
      $newRow = array();
      $newRow[] = $row['filePath'];
      if ($copyright) {
        $newRow[] = $row['content'];
      } else {
        if ($row['agentFindings'] !== null) {
          $newRow[] = implode(' ', $row['agentFindings']);
        } else {
          $newRow[] = "";
        }
        if ($row['conclusions'] !== null) {
          $newRow[] = implode(' ', $row['conclusions']);
        } else {
          $newRow[] = "";
        }
      }
      fputcsv($out, $newRow, $this->delimiter, $this->enclosure);
    }
    $content = ob_get_contents();
    ob_end_clean();

    $headers = array(
      'Content-type' => 'text/csv, charset=UTF-8',
      'Content-Disposition' => 'attachment; filename='.$fileName.'.csv',
      'Pragma' => 'no-cache',
      'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
      'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT'
    );

    return new Response($content, Response::HTTP_OK, $headers);
  }

  /**
   * Print the lines as spreadsheet
   * @param array   $lines     Lines to be printed
   * @param string  $uploadtreeTablename Upload tree table name
   * @return Response spreadsheet(xlsx) file as a response
   */
  private function printSpreadsheet($lines, $uploadtreeTablename)
  {
    $request = $this->getRequest();
    $itemId = intval($request->get('item'));
    $path = Dir2Path($itemId, $uploadtreeTablename);
    $fileName = $path[count($path) - 1]['ufile_name']."-".date("Ymd");

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headRow = array(
        'A' => array(5, 'id', 'ID'),
        'B' => array(30, 'path', 'Source Name or Path'),
        'C' => array(20, 'name', 'OSS Name'),
        'D' => array(10, 'version', 'OSS Version'),
        'E' => array(20, 'scan results', 'Scan Results'),
        'F' => array(20, 'concluded results', 'Concluded Results'),
        'G' => array(20, 'download', 'Download Location'),
        'H' => array(20, 'homepage', 'Homepage'),
        'I' => array(30, 'copyright', 'Copyright Text'),
        'J' => array(10, 'exclude', 'Exclude'),
        'K' => array(30, 'comment', 'Comment')
    );
    $styleArray = [
        'font' => [
            'bold' => true,
            'color' => ['argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE],
        ],
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK],
            ]
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['argb' => '002060'],
        ],
    ];
    $sheet->getStyle('A1:J1')->applyFromArray($styleArray);
    foreach ($headRow as $key => $val) {
      $cellName = $key.'1';

      $sheet->getColumnDimension($key)->setWidth($val[0]);

      $sheet->setCellValue($cellName, $val[2]);
    }

    $styleArray = [
        'font' => [
            'color' => ['argb' => '808080'],
        ],
        'borders' => [
            'vertical' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['argb' => 'B2B2B2'],
            ]
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFFFCC'],
        ],
    ];
    $sheet->getStyle('A2:J2')->applyFromArray($styleArray);
    $annotationRow = array(
      'A' => array('id', '-'),
      'B' => array('path', '[Name of the Source File or Path]'),
      'C' => array('name', '[Name of the OSS used]'),
      'D' => array('version', '[Version Number of the OSS]'),
      'E' => array('scanResults', '[Scan results. Use SPDX Identifier : https://spdx.org/licenses/]'),
      'F' => array('concludedResults', '[Concluded results. Use SPDX Identifier : https://spdx.org/licenses/]'),
      'G' => array('download', '[Download URL or a specific location within a VCS for the OSS]'),
      'H' => array('homepage', '[Web site that serves as the OSSs home page]'),
      'I' => array('copyright', '[The copyright holders of the OSS. E.g. Copyright (c) 201X Copyright Holder]'),
      'J' => array('exclude', '[If this OSS is not included in the final version, Check exclude]'),
      'K' => array('comment','')
    );
    foreach ($annotationRow as $key => $val) {
      $cellName = $key.'2';

      $sheet->setCellValue($cellName, $val[1]);
    }

    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK],
            ]
        ],
    ];

    $id = 1;
    foreach ($lines as $row) {
      $rowNumber = $id + 2;
      $range = 'A'.$rowNumber.':K'.$rowNumber;
      $sheet->getStyle($range)->applyFromArray($styleArray);
      $sheet->setCellValue('A'.$rowNumber, "$id");
      $sheet->setCellValue('B'.$rowNumber, $row['filePath']);

      if ($row['agentFindings'] !== null) {
        $scannedLicenses = implode(',', $row['agentFindings']);
        $sheet->setCellValue('E'.$rowNumber, $scannedLicenses);
      }
      if ($row['conclusions'] !== null) {
        $concludedLicenses = implode(',', $row['conclusions']);
        $sheet->setCellValue('F'.$rowNumber, $concludedLicenses);
      }
      $id++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$fileName.'.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    ob_end_clean();
    $writer->save('php://output');
    exit();
  }

  /**
   * Reduce multidimentional copyright list to simple 2D array
   * @param array $lines Copyright list
   * @return array Simple 2D array
   */
  private function reduceCopyrightLines($lines)
  {
    $reducedLines = array();
    foreach ($lines as $line) {
      foreach ($line as $copyright) {
        $reducedLines[] = $copyright;
      }
    }
    return $reducedLines;
  }
}

$NewPlugin = new UIExportList();
$NewPlugin->Initialize();
