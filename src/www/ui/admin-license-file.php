<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.

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

define("TITLE_admin_license_file", _("License Administration"));

class admin_license_file extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "admin_license";
    $this->Title      = TITLE_admin_license_file;
    $this->MenuList   = "Admin::License Admin";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
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


  protected function htmlContent()
  {
    $V = ""; // menu_to_1html(menu_find($this->Name, $MenuDepth),0);
    $errorstr = "License not added";

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
    $rf_pk = @$_REQUEST['rf_pk'];
    if ($rf_pk)
    {
      $V .= $this->Updatefm($rf_pk);
      return $V;
    }

    $V .= $this->Inputfm();
    if (@$_POST["req_shortname"])
      $V .= $this->LicenseList($_POST["req_shortname"], $_POST["req_marydone"]);

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
    $V.= _("What license family do you wish to view:<br>");

    // qualify by marydone, short name and long name
    // all are optional
    $V.= "<p>";
    $V.= _("Filter: ");
    $V.= "<SELECT name='req_marydone'>\n";
    $Selected =  (@$_REQUEST['req_marydone'] == 'all') ? " SELECTED ": "";
    $text = _("All");
    $V.= "<option value='all' $Selected> $text </option>";
    $Selected =  (@$_REQUEST['req_marydone'] == 'done') ? " SELECTED ": "";
    $text = _("Checked");
    $V.= "<option value='done' $Selected> $text </option>";
    $Selected =  (@$_REQUEST['req_marydone'] == 'notdone') ? " SELECTED ": "";
    $text = _("Not Checked");
    $V.= "<option value='notdone' $Selected> $text </option>";
    $V.= "</SELECT>";
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


  /**
   * \brief Build the input form
   * 
   * \param $namestr - license family name
   * \param $filter - marydone value requested
   *
   * \return The input form as a string
   */
  function LicenseList($namestr, $filter)
  {
    global $PG_CONN;

    $ob = "";     // output buffer
    $whereextra = "";  // additional stuff for where sql where clause

    // look at all
    if ($namestr == "All")
    $where = "";
    else
    $where = "where rf_shortname like '$namestr%' ";

    // $filter is one of these: "All", "done", "notdone"
    if ($filter != "all")
    {
      if (empty($where))
      $where .= "where ";
      else
      $where .= " and ";
      if ($filter == "done") $where .= " marydone=true";
      if ($filter == "notdone") $where .= " marydone=false";
    }

    $sql = "select * from ONLY license_ref $where order by rf_shortname";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    // print simple message if we have no results
    if (pg_num_rows($result) == 0)
    {
      $text = _("No licenses matching the filter");
      $text1 = _("and name pattern");
      $text2 = _("were found");
      $ob .= "<br>$text ($filter) $text1 ($namestr) $text2.<br>";
      pg_free_result($result);
      return $ob;
    }

    $plural = (pg_num_rows($result) == 1) ? "" : "s";
    $ob .= pg_num_rows($result) . " license$plural found.";

    //$ob .= "<table style='border: thin dotted gray'>";
    $ob .= "<table rules='rows' cellpadding='3'>";
    $ob .= "<tr>";
    $text = _("Edit");
    $ob .= "<th>$text</th>";
    $text = _("Checked");
    $ob .= "<th>$text</th>";
    $text = _("Shortname");
    $ob .= "<th>$text</th>";
    $text = _("Fullname");
    $ob .= "<th>$text</th>";
    $text = _("Text");
    $ob .= "<th>$text</th>";
    $text = _("URL");
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

      // Edit button brings up full screen edit of all license_ref fields
      $ob .= "<td align=center><a href='";
      $ob .= Traceback_uri();
      $ob .= "?mod=" . $this->Name .
           "&rf_pk=$row[rf_pk]".
           "&req_marydone=$_REQUEST[req_marydone]&req_shortname=$_REQUEST[req_shortname]' >".
           "<img border=0 src='" . Traceback_uri() . "images/button_edit.png'></a></td>";

      $marydone = ($row['marydone'] == 't') ? "Yes" : "No";

      $text = _("$marydone");
      $ob .= "<td align=center>$text</td>";

      $ob .= "<td>$row[rf_shortname]</td>";
      $ob .= "<td>$row[rf_fullname]</td>";
      $vetext = htmlspecialchars($row['rf_text']);
      $ob .= "<td><textarea readonly=readonly rows=3 cols=40>$vetext</textarea></td> ";
      $ob .= "<td>$row[rf_url]</td>";
      $ob .= "</tr>";
    }
    pg_free_result($result);
    $ob .= "</table>";
    return $ob;
  }


  /**
   * @brief Update forms
   * @param int $rf_pk - for the license to update, empty to add
   * @return string The input form
   */
  function Updatefm($rf_pk)
  {
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');
    $vars = array();

    $rf_pk_update = "";

    if (0 < count($_POST)) {
      $rf_pk_update = $_POST['rf_pk'];
      if (!empty($rf_pk)) $rf_pk_update = $rf_pk;
      else if (empty($rf_pk_update)) $rf_pk_update = $_GET['rf_pk'];
    }

    $vars['actionUri'] = "?mod=" . $this->Name."&rf_pk=$rf_pk_update";
    $vars['req_marydone'] = array_key_exists('req_marydone', $_GET) ? $_GET['req_marydone']:'';
    $vars['req_shortname'] = array_key_exists('req_shortname', $_GET) ? $_GET['req_shortname']:'';
    
    if ($rf_pk)  // true if this is an update
    {
      $row = $dbManager->getSingleRow("SELECT * FROM ONLY license_ref WHERE rf_pk=$1", array($rf_pk),__METHOD__.'.forUpdate');
      if ($row === false)
      {
        $text = _("No licenses matching this key");
        $text1 = _("was found");
        return "$text ($rf_pk) $text1.";
      }
    }
    else
    {
      $row = array('rf_active' =>'t', 'marydone'=>'f', 'rf_text_updatable'=>'t');
    }
    
    foreach(array_keys($row) as $key)
    {
      if (array_key_exists($key, $_POST))
      {
        $row[$key] = $_POST[$key];
      }
    }

    $vars['boolYesNoMap'] = array("true"=>"Yes", "false"=>"No");
    $row['rf_active'] = $dbManager->booleanFromDb($row['rf_active'])?'true':'false';
    $row['marydone'] = $dbManager->booleanFromDb($row['marydone'])?'true':'false';
    $row['rf_text_updatable'] = $dbManager->booleanFromDb($row['rf_text_updatable'])?'true':'false';
    
    $vars['isReadOnly'] = !(empty($rf_pk) || $row['rf_text_updatable']=='true');
    $vars['detectorTypes'] = array("1"=>"Reference License", "2"=>"Nomos");

    $vars['rfId'] = $rf_pk?:$rf_pk_update;

    $allVars = array_merge($vars,$row);
    return $this->renderTemplate('admin_license-upload_form.html.twig', $allVars);
  }


  /**
   * \brief Update the database
   *
   * \return An update status string
   */
  function Updatedb()
  {
    global $PG_CONN;

    $ob = "";     // output buffer

    $shortname = pg_escape_string($_POST['rf_shortname']);
    $fullname = pg_escape_string($_POST['rf_fullname']);
    $url = pg_escape_string($_POST['rf_url']);
    $notes = pg_escape_string($_POST['rf_notes']);
    $text = pg_escape_string($_POST['rf_text']);
    $licmd5 = md5($text);
    $shortname = trim($shortname);
    $fullname = trim($fullname);
    $text = trim($text);

    if (empty($shortname)) {
      $text = _("ERROR: The license shortname is empty.");
      return "<b>$text</b><p>";
    }

    /** check if shortname or license text of this license is existing */
    $sql = "SELECT count(*) from license_ref where rf_pk <> $_POST[rf_pk] and (LOWER(rf_shortname) = LOWER('$shortname') or (rf_text <> ''
      and rf_text = '$text' and LOWER(rf_text) NOT LIKE 'license by nomos'));";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $check_count = pg_fetch_assoc($result);
    pg_free_result($result);
    if (0 < $check_count['count'])
    {
      $text = _("ERROR: The shortname or license text already exist in the license list.  License not added.");
      return "<b>$text</b><p>";
    }

    if (empty($text) || stristr($text, "License by Nomos")) {
      $sql = "UPDATE license_ref set
        rf_active='$_POST[rf_active]', 
        marydone='$_POST[marydone]',
        rf_shortname='$shortname',
        rf_fullname='$fullname',
        rf_url='$url',
        rf_notes='$notes',
        rf_text_updatable='$_POST[rf_text_updatable]',
        rf_detector_type='$_POST[rf_detector_type]',
        rf_text='$text',
        rf_md5=null
          WHERE rf_pk='$_POST[rf_pk]'";
    } else {
      $sql = "UPDATE license_ref set
        rf_active='$_POST[rf_active]', 
        marydone='$_POST[marydone]',
        rf_shortname='$shortname',
        rf_fullname='$fullname',
        rf_url='$url',
        rf_notes='$notes',
        rf_text_updatable='$_POST[rf_text_updatable]',
        rf_detector_type='$_POST[rf_detector_type]',
        rf_text='$text',
        rf_md5='$licmd5'
          WHERE rf_pk='$_POST[rf_pk]'";
    }
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

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
    global $PG_CONN;

    $ob = "";     // output buffer

    $rf_shortname = pg_escape_string($_POST['rf_shortname']);
    $rf_fullname = pg_escape_string($_POST['rf_fullname']);
    $rf_url = pg_escape_string($_POST['rf_url']);
    $rf_notes = pg_escape_string($_POST['rf_notes']);
    $rf_text = pg_escape_string($_POST['rf_text']);
    $rf_shortname = trim($rf_shortname);
    $rf_fullname = trim($rf_fullname);
    $rf_text = trim($rf_text);

    if (empty($rf_shortname)) {
      $text = _("ERROR: The license shortname is empty.");
      return "<b>$text</b><p>";
    }

    /** check if shortname or license text of this license is existing */
    $sql = "SELECT count(*) from license_ref where rf_shortname = '$rf_shortname' or (rf_text <> '' 
      and rf_text = '$rf_text' and LOWER(rf_text) NOT LIKE 'license by nomos');";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $check_count = pg_fetch_assoc($result);
    pg_free_result($result);
    if (0 < $check_count['count'])
    {
      $text = _("ERROR: The shortname or license text already exist in the license list.  License not added.");
      return "<b>$text</b><p>";
    }

    $sql = "";
    if (empty($rf_text) || stristr($rf_text, "License by Nomos")) {
      $sql = "INSERT into license_ref (
        rf_active, marydone, rf_shortname, rf_fullname,
        rf_url, rf_notes, rf_md5, rf_text, rf_text_updatable,
        rf_detector_type) 
          VALUES (
              '$_POST[rf_active]',
              '$_POST[marydone]',
              '$rf_shortname',
              '$rf_fullname',
              '$rf_url',
              '$rf_notes', null, '$rf_text',
              '$_POST[rf_text_updatable]',
              '$_POST[rf_detector_type]')";
    } else {
      $licmd5 = md5($rf_text);
      $sql = "INSERT into license_ref (
        rf_active, marydone, rf_shortname, rf_fullname,
        rf_url, rf_notes, rf_md5, rf_text, rf_text_updatable,
        rf_detector_type) 
          VALUES (
              '$_POST[rf_active]',
              '$_POST[marydone]',
              '$rf_shortname',
              '$rf_fullname',
              '$rf_url',
              '$rf_notes', '$licmd5', '$rf_text',
              '$_POST[rf_text_updatable]',
              '$_POST[rf_detector_type]')";
    }
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $ob = "License $_POST[rf_shortname] added.<p>";
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

}

$NewPlugin = new admin_license_file;
