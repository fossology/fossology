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

/*************************************************
 This plugin is used to list all files associated
 with a specific license.
 This is NOT intended to be a user-UI plugin.
 This is intended as an active plugin to provide support
 data to the UI.
 *************************************************/

class search_file_by_licgroup extends FO_Plugin
  {
  var $Name       = "search_file_by_licgroup";
  var $Title      = "List Files based on License Group";
  var $Version    = "1.0";
  var $Dependency = array("db","browse","licgroup","search_file_by_license");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  var $LicPk      = array();

  /***********************************************************
   CmpLicNames(): Sort function.
   ***********************************************************/
  function CmpLicNames       ($a,$b)
    {
    $Aname = $a['lic_name'];
    $Bname = $b['lic_name'];
    if (empty($Aname)) { $Aname = $a; }
    if (empty($Bname)) { $Bname = $b; }
    return(strcmp($Aname,$Bname));
    } // CmpLicNames()

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    global $DB;
    $Time = time();
    $Max = 50;

    $LicGroup = &$Plugins[plugin_find_id("licgroup")];
    $LicGroup->MakeGroupTables();

    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	$UploadTreePk = GetParm("item",PARM_INTEGER);
	$LicGrpPk = GetParm("licgroup",PARM_INTEGER);
	$Page = GetParm("page",PARM_INTEGER);
	if (empty($UploadTreePk) || empty($LicGrpPk))
		{
		return;
		}
	if (empty($Page)) { $Page=0; }
	$Offset = $Page * $Max;

	/* Get License Name */
	$Results = $DB->Action("SELECT * FROM licgroup WHERE licgroup_pk = '$LicGrpPk' LIMIT 1;");
	$LicName = htmlentities($Results[0]['licgroup_name']);
	if (empty($LicName)) { return; }
	$V .= "The following files include licenses in the license group '<b>$LicName</b>'.\n";

	/* Find the key associated with the group id */
	$LicGrp = '';
	foreach($LicGroup->GrpInGroup as $g)
	  {
	  if ($g['id'] == $LicGrpPk) { $LicGrp = $g; }
	  }
	if (empty($LicGrp)) { return; }

	/* Load licenses */
	$Lics = array();
	$M = $Max;
	$O = $Offset;
	$LicPkList = '';
	foreach($LicGrp as $Key => $Val)
	  {
	  if (substr($Key,0,1) != 'l') { continue; }
	  $LicPk = substr($Key,1);
	  if (!empty($LicPkList)) { $LicPkList .= " OR "; }
	  $LicPkList .= "lic_id=$LicPk";
	  }
	LicenseGetAllFiles($UploadTreePk,$Lics,$LicPkList,$M,$O);
	/* $LicPkList = all licenses in this group */

        /*****************************************/
	/* Permit refining the search by license */
        /*****************************************/
	$LicList = array();
	LicenseGetAll($UploadTreePk,$LicList,1);
	$SQL = "SELECT DISTINCT lic_id,lic_name FROM agent_lic_raw
		WHERE ($LicPkList)";
	$First=1;
	foreach($LicList as $L => $Lval)
	  {
	  if (empty($L)) { continue; }
	  if (is_int($L))
	    {
	    if (!$First) { $SQL .= " OR"; }
	    else { $SQL .= " AND ("; }
	    $SQL .= " lic_pk=$L";
	    $First=0;
	    }
	  }
	if (!$First) { $SQL .= ")"; }
	// print "<pre>" . strlen($SQL) . ": $SQL</pre>";
	$Results = $DB->Action($SQL);
	for($i=0; !empty($Results[$i]['lic_name']); $i++)
	  {
	  $Results[$i]['lic_name'] = preg_replace("@^.*/@","",$Results[$i]['lic_name']);
	  }
	usort($Results,array($this,"CmpLicNames"));

if (0)
{
/* TBD: Fix this so it works with license groups. */
	$V .= "<form method='get' action='" . Traceback_uri() . "'>";
	$V .= "Refine search by specific license: ";
	$V .= "<input type='hidden' name='mod' value='search_file_by_license'>";
	$V .= "<input type='hidden' name='item' value='$UploadTreePk'>";
	$V .= "<select name='lic'>";
	for($i=0; !empty($Results[$i]['lic_name']); $i++)
	  {
	  $V .= "<option value='" . $Results[$i]['lic_id'] . "'>";
	  $V .= htmlentities($Results[$i]['lic_name']);
	  $V .= "</option>";
	  }
	$V .= "</select>";
	$V .= "<input type='submit' value='Search!'>";
	$V .= "</form>\n";
}

        /*****************************************/
	/* Save the license results */
        /*****************************************/
	$Count = count($Lics);

	/* Get the page menu */
	if (($Count >= $Max) || ($Page > 0))
	  {
	  $VM = "<P />\n" . MenuEndlessPage($Page, ($Count >= $Max)) . "<P />\n";
	  $V .= $VM;
	  }
	else
	  {
	  $VM = "";
	  }

	for($i=0; $i < $Count; $i++)
	  {
	  $V .= "<P />\n";
	  $L = &$Lics[$i];
	  $Pos = $Offset + $i + 1;
	  $V .= "<table border=1 width='100%' style='background:lightyellow'>";
	  $V .= "<tr><td align='center' width='5%'><font size='+2'>$Pos:</font></td>";
	  $V .= "<td>";
	  if (!empty($L['phrase_text'])) { $V .= "<b>Phrase:</b> " . htmlentities($L['phrase_text']) . "\n"; }
	  if (Isdir($L['ufile_mode']))
	    {
	    $V .= Dir2Browse("licgroup",$L['uploadtree_pk'],$L['ufile_pk'],"license") . "\n";
	    }
	  else
	    {
	    $V .= Dir2Browse("licgroup",$L['uploadtree_pk'],$L['ufile_pk'],"view-license") . "\n";
	    }
	  $V .= "</td></tr></table>\n";
	  }
	if (!empty($VM)) { $V .= $VM . "\n"; }
	$V .= "<hr>\n";
	$Time = time() - $Time;
	$V .= "<small>Elaspsed time: $Time seconds</small>\n";
        break;
      case "Text":
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    } // Output()


  };
$NewPlugin = new search_file_by_licgroup;
$NewPlugin->Initialize();

?>
