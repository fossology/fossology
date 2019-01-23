<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2018, Siemens AG

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

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;
use Symfony\Component\HttpFoundation\Response;

define("TITLE_admin_license_file", _("License Administration"));

class admin_license_file extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name       = "admin_license";
    $this->Title      = TITLE_admin_license_file;
    $this->MenuList   = "Admin::License Admin";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    $this->vars       = array();
    parent::__construct();

    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }

    $URL = $this->Name."&add=y";
    $text = _("Add new license");
    menu_insert("Main::".$this->MenuList."::Add License",0, $URL, $text);
    $URL = $this->Name;
    $text = _("Select license family");
    menu_insert("Main::".$this->MenuList."::Select License",0, $URL, $text);
  }


  public function Output()
  {
    $V = ""; // menu_to_1html(menu_find($this->Name, $MenuDepth),0);
    $errorstr = "License not added";

    // update the db
    if (@$_POST["updateit"])
    {
      $resultstr = $this->Updatedb($_POST);
      $this->vars['message'] = $resultstr;
      if (strstr($resultstr, $errorstr)) {
        return $this->Updatefm(0);
      }
      else {
        return $this->Inputfm();
      }
    }

    if (@$_REQUEST['add'] == 'y')
    {
      return $this->Updatefm(0);
    }

    // Add new rec to db
    if (@$_POST["addit"])
    {
      $resultstr = $this->Adddb();
      $this->vars['message'] = $resultstr;
      if (strstr($resultstr, $errorstr)) {
        return $this->Updatefm(0);
      }
      else {
        return $this->Inputfm();
      }
    }

    // bring up the update form
    $rf_pk = @$_REQUEST['rf_pk'];
    if ($rf_pk)
    {
      return $this->Updatefm($rf_pk);
    }

    // return a license text
    if (@$_GET["getLicenseText"])
    {
      if (@$_GET["licenseID"])
      {
        $licenseText = $this->getLicenseTextForID(@$_GET["licenseID"]);
        if (!$licenseText)
        {
          return new Response("Error in querying license text.", Response::HTTP_BAD_REQUEST, array('Content-type'=>'text/plain'));
        }
        return new Response($licenseText, Response::HTTP_OK, array('Content-type'=>'text/plain'));
      }
    }

    if (@$_POST["req_shortname"])
    {
      $this->vars += $this->getLicenseListData($_POST["req_shortname"], $_POST["req_marydone"]);
    }
    $this->vars['Inputfm'] = $this->Inputfm();
    return $this->render('admin_license_file.html.twig');
  }

  /**
   * \brief Build the input form
   *
   * \return The input form as a string
   */
  function Inputfm()
  {
    $V = "<FORM name='Inputfm' action='?mod=" . $this->Name . "' method='POST'>";
    $V.= _("What license family do you wish to view:<br>");

    // qualify by marydone, short name and long name
    // all are optional
    $V.= "<p>";
    $V.= _("Filter: ");
    $V.= "<select name='req_marydone'>\n";
    $Selected =  (@$_REQUEST['req_marydone'] == 'all') ? " SELECTED ": "";
    $text = _("All");
    $V.= "<option value='all' $Selected> $text </option>";
    $Selected =  (@$_REQUEST['req_marydone'] == 'done') ? " SELECTED ": "";
    $text = _("Checked");
    $V.= "<option value='done' $Selected> $text </option>";
    $Selected =  (@$_REQUEST['req_marydone'] == 'notdone') ? " SELECTED ": "";
    $text = _("Not Checked");
    $V.= "<option value='notdone' $Selected> $text </option>";
    $V.= "</select>";
    $V.= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

    // by short name -ajax-> fullname
    $V.= _("License family name: ");
    $Shortnamearray = $this->FamilyNames();
    $Shortnamearray = array("All"=>"All") + $Shortnamearray;
    $Selected = @$_REQUEST['req_shortname'];
    $Pulldown = Array2SingleSelect($Shortnamearray, "req_shortname", $Selected);
    $V.= $Pulldown;
    $V.= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    $text = _("Find");
    $V.= "<INPUT type='submit' value='$text'>\n";
    $V .= "</FORM>\n";
    $V.= "<hr>";

    return $V;
  }


  private function getLicenseData($where)
  {
    $sql = "select * from ONLY license_ref $where order by rf_shortname";

    return $this->dbManager->getRows($sql);
  }

  /**
   * \brief Build the input form
   *
   * \param $namestr - license family name
   * \param $filter - marydone value requested
   *
   * \return The input form as a string
   */
  function getLicenseListData($namestr, $filter)
  {
    // look at all
    if ($namestr == "All")
      $where = "";
    else
      $where = "where rf_shortname like '". pg_escape_string($namestr) ."%' ";

    // $filter is one of these: "all", "done", "notdone"
    if ($filter != "all")
    {
      if (empty($where))
        $where .= "where ";
      else
        $where .= " and ";
      if ($filter == "done") $where .= " marydone=true";
      if ($filter == "notdone") $where .= " marydone=false";
    }

    $data = $this->getLicenseData($where);
    if(!$data)
    {
      $dataMessage = _("No licenses matching the filter and name pattern were found");
    }
    else
    {
      $dataSize = sizeof($data);
      $plural = "";

      if($dataSize > 1)
        $plural = "s";
      $dataMessage = $dataSize . _(" License$plural found");
    }

    $vars = array(
      'data' => $data,
      'dataMessage' => $dataMessage,
      'message' => "",
      'tracebackURI' => Traceback_uri());
    return $vars;
  }

  function Updatefm($rf_pk)
  {
    $this->vars += $this->getUpdatefmData($rf_pk);
    return $this->render('admin_license-upload_form.html.twig', $this->vars);
  }

  /**
   * @brief Update forms
   * @param int $rf_pk - for the license to update, empty to add
   * @return string The input form
   */
  function getUpdatefmData($rf_pk)
  {
    $vars = array();

    $rf_pk_update = "";

    if (0 < count($_POST)) {
      $rf_pk_update = $_POST['rf_pk'];
      if (!empty($rf_pk)) $rf_pk_update = $rf_pk;
      else if (empty($rf_pk_update)) $rf_pk_update = $_GET['rf_pk'];
    }

    $vars['actionUri'] = "?mod=" . $this->Name . "&rf_pk=$rf_pk_update";
    $vars['req_marydone'] = array_key_exists('req_marydone', $_POST) ? $_POST['req_marydone'] : '';
    $vars['req_shortname'] = array_key_exists('req_shortname', $_POST) ? $_POST['req_shortname'] : '';
    $vars['req_marydone'] = array_key_exists('req_marydone', $_POST) ? $_POST['req_marydone'] : '';
    $vars['rf_shortname'] = array_key_exists('rf_shortname', $_POST) ? $_POST['rf_shortname'] : '';
    $vars['rf_fullname'] = array_key_exists('rf_fullname', $_POST) ? $_POST['rf_fullname'] : '';
    $vars['rf_text'] = array_key_exists('rf_text', $_POST) ? $_POST['rf_text'] : '';

    $parentMap = new LicenseMap($this->dbManager, 0, LicenseMap::CONCLUSION);
    $parentLicenes = $parentMap->getTopLevelLicenseRefs();
    $vars['parentMap'] = array(0=>'[self]');
    foreach ($parentLicenes as $licRef)
    {
      $vars['parentMap'][$licRef->getId()] = $licRef->getShortName();
    }

    $reportMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
    $reportLicenes = $reportMap->getTopLevelLicenseRefs();
    $vars['reportMap'] = array(0=>'[self]');
    foreach ($reportLicenes as $licRef)
    {
      $vars['reportMap'][$licRef->getId()] = $licRef->getShortName();
    }

    if ($rf_pk > 0)  // true if this is an update
    {
      $row = $this->dbManager->getSingleRow("SELECT * FROM ONLY license_ref WHERE rf_pk=$1", array($rf_pk),__METHOD__.'.forUpdate');
      if ($row === false)
      {
        $text = _("No licenses matching this key");
        $text1 = _("was found");
        return "$text ($rf_pk) $text1.";
      }
      $row['rf_parent'] = $parentMap->getProjectedId($rf_pk);
      $row['rf_report'] = $reportMap->getProjectedId($rf_pk);
    }
    else
    {
      $row = array('rf_active' =>'t', 'marydone'=>'f', 'rf_text_updatable'=>'t', 'rf_parent'=>0, 'rf_report'=>0, 'rf_risk'=>0,
        'rf_spdx_compatible'=>'f', 'rf_url'=>'', 'rf_detector_type'=>1, 'rf_notes'=>'');
    }

    foreach(array_keys($row) as $key)
    {
      if (array_key_exists($key, $_POST))
      {
        $row[$key] = $_POST[$key];
      }
    }

    $vars['boolYesNoMap'] = array("true"=>"Yes", "false"=>"No");
    $row['rf_active'] = $this->isTrue($row['rf_active']) ? 'true' : 'false';
    $row['marydone'] = $this->isTrue($row['marydone']) ? 'true' : 'false';
    $row['rf_text_updatable'] = $this->isTrue($row['rf_text_updatable']) ? 'true' : 'false';
    $row['rf_spdx_compatible'] = $this->isTrue($row['rf_spdx_compatible']) ? 'true' : 'false';
    $vars['risk_level'] = array_key_exists('risk_level', $_POST) ? intval($_POST['risk_level']) : $row['rf_risk'];
    $vars['isReadOnly'] = !(empty($rf_pk) || $row['rf_text_updatable']=='true');
    $vars['detectorTypes'] = array("1"=>"Reference License", "2"=>"Nomos", "3"=>"Unconcrete License");

    $vars['rfId'] = $rf_pk?:$rf_pk_update;

    return array_merge($vars,$row);
  }

  /** @brief Check if a variable is true
   * @param mixed $value
   * @return boolean
   */
  private function isTrue($value)
  {
    if (is_bool($value)) {
      return $value;
    }
    else {
      $value = strtolower($value);
      return ($value === 't' || $value === 'true');
    }
  }

  /** @brief check if shortname or license text of this license is existing */
  private function isShortnameBlocked($rfId,$shortname,$text)
  {
    $sql = "SELECT count(*) from license_ref where rf_pk <> $1 and (LOWER(rf_shortname) = LOWER($2) or (rf_text <> ''
      and rf_text = $3 and LOWER(rf_text) NOT LIKE 'license by nomos.'))";
    $check_count = $this->dbManager->getSingleRow($sql,array($rfId,$shortname,$text),__METHOD__.'.countLicensesByNomos');
    return (0 < $check_count['count']);
  }

  /** @brief check if shortname is changed */
  private function isShortNameExists($rfId, $shortname)
  {
    $sql = "SELECT LOWER(rf_shortname) AS rf_shortname FROM license_ref WHERE rf_pk=$1";
    $row = $this->dbManager->getSingleRow($sql,array($rfId),__METHOD__.'.DoNotChnageShortName');
    if ($row['rf_shortname'] === strtolower($shortname)) {
      return 1;
    } else {
      return 0;
    }
  }

  /**
   * \brief Update the database
   *
   * \return An update status string
   */
  function Updatedb()
  {
    $rfId = intval($_POST['rf_pk']);
    $shortname = trim($_POST['rf_shortname']);
    $fullname = trim($_POST['rf_fullname']);
    $url = $_POST['rf_url'];
    $notes = $_POST['rf_notes'];
    $text = trim($_POST['rf_text']);
    $parent = $_POST['rf_parent'];
    $report = $_POST['rf_report'];
    $riskLvl = intval($_POST['risk_level']);

    if (empty($shortname)) {
      $text = _("ERROR: The license shortname is empty. License not added.");
      return "<b>$text</b><p>";
    }

    if (!$this->isShortNameExists($rfId,$shortname)) {
      $text = _("ERROR: The shortname can not be changed. License not added.");
      return "<b>$text</b><p>";
    }

    $md5term = (empty($text) || stristr($text, "License by Nomos")) ? 'null' : 'md5($10)';

    $sql = "UPDATE license_ref SET
        rf_active=$2, marydone=$3,  rf_shortname=$4, rf_fullname=$5,
        rf_url=$6,  rf_notes=$7,  rf_text_updatable=$8,   rf_detector_type=$9,  rf_text=$10,
        rf_md5=$md5term, rf_risk=$11, rf_spdx_compatible=$12, rf_flag=$13
          WHERE rf_pk=$1";
    $params = array($rfId,
      $_POST['rf_active'],$_POST['marydone'],$shortname,$fullname,
      $url,$notes,$_POST['rf_text_updatable'],$_POST['rf_detector_type'],$text,
      $riskLvl,$_POST['rf_spdx_compatible'],2);
    $this->dbManager->prepare($stmt=__METHOD__.".$md5term", $sql);
    $this->dbManager->freeResult($this->dbManager->execute($stmt,$params));

    $parentMap = new LicenseMap($this->dbManager, 0, LicenseMap::CONCLUSION);
    $parentLicenses = $parentMap->getTopLevelLicenseRefs();
    if(array_key_exists($parent, $parentLicenses) && $parent!=$parentMap->getProjectedId($rfId))
    {
      $stmtDel = __METHOD__.'.deleteFromMap';
      $this->dbManager->prepare($stmtDel,'DELETE FROM license_map WHERE rf_fk=$1 AND usage=$2');
      $this->dbManager->execute($stmtDel,array($rfId, LicenseMap::CONCLUSION));
      $this->dbManager->insertTableRow('license_map',
        array('rf_fk'=>$rfId,'rf_parent'=>$parent,'usage'=>LicenseMap::CONCLUSION));
    }

    $reportMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
    $reportLicenses = $parentMap->getTopLevelLicenseRefs();
    if(array_key_exists($report, $reportLicenses) && $report!=$reportMap->getProjectedId($rfId))
    {
      $stmtDel = __METHOD__.'.deleteFromMap';
      $this->dbManager->prepare($stmtDel,'DELETE FROM license_map WHERE rf_fk=$1 AND usage=$2');
      $this->dbManager->execute($stmtDel,array($rfId, LicenseMap::REPORT));
      $this->dbManager->insertTableRow('license_map',
        array('rf_fk'=>$rfId,'rf_parent'=>$report,'usage'=>LicenseMap::REPORT));
    }

    $ob = "License $_POST[rf_shortname] updated.<p>";
    return $ob;
  }


  /**
   * \brief Add a new license_ref to the database
   *
   * \return An add status string
   */
  function Adddb()
  {
    $rf_shortname = trim($_POST['rf_shortname']);
    $rf_fullname = trim($_POST['rf_fullname']);
    $rf_url = $_POST['rf_url'];
    $rf_notes = $_POST['rf_notes'];
    $rf_text = trim($_POST['rf_text']);
    $parent = $_POST['rf_parent'];
    $report = $_POST['rf_report'];
    $riskLvl = intval($_POST['risk_level']);

    if (empty($rf_shortname)) {
      $text = _("ERROR: The license shortname is empty. License not added.");
      return "<b>$text</b><p>";
    }

    if ($this->isShortnameBlocked(0,$rf_shortname,$rf_text))
    {
      $text = _("ERROR: The shortname or license text already exist in the license list. License not added.");
      return "<b>$text</b><p>";
    }

    $md5term = (empty($rf_text) || stristr($rf_text, "License by Nomos")) ? 'null' : 'md5($7)';
    $stmt = __METHOD__.'.rf';
    $sql = "INSERT into license_ref (
        rf_active, marydone, rf_shortname, rf_fullname,
        rf_url, rf_notes, rf_md5, rf_text, rf_text_updatable,
        rf_detector_type, rf_risk, rf_spdx_compatible)
          VALUES (
              $1, $2, $3, $4, $5, $6, $md5term, $7, $8, $9, $10, $11) RETURNING rf_pk";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($_POST['rf_active'],$_POST['marydone'],$rf_shortname,$rf_fullname,
      $rf_url, $rf_notes, $rf_text,$_POST['rf_text_updatable'], $_POST['rf_detector_type'], $riskLvl, $_POST['rf_spdx_compatible']));
    $row = $this->dbManager->fetchArray($res);
    $rfId = $row['rf_pk'];

    $parentMap = new LicenseMap($this->dbManager, 0, LicenseMap::CONCLUSION);
    $parentLicenses = $parentMap->getTopLevelLicenseRefs();
    if(array_key_exists($parent, $parentLicenses))
    {
      $this->dbManager->insertTableRow('license_map',
        array('rf_fk'=>$rfId,'rf_parent'=>$parent,'usage'=>LicenseMap::CONCLUSION));
    }

    $reportMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
    $reportLicenses = $reportMap->getTopLevelLicenseRefs();
    if(array_key_exists($report, $reportLicenses))
    {
      $this->dbManager->insertTableRow('license_map',
        array('rf_fk'=>$rfId,'rf_parent'=>$report,'usage'=>LicenseMap::REPORT));
    }

    $ob = "License $_POST[rf_shortname] (id=$rfId) added.<p>";
    return $ob;
  }


  /**
   * \brief get an array of family names based on the
   *
   * \return an array of family names based on the
   * license_ref.shortname.
   * A family name is the name before most punctuation.
   *
   * \example the family name of "GPL V2" is "GPL"
   */
  function FamilyNames()
  {
    $familynamearray = array();
    $Shortnamearray = DB2KeyValArray("license_ref", "rf_pk", "rf_shortname", " order by rf_shortname");

    // truncate each name to the family name
    foreach ($Shortnamearray as $shortname)
    {
      // start with exceptions
      if (($shortname == "No_license_found")
        || ($shortname == "Unknown license"))
      {
        $familynamearray[$shortname] = $shortname;
      }
      else
      {
        $tok = strtok($shortname, " _-([/");
        $familynamearray[$tok] = $tok;
      }
    }

    return ($familynamearray);
  }

  private function getLicenseTextForID($licenseID)
  {
    $sql = "select rf_text from license_ref where rf_pk=$1";
    $result = $this->dbManager->getSingleRow($sql, array($licenseID));

    if(!$result)
    {
      return false;
    }
    return $result['rf_text'];
  }

}

$NewPlugin = new admin_license_file;
