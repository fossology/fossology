<?php
/***********************************************************
 * Copyright (C) 2014-2017 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **********************************************************/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Symfony\Component\HttpFoundation\Response;

class ui_license_list extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;

  /** @var LicenseDao */
  private $licenseDao;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var ClearingDecisionFilter */
  private $clearingFilter;

  /** @var string */
  protected $delimiter = ',';
  
  /** @var string */
  protected $enclosure = '"';

  function __construct()
  {
    $this->Name = "license-list";
    $this->Title = _("License List");
    $this->Dependency = array("browse");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoHeader = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
    $this->clearingFilter = $GLOBALS['container']->get('businessrules.clearing_decision_filter');
  }

  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter,0,1);
  }

  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure,0,1);
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
    $MenuDisplayString = _("License List");
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Item) || empty($Upload))
    {
      return;
    }
    if (GetParm("mod", PARM_STRING) == $this->Name)
    {
      menu_insert("Browse::$MenuDisplayString", 1);
    }
    else
    {
      menu_insert("Browse::$MenuDisplayString", 1, $URI, $MenuDisplayString);
      /* bobg - This is to use a select list in the micro menu to replace the above List
        and Download, but put this select list in a form
        $LicChoices = array("Lic Download" => "Download", "Lic display" => "Display");
        $LicChoice = Array2SingleSelect($LicChoices, $SLName="LicDL");
        menu_insert("Browse::Nomos License List Download2", 1, $URI . "&output=dltext", NULL,NULL, $LicChoice);
       */
    }
  }

  function getAgentPksFromRequest($upload_pk)
  {
    $agents = array("monk","nomos","ninka","reportImport");
    $agent_pks = array();

    foreach($agents as $agent)
    {
      if(GetParm("agentToInclude_".$agent, PARM_STRING))
      {
        /* get last nomos agent_pk that has data for this upload */
        $AgentRec = AgentARSList($agent."_ars", $upload_pk, 1);
        if ($AgentRec !== false)
        {
          $agent_pks[$agent] = $AgentRec[0]["agent_fk"];
        }
        else
        {
          $agent_pks[$agent] = false;
        }
      }
    }
    return $agent_pks;
  }

  function createListOfLines($uploadtreeTablename, $uploadtree_pk, $agent_pks, $NomostListNum, $includeSubfolder, $exclude, $ignore)
  {
     $licensesPerFileName = array();
    /** @var ItemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtreeTablename);
    $licensesPerFileNameOld = $this->licenseDao->getLicensesPerFileNameForAgentId($itemTreeBounds, $agent_pks, $includeSubfolder, array(), $exclude, $ignore, true);
    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, Auth::getGroupId());
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisionsForLicenseList($allDecisions);
    foreach($licensesPerFileNameOld as $path => $uploadTreePk){
      foreach($uploadTreePk as $uploadTreeId => $licenseArray){
        if($editedMappedLicenses[$uploadTreeId]){
          $licensesPerFileName[$path]['concludedResults'] = $editedMappedLicenses[$uploadTreeId];
        }
        $licensesPerFileName[$path]['scanResults'] = $licenseArray;
      } 
    }
    /* how many lines of data do you want to display */
    $currentNum = 0;
    $lines = [];
    foreach($licensesPerFileName as $fileName => $licenseNames){
      if ($licenseNames !== false && count($licenseNames) > 0) {
        if(++$currentNum > $NomostListNum){
          $lines["warn"] = _("<br><b>Warning: Only the first $NomostListNum lines are displayed.  To see the whole list, run fo_nomos_license_list from the command line.</b><br>");
          // TODO: the following should be done using a "LIMIT" statement in the sql query
          break;
        }

        if(!empty($licenseNames['concludedResults'])){
          $lines[] = rtrim($fileName .': '.implode($licenseNames['scanResults'],' ') . ', '.implode($licenseNames['concludedResults'],' ') . '', ', ');
        }else{
          $lines[] = rtrim($fileName .': '.implode($licenseNames['scanResults'],' ') . '');
        }
      }
      if (!$ignore && $licenseNames === false)
      {
        $lines[] = $fileName;
      }
    }
    return $lines;
  }

  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    global $PG_CONN;
    global $SysConf;
    $V = "";
    $formVars = array();
    if (!$PG_CONN)
    {
      echo _("NO DB connection");
    }

    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    if (empty($uploadtree_pk))
    {
      return;
    }

    $upload_pk = GetParm("upload", PARM_INTEGER);
    if (empty($upload_pk))
    {
      return;
    }
    if (!$this->uploadDao->isAccessible($upload_pk, Auth::getGroupId()))
    {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }
    $uploadtreeTablename = GetUploadtreeTableName($upload_pk);

    $warnings = array();
    $agent_pks_dict = $this->getAgentPksFromRequest($upload_pk);
    $agent_pks = array();
    foreach ($agent_pks_dict as $agent_name => $agent_pk)
    {
      if ($agent_pk === false)
      {
        $warnings[] = _("No information for agent: $agent_name");
      }
      else
      {
        $agent_pks[] = $agent_pk;
        $formVars["agentToInclude_".$agent_name] = "1";
      }
    }

    $dltext = (GetParm("output", PARM_STRING) == 'dltext');
    $formVars["dltext"] = $dltext;

    $NomostListNum = @$SysConf['SYSCONFIG']['NomostListNum'];
    $formVars["NomostListNum"] = $NomostListNum;

    $includeSubfolder = (GetParm("doNotIncludeSubfolder", PARM_STRING) !== "yes");
    $formVars["includeSubfolder"] = $includeSubfolder;

    $ignore = (GetParm("showContainers", PARM_STRING) !== "yes");
    $formVars["showContainers"] = !$ignore;
    $exclude = GetParm("exclude", PARM_STRING);
    $formVars["exclude"] = $exclude;

    $V .= $this->renderString("ui-license-list-form.html.twig",$formVars);

    $V .= "<hr/>";
    $lines = $this->createListOfLines($uploadtreeTablename, $uploadtree_pk, $agent_pks, $NomostListNum, $includeSubfolder, $exclude, $ignore);

    if (array_key_exists("warn",$lines))
    {
      $warnings[] = $lines["warn"];
      unset($lines["warn"]);
    }
    foreach($warnings as $warning)
    {
      $V .= "<br><b>$warning</b><br>";
    }

    if ($dltext)
    {
      $request = $this->getRequest();
      $itemId = intval($request->get('item'));
      $path = Dir2Path($itemId, $uploadtreeTablename);
      $fileName = $path[count($path) - 1]['ufile_name']."-".date("Ymd");

      $out = fopen('php://output', 'w');
      ob_start();
      $head = array('file path', 'scan results', 'concluded results');
      fputcsv($out, $head, $this->delimiter, $this->enclosure);
      foreach($lines as $row){
        $newRow = explode(':', str_replace(array(': ', ', '), ':', $row));
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

      $response = new Response($content, Response::HTTP_OK, $headers);
      return $response;
    }
    else
    {
      return $V . '<pre>' . implode("\n", $lines) . '</pre>';
    }
  }
}

$NewPlugin = new ui_license_list;
$NewPlugin->Initialize();
