<?php
/*
 SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\StringOperation;
use Fossology\UI\Api\Models\Obligation;

define("TITLE_ADMIN_OBLIGATION_FILE", _("Obligations and Risks Administration"));

class admin_obligation_file extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;

  /** @var ObligationMap */
  private $obligationMap;

  function __construct()
  {
    $this->Name       = "admin_obligation";
    $this->Title      = TITLE_ADMIN_OBLIGATION_FILE;
    $this->MenuList   = "Admin::Obligation Admin";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();

    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->obligationMap = $GLOBALS['container']->get('businessrules.obligationmap');
  }

  /** @brief return an array of all obligation topics from the DB */
  private function ObligationTopics()
  {
    $topicarray = DB2ValArray("obligation_ref", "ob_topic", true, " order by ob_topic");
    return ($topicarray);
  }

  /** @brief get obligations list along with obligations id */
  public function getObligationsList()
  {
    $sql = "SELECT ob_pk, ob_topic from obligation_ref";
    $obligationsList = $this->dbManager->getRows($sql);
    return ($obligationsList);
  }

  /** @brief get obligations Details along with obligations id */
  public function getObligationsDetails($id)
  {
    $sql = "SELECT * from obligation_ref WHERE ob_pk = $1";
    $obligationsDetails = $this->dbManager->getRows($sql, array($id));
    $obligationsArray=new Obligation($id);
    $finVal=$obligationsArray->getObligations($obligationsDetails);
    return ($finVal);
  }

  /** @brief get obligations Details of all obligations  */
  public function getAllObligationsDetails()
  {
    $sql = "SELECT * from obligation_ref";
    $obligationsDetails = $this->dbManager->getRows($sql);
    $obligationsArray=new Obligation(0);
    $finVal=$obligationsArray->getObligations($obligationsDetails);
    return ($finVal);
  }

  /** @brief check if the text of this obligation is existing */
  private function isObligationTopicAndTextBlocked($obId,$topic,$text)
  {
    $sql = "SELECT count(*) from obligation_ref where ob_pk <> $1 and (ob_topic <> '' and ob_topic = $2) and (ob_text <> '' and ob_text = $3)";
    $check_count = $this->dbManager->getSingleRow($sql,array($obId,$topic,$text));
    return (0 < $check_count['count']);
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
    $text = _("Add new obligation");
    menu_insert("Main::".$this->MenuList."::Add Obligation",0, $URL, $text);
    $URL = $this->Name;
    $text = _("Select obligation");
    menu_insert("Main::".$this->MenuList."::Select Obligation",0, $URL, $text);
  }

  public function Output()
  {
    $V = ""; // menu_to_1html(menu_find($this->Name, $MenuDepth),0);
    $errorstr = "Obligation or risk not added";

    // Delete db record
    if (@$_POST["del"]) {
      if (@$_POST["del"] == 'y') {
        $V .= $this->Deldb();
      } else {
        $V .= "<p>Obligation has not been deleted.</p>";
      }
      $V .= $this->Inputfm();
      return $V;
    }

    // update the db
    if (@$_POST["updateit"]) {
      $resultstr = $this->Updatedb($_POST);
      $V .= $resultstr;
      if (strstr($resultstr, $errorstr)) {
        $V .= $this->Updatefm(0);
      } else {
        $V .= $this->Inputfm();
      }
      return $V;
    }

    if (@$_REQUEST['add'] == 'y') {
      $V .= $this->Updatefm(0);
      return $V;
    }

    // Add new rec to db
    if (@$_POST["addit"]) {
      $resultstr = $this->Adddb($_POST);
      $V .= $resultstr;
      if (strstr($resultstr, $errorstr)) {
        $V .= $this->Updatefm(0);
      } else {
        $V .= $this->Inputfm();
      }
      return $V;
    }

    // bring up the update form
    $ob_pk = @$_REQUEST['ob_pk'];
    if ($ob_pk) {
      $V .= $this->Updatefm($ob_pk);
      return $V;
    }

    $V .= $this->Inputfm();
    if (@$_POST['req_topic']) {
      $V .= $this->ObligationTopic($_POST['req_topic']);
    }
    return $V;
  }

  /**
   * \brief Build the input form
   *
   * \return The input form as a string
   */
  function Inputfm()
  {
    $V = "<FORM name='Inputfm' action='?mod=" . $this->Name . "' method='POST'>";
    $V.= _("From which topic do you wish to view the obligations and risks:<br>");

    // qualify by license name
    // all are optional
    $V.= "<p>";
    $V.= _("From topic: ");
    $Topicarray = $this->ObligationTopics();
    $Topicarray = array("All"=>"All") + $Topicarray;
    $Selected = @$_REQUEST['req_topic'];
    $Pulldown = Array2SingleSelect($Topicarray, "req_topic", $Selected, false, false, "", false);
    $V.= $Pulldown;
    $V.= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    $text = _("Find");
    $V.= "<INPUT type='submit' value='$text'>\n";
    $V .= "</FORM>\n";
    $V.= "<hr>";

    return $V;
  }


  /**
   * \brief Build the input form
   *
   * \param $license - license name
   *
   * \return The input form as a string
   */
  function ObligationTopic($topic)
  {
    global $PG_CONN;

    $ob = "";     // output buffer

    // look at all
    if ($topic == "All") {
      $where = "";
    } else {
      $where = "WHERE ob_topic='". pg_escape_string($topic) ."' ";
    }

    $sql = "SELECT * FROM ONLY obligation_ref $where ORDER BY ob_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    // print simple message if we have no results
    if (pg_num_rows($result) == 0) {
      $topic = addslashes($topic);
      $text1 = _("No obligation matching the topic");
      $text2 = _("were found");
      $ob .= "<br>$text1 '$topic' $text2.<br>";
      pg_free_result($result);
      return $ob;
    }

    $plural = (pg_num_rows($result) == 1) ? "" : "s";
    $ob .= pg_num_rows($result) . " obligation$plural found.";

    $ob .= "<table style='border: thin dotted gray'>";
    $ob .= "<table rules='rows' cellpadding='3'>";
    $ob .= "<tr>";
    $text = _("Edit");
    $ob .= "<th>$text</th>";
    $text = _("Type");
    $ob .= "<th>$text</th>";
    $text = _("Topic");
    $ob .= "<th>$text</th>";
    $text = _("Text");
    $ob .= "<th>$text</th>";
    $text = _("Classification");
    $ob .= "<th>$text</th>";
    $text = _("Apply on modified code");
    $ob .= "<th>$text</th>";
    $text = _("Comment");
    $ob .= "<th>$text</th>";
    $text = _("Associated licenses");
    $ob .= "<th>$text</th>";
    $text = _("Associated candidate licenses");
    $ob .= "<th>$text</th>";
    $ob .= "</tr>";
    $lineno = 0;
    while ($row = pg_fetch_assoc($result)) {
      if ($lineno ++ % 2) {
        $style = "style='background-color:lavender'";
      } else {
        $style = "";
      }
      $ob .= "<tr $style>";

      $associatedLicenses = $this->obligationMap->getLicenseList($row['ob_pk']);
      $candidateLicenses = $this->obligationMap->getLicenseList($row['ob_pk'],True);

      // Edit button brings up full screen edit of all license_ref fields
      $ob .= "<td align=center><a href='";
      $ob .= Traceback_uri();
      $ob .= "?mod=" . $this->Name .
           "&ob_pk=$row[ob_pk]' >".
           "<img border=0 src='" . Traceback_uri() . "images/button_edit.png'></a></td>";

      $ob .= "<td align=left>$row[ob_type]</td>";
      $ob .= "<td align=left>" . htmlspecialchars($row["ob_topic"]) . "</td>";
      $vetext = htmlspecialchars($row['ob_text']);
      $ob .= "<td><textarea readonly=readonly rows=3 cols=40>$vetext</textarea></td> ";
      $ob .= "<td align=left>$row[ob_classification]</td>";
      $ob .= "<td align=center>$row[ob_modifications]</td>";
      $vetext = htmlspecialchars($row['ob_comment']);
      $ob .= "<td><textarea readonly=readonly rows=3 cols=40>$vetext</textarea></td> ";
      $ob .= "<td align=center>$associatedLicenses</td>";
      $ob .= "<td align=center>$candidateLicenses</td>";
      $ob .= "</tr>";
    }
    pg_free_result($result);
    $ob .= "</table>";
    return $ob;
  }

  /**
   * @brief Update forms
   * @param int $ob_pk - for the obligation to update, empty to add
   * @return string The input form
   */
  function Updatefm($ob_pk)
  {
    $vars = array();

    $ob_pk_update = "";

    if (0 < count($_POST)) {
      $ob_pk_update = $_POST['ob_pk'];
      if (! empty($ob_pk)) {
        $ob_pk_update = $ob_pk;
      } else if (empty($ob_pk_update)) {
        $ob_pk_update = $_GET['ob_pk'];
      }
    }
    $vars['actionUri'] = "?mod=" . $this->Name . "&ob_pk=$ob_pk_update";

    if ($ob_pk) { // true if this is an update
      $row = $this->dbManager->getSingleRow(
        "SELECT * FROM ONLY obligation_ref WHERE ob_pk=$1", array(
          $ob_pk
        ), __METHOD__ . '.forUpdate');
      if ($row === false) {
        $text = _("No obligation matching this key");
        $text1 = _("was found");
        return "$text ($ob_pk) $text1.";
      }

      $associatedLicenses = $this->obligationMap->getLicenseList($ob_pk);
      $vars['licnames'] = explode(";", $associatedLicenses);
      $candidateLicenses = $this->obligationMap->getLicenseList($ob_pk, True);
      $vars['candidatenames'] = explode(";", $candidateLicenses);
    } else {
      $row = array('ob_active' => 't',
        'ob_modifications' => 'No',
        'ob_text_updatable' => 't'
      );
    }

    foreach (array_keys($row) as $key) {
      if (array_key_exists($key, $_POST)) {
        $row[$key] = $_POST[$key];
      }
    }

    $vars['boolYesNoMap'] = array("true"=>"Yes", "false"=>"No");
    $vars['YesNoMap'] = array("Yes"=>"Yes", "No"=>"No");
    $row['ob_active'] = $this->dbManager->booleanFromDb($row['ob_active'])?'true':'false';
    $row['ob_text_updatable'] = $this->dbManager->booleanFromDb($row['ob_text_updatable'])?'true':'false';
    $vars['isReadOnly'] = !(empty($ob_pk) || $row['ob_text_updatable']=='true');

    $vars['obId'] = $ob_pk?:$ob_pk_update;

    // get list of known license shortnames
    $vars['licenseShortnames'] = $this->obligationMap->getAvailableShortnames();
    natcasesort($vars['licenseShortnames']);

    // get list of candidate shortnames
    $vars['candidateShortnames'] = $this->obligationMap->getAvailableShortnames(true);
    natcasesort($vars['candidateShortnames']);

    // build obligation type and classification arrays
    /**
     * @todo Add colors $dbManager->risksFromDB
     */
    $vars['obligationClassification'] = array("green"=>"green", "white"=>"white", "yellow"=>"yellow", "red"=>"red");
    $vars['obligationTypes'] = array("Obligation"=>"Obligation",
      "Restriction"=>"Restriction", "Risk"=>"Risk", "Right"=>"Right");

    $vars['ob_type'] = empty($row['ob_type']) ? 'Obligation' : $row['ob_type'];
    $vars['ob_classification'] = empty($row['ob_classification']) ? 'green' : $row['ob_classification'];

    // build scripts
    $vars['licenseSelectorName'] = 'licenseSelector[]';
    $vars['licenseSelectorId'] = 'licenseSelectorId';
    $vars['candidateSelectorName'] = 'candidateSelector[]';
    $vars['candidateSelectorId'] = 'candidateSelectorId';
    $scripts = "<script src='scripts/tools.js' type='text/javascript'></script>
      <script src='scripts/select2.full.min.js'></script>
      <script type='text/javascript'>
        $('#licenseSelectorId').select2({'placeholder': 'Select licenses associated with this obligation'});
      </script>
      <script type='text/javascript'>
        $('#candidateSelectorId').select2({'placeholder': 'Select candidate licenses associated with this obligation'});
      </script>
      <script type='text/javascript'>
        function confirmDeletion() {

          var updateform = document.forms['Updatefm'];
          var delinput = document.createElement('input');
          delinput.name = 'del';

          if (confirm('Are you sure?')) {
            delinput.value = 'y';
          }
          else {
            delinput.value = 'n';
          }
          updateform.appendChild(delinput);
        }
      </script>";

    $this->renderScripts($scripts);
    $allVars = array_merge($vars,$row);
    return $this->renderString('admin_obligation-upload_form.html.twig', $allVars);
  }

  /**
   * \brief Update the database
   *
   * \return An update status string
   */
  function Updatedb()
  {
    $obId = intval($_POST['ob_pk']);
    $topic = StringOperation::replaceUnicodeControlChar(trim($_POST['ob_topic']));
    $licnames = $_POST['licenseSelector'];
    $candidatenames = $_POST['candidateSelector'];
    $text = StringOperation::replaceUnicodeControlChar(trim($_POST['ob_text']));
    $comment = StringOperation::replaceUnicodeControlChar(trim($_POST['ob_comment']));

    if (empty($topic)) {
      $text = _("ERROR: The obligation topic is empty.");
      return "<b>$text</b><p>";
    }

    if (empty($text)) {
      $text = _("ERROR: The obligation text is empty.");
      return "<b>$text</b><p>";
    }

    if ($this->isObligationTopicAndTextBlocked($obId, $topic, $text)) {
      $text = _(
        "ERROR: The obligation topic and text already exist in the obligation list. Obligation not updated.");
      return "<b>$text</b><p>";
    }

    $sql = "UPDATE obligation_ref SET ob_active=$2, ob_type=$3, ob_modifications=$4, ob_topic=$5, ob_md5=md5($6), ob_text=$6, ob_classification=$7, ob_text_updatable=$8, ob_comment=$9 WHERE ob_pk=$1";
    $params = array(
      $obId,
      $_POST['ob_active'],
      $_POST['ob_type'],
      $_POST['ob_modifications'],
      $topic,
      $text,
      $_POST['ob_classification'],
      $_POST['ob_text_updatable'],
      $comment);
    $this->dbManager->prepare($stmt=__METHOD__.".update", $sql);
    $this->dbManager->freeResult($this->dbManager->execute($stmt,$params));

    // Add new licenses and new candiate licenses
    $newAssociatedLicenses = $this->addNewLicenses($licnames,$obId);
    $newCandidateLicenses = $this->addNewLicenses($candidatenames,$obId,true);

    // Remove licenses that shouldn't be associated with the obligation any more
    $unassociatedLicenses = $this->removeLicenses($licnames,$obId);
    $unassociatedCandidateLicenses = $this->removeLicenses($candidatenames,$obId,true);

    $ob = "Obligation '$topic' was updated -  ";
    $ob .= $newAssociatedLicenses ? "New licenses: '$newAssociatedLicenses' - " : "";
    $ob .= $newCandidateLicenses ? "New candidate licenses:  '$newCandidateLicenses' - " : "";
    $ob .= $unassociatedLicenses ? "Removed licenses: '$unassociatedLicenses' - " : "";
    $ob .= $unassociatedCandidateLicenses ? "Removed candidate licenses: '$unassociatedCandidateLicenses'" : "";
    $ob .= "</p>";
    return $ob;
  }


  /**
   * \brief Add a new obligation_ref to the database
   *
   * \return An add status string
   */
  function Adddb()
  {
    $topic = StringOperation::replaceUnicodeControlChar(trim($_POST['ob_topic']));
    $licnames = empty($_POST['licenseSelector']) ? array() : $_POST['licenseSelector'];
    $candidatenames = empty($_POST['candidateSelector']) ? array() : $_POST['candidateSelector'];
    $text = StringOperation::replaceUnicodeControlChar(trim($_POST['ob_text']));
    $comment = StringOperation::replaceUnicodeControlChar(trim($_POST['ob_comment']));
    $message = "";

    if (empty($topic)) {
      $text = _("ERROR: The obligation topic is empty.");
      return "<b>$text</b><p>";
    }

    if (empty($text)) {
      $text = _("ERROR: The obligation text is empty.");
      return "<b>$text</b><p>";
    }

    if (empty($licnames) && empty($candidatenames)) {
      $message = _("ERROR: There are no licenses associated with this topic.");
      return "<b>$message</b><p>";
    }

    if ($this->isObligationTopicAndTextBlocked(0, $topic, $text)) {
      $message = _(
        "ERROR: The obligation topic and text already exist in the obligation list. Obligation not added.");
      return "<b>$message</b><p>";
    }

    $stmt = __METHOD__.'.ob';
    $sql = "INSERT into obligation_ref (ob_active, ob_type, ob_modifications, ob_topic, ob_md5, ob_text, ob_classification, ob_text_updatable, ob_comment) VALUES ($1, $2, $3, $4, md5($5), $5, $6, $7, $8) RETURNING ob_pk";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,
      array($_POST['ob_active'],
        $_POST['ob_type'],
        $_POST['ob_modifications'],
        $topic,
        $text,
        $_POST['ob_classification'],
        $_POST['ob_text_updatable'],
        $comment));
    $row = $this->dbManager->fetchArray($res);
    $obId = $row['ob_pk'];

    $associatedLicenses = $this->addNewLicenses($licnames, $obId);
    $candidateLicenses = $this->addNewLicenses($candidatenames, $obId, True);

    $message .= "Obligation '$topic' associated with: ";
    $message .= $associatedLicenses ? "licenses '$associatedLicenses' " : "";
    $message .= ($associatedLicenses && $candidateLicenses) ? "and " : "";
    $message .= $candidateLicenses ? "candidates licenses '$candidateLicenses' " : "";
    $message .= "(id=$obId) was added.<p>";
    return $message;
  }

  /**
   * \brief Remove obligation_ref from the database
   * and unassociate licenses.
   *
   * \return True
   */
  function Deldb()
  {
    $stmt = __METHOD__.'.delob';
    $sql = "DELETE FROM obligation_ref WHERE ob_pk=$1";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($_POST['ob_pk']));

    $this->obligationMap->unassociateLicenseFromObligation($_POST['ob_pk']);
    $this->obligationMap->unassociateLicenseFromObligation($_POST['ob_pk'], 0, true);

    return "<p>Obligation has been deleted.</p>";
  }

  /**
   * \brief Associate selected licenses to the obligation
   *
   * @param array   $shortnames - new licenses to be associated
   * @param int     $obId - obligation being processed
   * @param boolean $candidate - do we handle candidate licenses?
   * @return string the list of associated licences
   */
  function addNewLicenses($shortnames,$obId,$candidate=false)
  {
    if (!empty($shortnames)) {
      $licList = "";
      foreach ($shortnames as $license) {
        $licIds = $this->obligationMap->getIdFromShortname($license,$candidate);
        $newLic = $this->obligationMap->associateLicenseFromLicenseList($obId,
          $licIds, $candidate);
        if ($newLic) {
          if ($licList == "") {
            $licList = "$license";
          } else {
            $licList .= ";$license";
          }
        }
      }
      return $licList;
    }

    return "";
  }

  /**
   * \brief Unassociate selected licenses to the obligation
   *
   * @param array   $shortnames - new licenses to be associated
   * @param int     $obId - obligation being processed
   * @param boolean $candidate - do we handle candidate licenses?
   * @return string the list of associated licences
   */
  function removeLicenses($shortnames,$obId,$candidate=false)
  {
    $unassociatedLicenses = "";
    $licenses = $this->obligationMap->getLicenseList($obId, $candidate);
    $current = explode(";", $licenses);
    if (! empty($shortnames)) {
      $obsoleteLicenses = array_diff($current, $shortnames);
    } else {
      $obsoleteLicenses = $current;
    }

    if ($obsoleteLicenses) {
      foreach ($obsoleteLicenses as $toBeRemoved) {
        $licIds = $this->obligationMap->getIdFromShortname($toBeRemoved,
          $candidate);
        $this->obligationMap->unassociateLicenseFromLicenseList($obId, $licIds,
          $candidate);
        if ($unassociatedLicenses == "") {
          $unassociatedLicenses = "$toBeRemoved";
        } else {
          $unassociatedLicenses .= ";$toBeRemoved";
        }
      }
    }

    return $unassociatedLicenses;
  }
}

$NewPlugin = new admin_obligation_file();
