<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }


class admin_license_file extends FO_Plugin
{
  var $Name       = "Admin_License";
  var $Version    = "1.0";
  var $Title      = "License Administration";
  var $MenuList   = "Admin::License Admin";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_WRITE;

/***********************************************************
 RegisterMenus(): Customize submenus.
***********************************************************/
function RegisterMenus()
{
  if ($this->State != PLUGIN_STATE_READY) { return(0); } 

  // micro-menu
  $URL = $this->Name."&add=y";
  menu_insert($this->Name."::AddLicense",0, $URL, "Add new license");
}


/************************************************
 Inputfm(): Build the input form 

 Return: The input form as a string
 ************************************************/
function Inputfm()
{
  $V = "";

  $V.= "<FORM name='Inputfm' action='?mod=" . $this->Name."' method='POST'>";
  $V.= "What license family do you wish to view:<br>";

  // qualify by marydone, short name and long name
  // all are optional
  $V.= "<p>";
  $V.= "Filter: ";
  $V.= "<SELECT name='req_marydone'>\n";
  $V.= "<option value='all'> All </option>";
  $V.= "<option value='done'> Mary is done </option>";
  $V.= "<option value='notdone'> Mary is not done </option>";
  $V.= "</SELECT>";
  $V.= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

  // by short name -ajax-> fullname
  $V.= "License family name: ";
  //$Shortnamearray = DB2KeyValArray("license_ref", "rf_pk", "rf_shortname");
  $Shortnamearray = $this->FamilyNames();
  $Shortnamearray = array("All"=>"All") + $Shortnamearray;
  $Pulldown = Array2SingleSelect($Shortnamearray, "req_shortname");
  $V.= $Pulldown;
  $V.= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
  $V.= "<INPUT type='submit' value='Find'>\n";
  $V .= "</FORM>\n";
  $V.= "<hr>";

  return $V;
}


/************************************************
 LicenseList(): Build the input form 

 Parms: 
   $namestr license family name
   $filter  marydone value requested
 Return: The input form as a string
 ************************************************/
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

  $sql = "select * from license_ref $where order by rf_shortname";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  // print simple message if we have no results
  if (pg_num_rows($result) == 0)
  {
    $ob .= "<br>No licenses matching this the name pattern  ($namestr) was found.<br>";
    return $ob;
  }

  //$ob .= "<table style='border: thin dotted gray'>";
  $ob .= "<table rules='rows' cellpadding='3'>";
  $ob .= "<tr>";
  $ob .= "<th>Edit</th>";
  $ob .= "<th>Mary<br>Done</th>";
  $ob .= "<th>Shortname</th>";
  $ob .= "<th>Fullname</th>";
  $ob .= "<th>Text</th>";
  $ob .= "<th>URL</th>";
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
           "&rf_pk=$row[rf_pk]' >".
           "<image border=0 src=images/button_edit.png></a></td>";

    $marydone = ($row['marydone'] == 't') ? "Yes" : "No";
/* to allow editing in line
    $select = Array2SingleSelect(array("Yes", "No"), "marydone", $marydone);
    $ob .= "<td align=center>$select</td>";
*/
    $ob .= "<td align=center>$marydone</td>";

    $ob .= "<td>$row[rf_shortname]</td>";
    $ob .= "<td>$row[rf_fullname]</td>";
    $vetext = htmlspecialchars($row[rf_text]);
    $ob .= "<td><textarea readonly=readonly rows=3 cols=40>$vetext</textarea></td>";
    $ob .= "<td>$row[rf_url]</td>";
    $ob .= "</tr>";
  }
  $ob .= "</table>";
  return $ob;
}


/************************************************
 Updatefm(): Update forms

 Params:
  $rf_pk is the license_ref table rf_pk
 
 Return: The input form as a string
 ************************************************/
function Updatefm($rf_pk)
{
  global $PG_CONN;

  $ob = "";     // output buffer
  $ob .= "<FORM name='Updatefm' action='?mod=" . $this->Name."' method='POST'>";

  $sql = "select * from license_ref where rf_pk='$rf_pk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  // print simple message if we have no results
  if (pg_num_rows($result) ==0)
  {
    $ob .= "<br>No licenses matching this key ($rf_pk) was found.<br>";
    return $ob;
  }

  $ob .= "<input type=hidden name=updateit value=true>";
  $ob .= "<input type=hidden name=rf_pk value='$rf_pk'>";

  $ob .= "<table>";
  while ($row = pg_fetch_assoc($result))
  {
    $ob .= "<tr>";
    $active = ($row['rf_active'] == 't') ? "Yes" : "No";
    $select = Array2SingleSelect(array("true"=>"Yes", "false"=>"No"), "rf_active", $active);
    $ob .= "<td align=right>Active</td>";
    $ob .= "<td align=left>$select</td>";
    $ob .= "</tr>";

    $ob .= "<tr>";
    $marydone = ($row['marydone'] == 't') ? "Yes" : "No";
    $select = Array2SingleSelect(array("true"=>"Yes", "false"=>"No"), "marydone", $marydone);
    $ob .= "<td align=right>Mary Done</td>";
    $ob .= "<td align=left>$select</td>";
    $ob .= "</tr>";

    $ob .= "<tr>";
//    $ob .= "<td align=right>Short name<br>(read only)</td>";
//    $ob .= "<td><input readonly='readonly' type='text' name='rf_shortname' value='$row[rf_shortname]' size=80></td>";
    $ob .= "<td align=right>Short name</td>";
    $ob .= "<td><input type='text' name='rf_shortname' value='$row[rf_shortname]' size=80></td>";
    $ob .= "</tr>";

    $ob .= "<tr>";
    $ob .= "<td align=right>Full name</td>";
    $ob .= "<td><input type='text' name='rf_fullname' value='$row[rf_fullname]' size=80></td>";
    $ob .= "</tr>";

    $ob .= "<tr>";
    $ro = ($row['rf_text_updatable'] == 't') ? "": "<br>(read only)";
    $ob .= "<td align=right>License Text $ro</td>";
    $ob .= "<td><textarea name='rf_text' rows=10 cols=80 readonly='readonly'>".$row[rf_text]. "</textarea></td>";
    $ob .= "</tr>";

    $ob .= "<tr>";
    $ob .= "<td align=right>URL";
    $ob .= "<a href='$row[rf_url]'><image border=0 src=images/right-point-bullet.gif></a></td>";
    $ob .= "<td><input type='text' name='rf_url' value='$row[rf_url]' size=80></td>";
    $ob .= "</tr>";

    $ob .= "<tr>";
    $ob .= "<td align=right>Public Notes</td>";
    $ob .= "<td><textarea name='rf_notes' rows=5 cols=80>" .$row[rf_notes]. "</textarea></td>";
    $ob .= "</tr>";
  }
  $ob .= "</table>";
  $ob .= "<INPUT type='submit' value='Update'>\n";
  $ob .= "</FORM>\n";
  return $ob;
}


/************************************************
 Updatedb(): Update the database

 Return: An update status string
 ************************************************/
function Updatedb()
{
  global $PG_CONN;

  $ob = "";     // output buffer

  $notes = pg_escape_string($_POST['rf_notes']);
  $sql = "UPDATE license_ref set 
                 rf_active='$_POST[rf_active]', 
                 marydone='$_POST[marydone]',
                 rf_shortname='$_POST[rf_shortname]',
                 rf_fullname='$_POST[rf_fullname]',
                 rf_url='$_POST[rf_url]',
                 rf_notes='$notes'
            WHERE rf_pk='$_POST[rf_pk]'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  
  $ob = "License $_POST[rf_shortname] updated.<p>";
  return $ob;
}


/************************************************
 Output(): Generate output.
 ************************************************/
function Output() 
{
  global $DB, $PG_CONN;
  global $Plugins;
  
  // make sure there is a db connection since I've pierced the core-db abstraction
  if (!$PG_CONN) { $dbok = $DB->db_init(); if (!dbok) echo "NO DB connection"; }
    
  if ($this->State != PLUGIN_STATE_READY) { return; }
  $V="";
    
  $POSTnames = array('rf_pk', 'rf_shortname', 'rf_text', 'rf_url', 'rf_add_date', 'rf_copyleft', 
                     'rf_OSIapproved', 'rf_fullname', 'rf_FSFfree', 'rf_GPLv2compatible', 
                     'rf_GPLv3compatible', 'rf_notes', 'rf_Fedora', 
                     'req_marydone', 'req_shortname');
  
  switch($this->OutputType)
  {
    case "XML":
	  break;
    case "Text":
	  break;
    case "HTML":
      $V .= menu_to_1html(menu_find($this->Name, $MenuDepth),0);
      if ($_POST["updateit"])
      {
        $V .= $this->Updatedb($_POST);
        $V .= $this->Inputfm($_POST);
        break;
      }
    
      $rf_pk = $_REQUEST['rf_pk'];
      if ($rf_pk)
      {
        $V .= $this->Updatefm($rf_pk);
        break;
      }

      $V .= $this->Inputfm($_POST);
      if ($_POST["req_shortname"]) 
        $V .= $this->LicenseList($_POST["req_shortname"], $_POST["req_marydone"]);
	  break;
    default:
	  break;
  }

    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
} // Output()


/************************************************
 FamilyNames()
  Return an array of family names based on the
  license_ref.shortname.
  A family name is the name before most punctuation.
  For example, the family name of "GPL V2" is "GPL"
 ************************************************/
function FamilyNames()
{
  $familynamearray = array();
  $Shortnamearray = DB2KeyValArray("license_ref", "rf_pk", "rf_shortname", " order by rf_shortname");
  
  // truncate each name to the family name
  foreach ($Shortnamearray as $shortname)
  {
    // start with exceptions
    if (($shortname == "No License Found")
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


};

$NewPlugin = new admin_license_file;
?>
