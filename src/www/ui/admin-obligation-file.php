<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2017, Siemens AG

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
use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\Lib\Db\DbManager;

define("TITLE_admin_obligation_file", _("Obligations and Risks Administration"));

class admin_obligation_file extends FO_Plugin
{
  /** @var DbManager */
  private $dbManager;

  /** @var ObligationMap */
  private $obligationMap;

  function __construct()
  {
    $this->Name       = "admin_obligation";
    $this->Title      = TITLE_admin_obligation_file;
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
    if ($this->State != PLUGIN_STATE_READY) { return(0); }

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

    // update the db
    if (@$_POST["updateit"])
    {
      $resultstr = $this->Updatedb($_POST);
      $V .= $resultstr;
      if (strstr($resultstr, $errorstr)) {
        $V .= $this->Updatefm(0);
      }
      else {
        $V .= $this->Inputfm();
      }
      return $V;
    }

    if (@$_REQUEST['add'] == 'y')
    {
      $V .= $this->Updatefm(0);
      return $V;
    }

    // Add new rec to db
    if (@$_POST["addit"])
    {
      $resultstr = $this->Adddb($_POST);
      $V .= $resultstr;
      if (strstr($resultstr, $errorstr)) {
        $V .= $this->Updatefm(0);
      }
      else {
        $V .= $this->Inputfm();
      }
      return $V;
    }

    // bring up the update form
    $ob_pk = @$_REQUEST['ob_pk'];
    if ($ob_pk)
    {
      $V .= $this->Updatefm($ob_pk);
      return $V;
    }

    $V .= $this->Inputfm();
    if (@$_POST['req_topic'])
      $V .= $this->ObligationTopic($_POST['req_topic']);
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
    if ($topic == "All")
      $where = "";
    else
      $where = "WHERE ob_topic='". pg_escape_string($topic) ."' ";

    $sql = "SELECT * FROM ONLY obligation_ref $where ORDER BY ob_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    // print simple message if we have no results
    if (pg_num_rows($result) == 0)
    {
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
    $text = _("Topic");
    $ob .= "<th>$text</th>";
    $text = _("Text");
    $ob .= "<th>$text</th>";
    $text = _("Associated Licenses");
    $ob .= "<th>$text</th>";
    $ob .= "</tr>";
    $lineno = 0;
    while ($row = pg_fetch_assoc($result))
    {
      if ($lineno++ % 2)
        $style = "style='background-color:lavender'";
      else
        $style = "";
      $ob .= "<tr $style>";

      $associatedLicenses = $this->obligationMap->getLicenseList($row['ob_pk']);

      // Edit button brings up full screen edit of all license_ref fields
      $ob .= "<td align=center><a href='";
      $ob .= Traceback_uri();
      $ob .= "?mod=" . $this->Name .
           "&ob_pk=$row[ob_pk]' >'".
           "<img border=0 src='" . Traceback_uri() . "images/button_edit.png'></a></td>";

      $ob .= "<td align=left>$row[ob_topic]</td>";
      $vetext = htmlspecialchars($row['ob_text']);
      $ob .= "<td><textarea readonly=readonly rows=3 cols=40>$vetext</textarea></td> ";
      $ob .= "<td align=center>$associatedLicenses</td>";
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
      if (!empty($ob_pk)) $ob_pk_update = $ob_pk;
      else if (empty($ob_pk_update)) $ob_pk_update = $_GET['ob_pk'];
    }

    $vars['actionUri'] = "?mod=" . $this->Name."&ob_pk=$ob_pk_update";

    if ($ob_pk)  // true if this is an update
    {
      $row = $this->dbManager->getSingleRow("SELECT * FROM ONLY obligation_ref WHERE ob_pk=$1", array($ob_pk),__METHOD__.'.forUpdate');
      if ($row === false)
      {
        $text = _("No obligation matching this key");
        $text1 = _("was found");
        return "$text ($ob_pk) $text1.";
      }

      $associatedLicenses = $this->obligationMap->getLicenseList($ob_pk);
      $vars['licnames'] = explode(";",$associatedLicenses);
    }
    else
    {
      $row = array('ob_active' =>'t', 'ob_text_updatable'=>'t');
    }

    foreach(array_keys($row) as $key)
    {
      if (array_key_exists($key, $_POST))
      {
        $row[$key] = $_POST[$key];
      }
    }

    $vars['boolYesNoMap'] = array("true"=>"Yes", "false"=>"No");
    $row['ob_active'] = $this->dbManager->booleanFromDb($row['ob_active'])?'true':'false';
    $row['ob_text_updatable'] = $this->dbManager->booleanFromDb($row['ob_text_updatable'])?'true':'false';
    $vars['isReadOnly'] = !(empty($ob_pk) || $row['ob_text_updatable']=='true');

    $vars['obId'] = $ob_pk?:$ob_pk_update;

    // get list of known license shortnames
    $licenseMap = new LicenseMap($this->dbManager, 0, LicenseMap::REPORT);
    $reportLicenses = $licenseMap->getTopLevelLicenseRefs();
    $vars['licenseShortnames'] = array();
    foreach ($reportLicenses as $licRef)
    {
      $vars['licenseShortnames'][$licRef->getShortName()] = $licRef->getShortName();
    }
    natcasesort($vars['licenseShortnames']);

    $vars['licenseSelectorName'] = 'licenseSelector[]';
    $vars['licenseSelectorId'] = 'licenseSelectorId';
    $scripts = "<script src='scripts/tools.js' type='text/javascript'></script>
      <script src='scripts/select2.full.min.js'></script>
      <script type='text/javascript'>
        $('#licenseSelectorId').select2({'placeholder': 'Select license associated with this obligation'});
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
    $topic = trim($_POST['ob_topic']);
    $licnames = $_POST['licenseSelector'];
    $text = trim($_POST['ob_text']);
    if (empty($topic)) {
      $text = _("ERROR: The obligation topic is empty.");
      return "<b>$text</b><p>";
    }

    if ($this->isObligationTopicAndTextBlocked($obId,$topic,$text))
    {
      $text = _("ERROR: The obligation topic and text already exist in the obligation list. Obligation not updated.");
      return "<b>$text</b><p>";
    }

    $md5term = empty($text) ? 'null' : 'md5($5)';
    $sql = "UPDATE obligation_ref SET
        ob_active=$2, ob_topic=$3, ob_text_updatable=$4, ob_text=$5,
        ob_md5=$md5term WHERE ob_pk=$1";
    $params = array($obId,
        $_POST['ob_active'],$topic,$_POST['ob_text_updatable'],$text);
    $this->dbManager->prepare($stmt=__METHOD__.".$md5term", $sql);
    $this->dbManager->freeResult($this->dbManager->execute($stmt,$params));

    # Add new licenses
    $associatedLicenses = "";
    foreach ($licnames as $license)
    {
      $licId = $this->obligationMap->getIdFromShortname($license);
      if ($this->obligationMap->isLicenseAssociated($obId,$licId))
        continue;

      $this->obligationMap->associateLicenseWithObligation($obId,$licId);
      if ($associatedLicenses == "")
        $associatedLicenses = "$license";
      else
        $associatedLicenses .= ";$license";
    }

    # Remove licenses that shouldn't be associated with the obligation any more
    $unassociatedLicenses = "";
    $allAssociatedLicenses = $this->obligationMap->getLicenseList($obId);
    $allLicenses = explode(";", $allAssociatedLicenses);
    $obsoleteLicenses = array_diff($allLicenses, $licnames);
    foreach ($obsoleteLicenses as $toBeRemoved)
    {
      $licId = $this->obligationMap->getIdFromShortname($toBeRemoved);
      $this->obligationMap->unassociateLicenseFromObligation($obId,$licId);
      if ($unassociatedLicenses == "")
        $unassociatedLicenses = "$toBeRemoved";
      else
        $unassociatedLicenses .= ";$toBeRemoved";
    }

    $ob = "Obligation '$_POST[ob_topic]' associated with licenses ";
    if ($associatedLicenses != '')
      $ob .=  "(+) '$associatedLicenses' ";
    if ($unassociatedLicenses != '')
      $ob .=  "(-) '$unassociatedLicenses' ";
    $ob .= "updated.<p>";
    return $ob;
  }


  /**
   * \brief Add a new obligation_ref to the database
   *
   * \return An add status string
   */
  function Adddb()
  {
    $ob_topic = trim($_POST['ob_topic']);
    $licnames = $_POST['licenseSelector'];
    $ob_text = trim($_POST['ob_text']);

    if (empty($ob_topic)) {
      $text = _("ERROR: The obligation topic is empty.");
      return "<b>$text</b><p>";
    }

    if (empty($licnames)) {
      $text = _("ERROR: There are no licenses associated with this topic.");
      return "<b>$text</b><p>";
    }

    if ($this->isObligationTopicAndTextBlocked(0,$ob_topic,$ob_text))
    {
      $text = _("ERROR: The obligation topic and text already exist in the obligation list. Obligation not added.");
      return "<b>$text</b><p>";
    }

    $md5term = empty($ob_text) ? 'null' : 'md5($3)';
    $stmt = __METHOD__.'.ob';
    $sql = "INSERT into obligation_ref (ob_active, ob_topic, ob_md5, ob_text, ob_text_updatable) VALUES ($1, $2, $md5term, $3, $4) RETURNING ob_pk";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($_POST['ob_active'],$ob_topic,$ob_text, $_POST['ob_text_updatable']));
    $row = $this->dbManager->fetchArray($res);
    $obId = $row['ob_pk'];

    $associatedLicenses = "";
    foreach ($licnames as $license)
    {
      $licId = $this->obligationMap->getIdFromShortname($license);
      if ($licId == '0')
      {
        $message = _("ERROR: License with shortname '$license' not found in the DB. Obligation not updated.");
        return "<b>$message</b><p>";
      }

      if ($this->obligationMap->isLicenseAssociated($obId,$licId))
        continue;

      $this->obligationMap->associateLicenseWithObligation($obId,$licId);
      if ($associatedLicenses == "")
        $associatedLicenses = "$license";
      else
        $associatedLicenses .= ";$license";
    }

    $ob = "Obligation '$_POST[ob_topic]' associated with licenses '$associatedLicenses' (id=$obId) added.<p>";
    return $ob;
  }

}

$NewPlugin = new admin_obligation_file;
