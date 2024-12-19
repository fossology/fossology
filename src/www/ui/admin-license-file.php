<?php
/*
 SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Composer\Spdx\SpdxLicenses;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\StringOperation;
use Symfony\Component\HttpFoundation\Response;

define("TITLE_ADMIN_LICENSE_FILE", _("License Administration"));

class admin_license_file extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;

  /** @var LicenseDao $licenseDao */
  private $licenseDao;

  function __construct()
  {
    $this->Name       = "admin_license";
    $this->Title      = TITLE_ADMIN_LICENSE_FILE;
    $this->MenuList   = "Admin::License Admin";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    $this->vars       = array();
    $this->obligationSelectorName = "assocObligations"; ///< Selector name for obligation list
    $this->obligationSelectorId = "assocObligations";   ///< Selector id for obligation list
    parent::__construct();

    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    $URL = $this->Name."&add=y";
    $text = _("Add new license");
    menu_insert("Main::".$this->MenuList."::Add License",0, $URL, $text);
    $URL = $this->Name;
    $text = _("Select license family");
    menu_insert("Main::".$this->MenuList."::Select License",0, $URL, $text);
  }

  public function Output()
  {
    $V = "";
    $errorstr = "License not added";

    // update the db
    if (@$_POST["updateit"]) {
      $resultstr = $this->Updatedb($_POST);
      $this->vars['message'] = $resultstr;
      if (strstr($resultstr, $errorstr)) {
        return $this->Updatefm(0);
      } else {
        return $this->Inputfm();
      }
    }

    if (@$_REQUEST['add'] == 'y') {
      return $this->Updatefm(0);
    }

    // Add new rec to db
    if (@$_POST["addit"]) {
      $resultstr = $this->Adddb();
      $this->vars['message'] = $resultstr;
      if (strstr($resultstr, $errorstr)) {
        return $this->Updatefm(0);
      } else {
        return $this->Inputfm();
      }
    }

    // bring up the update form
    $rf_pk = @$_REQUEST['rf_pk'];
    if ($rf_pk) {
      return $this->Updatefm($rf_pk);
    }

    // return a license text
    if (@$_GET["getLicenseText"] && @$_GET["licenseID"]) {
      $licenseText = $this->getLicenseTextForID(@$_GET["licenseID"]);
      if (! $licenseText) {
        return new Response("Error in querying license text.",
          Response::HTTP_BAD_REQUEST, array(
            'Content-type' => 'text/plain'
          ));
      }
      return new Response($licenseText, Response::HTTP_OK,
          array(
            'Content-type' => 'text/plain'
          ));
    }

    if (@$_POST["req_shortname"]) {
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
    $sql = "SELECT rf_pk, marydone, rf_shortname, rf_spdx_id, " .
      "rf_shortname, rf_fullname, rf_url, rf_text, ".
      "string_agg(ob_topic, ';') AS ob_topic " .
      "FROM ONLY license_ref " .
      "LEFT OUTER JOIN obligation_map ON rf_fk = rf_pk " .
      "LEFT OUTER JOIN obligation_ref ON ob_fk = ob_pk " .
      "$where GROUP BY rf_pk ORDER BY rf_shortname";

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
    if ($namestr == "All") {
      $where = "";
    } else {
      $where = "where rf_shortname like '" . pg_escape_string($namestr) . "%' ";
    }

    // $filter is one of these: "all", "done", "notdone"
    if ($filter != "all") {
      if (empty($where)) {
        $where .= "where ";
      } else {
        $where .= " and ";
      }
      if ($filter == "done") {
        $where .= " marydone=true";
      }
      if ($filter == "notdone") {
        $where .= " marydone=false";
      }
    }

    $data = $this->getLicenseData($where);
    if (! $data) {
      $dataMessage = _(
        "No licenses matching the filter and name pattern were found");
    } else {
      $dataSize = sizeof($data);
      $plural = "";

      if ($dataSize > 1) {
        $plural = "s";
      }
      $dataMessage = $dataSize . _(" License$plural found");
    }

    return array(
      'data' => $data,
      'dataMessage' => $dataMessage,
      'message' => "",
      'tracebackURI' => Traceback_uri());
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
    global $SysConf;
    $vars = array();

    $rf_pk_update = "";

    if (0 < count($_POST)) {
      $rf_pk_update = $_POST['rf_pk'];
      if (! empty($rf_pk)) {
        $rf_pk_update = $rf_pk;
      } else if (empty($rf_pk_update)) {
        $rf_pk_update = $_GET['rf_pk'];
      }
    }

    $vars['obligationSelectorName'] = $this->obligationSelectorName . "[]";
    $vars['obligationSelectorId'] = $this->obligationSelectorId;

    $vars['actionUri'] = "?mod=" . $this->Name . "&rf_pk=$rf_pk_update";
    $vars['req_marydone'] = array_key_exists('req_marydone', $_POST) ? $_POST['req_marydone'] : '';
    $vars['req_shortname'] = array_key_exists('req_shortname', $_POST) ? $_POST['req_shortname'] : '';
    $vars['rf_shortname'] = array_key_exists('rf_shortname', $_POST) ? $_POST['rf_shortname'] : '';
    $vars['rf_fullname'] = array_key_exists('rf_fullname', $_POST) ? $_POST['rf_fullname'] : '';
    $vars['rf_text'] = array_key_exists('rf_text', $_POST) ? $_POST['rf_text'] : '';
    $vars['rf_licensetype'] = array_key_exists('rf_licensetype', $_POST) ? $_POST['rf_licensetype'] : '---';
    $selectedObligations = array_key_exists($this->obligationSelectorName,
      $_POST) ? $_POST[$this->obligationSelectorName] : [];

    $parentMap = new LicenseMap($this->dbManager, 0, LicenseMap::CONCLUSION);
    $parentLicenes = $parentMap->getTopLevelLicenseRefs();
    $vars['parentMap'] = array(0=>'[self]');
    foreach ($parentLicenes as $licRef) {
      $vars['parentMap'][$licRef->getId()] = $licRef->getShortName();
    }

    $reportMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
    $reportLicenes = $reportMap->getTopLevelLicenseRefs();
    $vars['reportMap'] = array(0=>'[self]');
    foreach ($reportLicenes as $licRef) {
      $vars['reportMap'][$licRef->getId()] = $licRef->getShortName();
    }

    $obligationMap = new ObligationMap($this->dbManager);
    $obligations = $obligationMap->getObligations();
    foreach ($obligations as $obligation) {
      $vars['obligationTopics'][$obligation['ob_pk']] = $obligation['ob_topic'];
    }
    foreach ($selectedObligations as $obligation) {
      $row['obligationSelected'][$obligation] = $obligationMap->getTopicNameFromId(
        $obligation);
    }

    if ($rf_pk > 0) { // true if this is an update
      $row = $this->dbManager->getSingleRow(
        "SELECT * FROM ONLY license_ref WHERE rf_pk=$1", array($rf_pk),
        __METHOD__ . '.forUpdate');
      if ($row === false) {
        $text = _("ERROR: No licenses matching this key");
        $text1 = _("was found");
        return ["error" => "$text ($rf_pk) $text1."];
      }
      $row['rf_parent'] = $parentMap->getProjectedId($rf_pk);
      $row['rf_report'] = $reportMap->getProjectedId($rf_pk);

      $obligationsAssigned = $parentMap->getObligationsForLicenseRef($rf_pk);
      foreach ($obligationsAssigned as $obligation) {
        $row['obligationSelected'][$obligation] = $obligationMap->getTopicNameFromId(
          $obligation);
      }
    } else {
      $row = array(
        'rf_active' => 't',
        'marydone' => 'f',
        'rf_text_updatable' => 't',
        'rf_parent' => 0,
        'rf_report' => 0,
        'rf_risk' => 0,
        'rf_url' => '',
        'rf_detector_type' => 1,
        'rf_notes' => '',
        'rf_licensetype' => 'Permissive'
      );
    }

    foreach (array_keys($row) as $key) {
      if (array_key_exists($key, $_POST)) {
        $row[$key] = $_POST[$key];
      }
    }

    $vars['boolYesNoMap'] = array("true"=>"Yes", "false"=>"No");
    $row['rf_active'] = $this->isTrue($row['rf_active']) ? 'true' : 'false';
    $row['marydone'] = $this->isTrue($row['marydone']) ? 'true' : 'false';
    $row['rf_text_updatable'] = $this->isTrue($row['rf_text_updatable']) ? 'true' : 'false';
    $vars['risk_level'] = array_key_exists('risk_level', $_POST) ? intval($_POST['risk_level']) : $row['rf_risk'];
    $vars['isReadOnly'] = !(empty($rf_pk) || $row['rf_text_updatable']=='true');
    $vars['detectorTypes'] = array("1"=>"Reference License", "2"=>"Nomos", "3"=>"Unconcrete License");
    $licenseType = $SysConf['SYSCONFIG']['LicenseTypes'];
    $licenseType = explode(',', $licenseType);
    $licenseType = array_map('trim', $licenseType);
    $vars['licenseTypes'] = array_combine($licenseType, $licenseType);
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
    } else {
      $value = strtolower($value);
      return ($value === 't' || $value === 'true');
    }
  }

  /** @brief check if shortname or license text of this license is existing */
  private function isShortnameBlocked($rfId, $shortname, $text)
  {
    $sql = "SELECT count(*) from license_ref where rf_pk <> $1 and (LOWER(rf_shortname) = LOWER($2) or (rf_text <> ''
      and rf_text = $3 and LOWER(rf_text) NOT LIKE 'license by nomos.'))";
    $check_count = $this->dbManager->getSingleRow($sql,
      array(
        $rfId,
        $shortname,
        $text
      ), __METHOD__ . '.countLicensesByNomos');
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
    $spdxLicenses = new SpdxLicenses();
    $errors = [];

    $rfId = intval($_POST['rf_pk']);
    $shortname = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_shortname']));
    $fullname = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_fullname']));
    $url = $_POST['rf_url'];
    $notes = $_POST['rf_notes'];
    $text = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_text']));
    $spdxId = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_spdx_id']));
    $parent = $_POST['rf_parent'];
    $report = $_POST['rf_report'];
    $licensetype = trim($_POST['rf_licensetype']);
    $riskLvl = intval($_POST['risk_level']);
    $selectedObligations = array_key_exists($this->obligationSelectorName,
      $_POST) ? $_POST[$this->obligationSelectorName] : [];

    if (! empty($spdxId) &&
        strstr(strtolower($spdxId), strtolower(LicenseRef::SPDXREF_PREFIX)) === false) {
      if (! $spdxLicenses->validate($spdxId)) {
        $spdxId = LicenseRef::convertToSpdxId($spdxId, null);
        $errors[] = "SPDX ID changed to $spdxId to be compliant with SPDX.";
      }
    } elseif (empty($spdxId)) {
      $spdxId = null;
    }
    if (! empty($spdxId)) {
      $spdxId = LicenseRef::replaceSpaces($spdxId);
    }

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
        rf_md5=$md5term, rf_risk=$11, rf_spdx_id=$12, rf_flag=$13, rf_licensetype=$14
          WHERE rf_pk=$1";
    $params = array($rfId,
      $_POST['rf_active'],$_POST['marydone'],$shortname,$fullname,
      $url,$notes,$_POST['rf_text_updatable'],$_POST['rf_detector_type'],$text,
      $riskLvl,$spdxId,2, $licensetype);
    $statement = __METHOD__ . ".updateLicense";
    if ($md5term == "null") {
      $statement .= ".nullMD5";
    }
    $this->dbManager->prepare($statement, $sql);
    $this->dbManager->freeResult($this->dbManager->execute($statement, $params));

    $licenseMapDelStatement = __METHOD__ . '.deleteFromMap';
    $licenseMapDelSql = 'DELETE FROM license_map WHERE rf_fk=$1 AND usage=$2';
    $this->dbManager->prepare($licenseMapDelStatement, $licenseMapDelSql);

    $parentMap = new LicenseMap($this->dbManager, 0, LicenseMap::CONCLUSION);
    if ($parent == 0) {
      // Update conclusion to self
      $this->dbManager->execute($licenseMapDelStatement,
        array($rfId, LicenseMap::CONCLUSION));
    } else {
      $parentLicenses = $parentMap->getTopLevelLicenseRefs();
      if (array_key_exists($parent, $parentLicenses) &&
        $parent != $parentMap->getProjectedId($rfId)) {
        $this->dbManager->execute($licenseMapDelStatement,
          array($rfId, LicenseMap::CONCLUSION));
        $this->dbManager->insertTableRow('license_map',
          array('rf_fk'=>$rfId, 'rf_parent'=>$parent, 'usage'=>LicenseMap::CONCLUSION));
      }
    }

    if ($report == 0) {
      // Update report to self
      $this->dbManager->execute($licenseMapDelStatement,
        array($rfId, LicenseMap::REPORT));
    } else {
      $reportMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
      $reportLicenses = $parentMap->getTopLevelLicenseRefs();
      if (array_key_exists($report, $reportLicenses) &&
        $report != $reportMap->getProjectedId($rfId)) {
        $this->dbManager->execute($licenseMapDelStatement,
          array($rfId, LicenseMap::REPORT));
        $this->dbManager->insertTableRow('license_map',
          array('rf_fk'=>$rfId, 'rf_parent'=>$report, 'usage'=>LicenseMap::REPORT));
      }
    }

    $obligationMap = new ObligationMap($this->dbManager);
    foreach ($selectedObligations as $obligation) {
      $obligationMap->associateLicenseWithObligation($obligation, $rfId);
    }

    $allObligations = $parentMap->getObligationsForLicenseRef($rfId);
    $removedObligations = array_diff($allObligations, $selectedObligations);
    foreach ($removedObligations as $obligation) {
      $obligationMap->unassociateLicenseFromObligation($obligation, $rfId);
    }

    return "License $_POST[rf_shortname] updated. " . join(" ", $errors) . "<p>";
  }


  /**
   * \brief Add a new license_ref to the database
   *
   * \return An add status string
   */
  function Adddb()
  {
    $spdxLicenses = new SpdxLicenses();
    $errors = [];

    $rf_shortname = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_shortname']));
    $rf_fullname = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_fullname']));
    $rf_spdx_id = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_spdx_id']));
    $rf_url = $_POST['rf_url'];
    $rf_notes = $_POST['rf_notes'];
    $rf_text = StringOperation::replaceUnicodeControlChar(trim($_POST['rf_text']));
    $rf_licensetype = trim($_POST['rf_licensetype']);
    $parent = $_POST['rf_parent'];
    $report = $_POST['rf_report'];
    $riskLvl = intval($_POST['risk_level']);
    $selectedObligations = array_key_exists($this->obligationSelectorName,
      $_POST) ? $_POST[$this->obligationSelectorName] : [];

    if (! empty($rf_spdx_id) &&
        strstr(strtolower($rf_spdx_id), strtolower(LicenseRef::SPDXREF_PREFIX)) === false) {
      if (! $spdxLicenses->validate($rf_spdx_id)) {
        $rf_spdx_id = LicenseRef::convertToSpdxId($rf_spdx_id, null);
        $errors[] = "SPDX ID changed to $rf_spdx_id to be compliant with SPDX.";
      }
    } elseif (empty($rf_spdx_id)) {
      $rf_spdx_id = null;
    }
    if (! empty($rf_spdx_id)) {
      $rf_spdx_id = LicenseRef::replaceSpaces($rf_spdx_id);
    }

    if (empty($rf_shortname)) {
      $text = _("ERROR: The license shortname is empty. License not added.");
      return "<b>$text</b><p>";
    }

    if ($this->isShortnameBlocked(0,$rf_shortname,$rf_text)) {
      $text = _("ERROR: The shortname or license text already exist in the license list. License not added.");
      return "<b>$text</b><p>";
    }

    $md5term = (empty($rf_text) || stristr($rf_text, "License by Nomos")) ? 'null' : 'md5($7)';
    $stmt = __METHOD__.'.rf';
    $sql = "INSERT into license_ref (
        rf_active, marydone, rf_shortname, rf_fullname,
        rf_url, rf_notes, rf_md5, rf_text, rf_text_updatable,
        rf_detector_type, rf_risk, rf_spdx_id, rf_licensetype)
          VALUES (
              $1, $2, $3, $4, $5, $6, $md5term, $7, $8, $9, $10, $11, $12) RETURNING rf_pk";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,
      array(
        $_POST['rf_active'],
        $_POST['marydone'],
        $rf_shortname,
        $rf_fullname,
        $rf_url,
        $rf_notes,
        $rf_text,
        $_POST['rf_text_updatable'],
        $_POST['rf_detector_type'],
        $riskLvl,
        $rf_spdx_id,
        $rf_licensetype
      ));
    $row = $this->dbManager->fetchArray($res);
    $rfId = $row['rf_pk'];

    $parentMap = new LicenseMap($this->dbManager, 0, LicenseMap::CONCLUSION);
    $parentLicenses = $parentMap->getTopLevelLicenseRefs();
    if (array_key_exists($parent, $parentLicenses)) {
      $this->dbManager->insertTableRow('license_map',
        array(
          'rf_fk' => $rfId,
          'rf_parent' => $parent,
          'usage' => LicenseMap::CONCLUSION
        ));
    }

    $reportMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
    $reportLicenses = $reportMap->getTopLevelLicenseRefs();
    if (array_key_exists($report, $reportLicenses)) {
      $this->dbManager->insertTableRow('license_map',
        array(
          'rf_fk' => $rfId,
          'rf_parent' => $report,
          'usage' => LicenseMap::REPORT
        ));
    }

    $obligationMap = new ObligationMap($this->dbManager);
    foreach ($selectedObligations as $obligation) {
      $obligationMap->associateLicenseWithObligation($obligation, $rfId);
    }

    return "License $_POST[rf_shortname] (id=$rfId) added. " .
      join(" ", $errors) . "<p>";
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
    foreach ($Shortnamearray as $shortname) {
      // start with exceptions
      if (($shortname == "No_license_found") || ($shortname == "Unknown license")) {
        $familynamearray[$shortname] = $shortname;
      } else {
        $tok = strtok($shortname, " _-([/");
        $familynamearray[$tok] = $tok;
      }
    }

    return ($familynamearray);
  }

  private function getLicenseTextForID($licenseID)
  {
    $license = $this->licenseDao->getLicenseById($licenseID);

    if ($license == null) {
      return false;
    }
    return $license->getText();
  }
}

$NewPlugin = new admin_license_file();
