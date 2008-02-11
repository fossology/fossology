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

class ui_view_license extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="view-license";
  var $Version="1.0";
  var $Dependency=array("db","view");

  /***********************************************************
   ConvertLicPathToHighlighting(): Given a license path, insert
   it into the View highlighting.
   ***********************************************************/
  function ConvertLicPathToHighlighting($Row,$LicName)
    {
    global $Plugins;
    $View = &$Plugins[plugin_find_id("view")];

    $First=1;
    foreach(split(",",$Row['pfile_path']) as $Segment)
	{
	$Parts = split("-",$Segment,2);
	if (empty($Parts[1])) { $Parts[1] = $Parts[0]; }
	$Match = intval($Row['tok_match'] * 100 / $Row['tok_pfile']) . "%";
	if ($First) { $First = 0; $Color=-2; }
	else { $Color=-1; $LicName=NULL; }

	$View ->AddHighlight($Parts[0],$Parts[1],$Color,$Match,$LicName);
	}
    } // ConvertLicPathToHighlighting()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    global $DB;
    $View = &$Plugins[plugin_find_id("view")];
    $Pfile = GetParm("pfile",PARM_INTEGER);
    if (empty($Pfile)) { return; }

    /* Load license names */
    $LicPk2GID=array();  // map lic_pk to the group id: lic_id
    $LicGID2Name=array(); // map lic_id to name.
    $Results = $DB->Action("SELECT lic_pk,lic_id,lic_name FROM agent_lic_raw ORDER BY lic_name;");
    foreach($Results as $Key => $R)
      {
      if (empty($R['lic_name'])) { continue; }
      $Name = preg_replace("/^.*\//","",$R['lic_name']);
      $GID = $R['lic_id'];
      $LicGID2Name[$GID] = $Name;
      $LicPk2GID[$R['lic_pk']] = $GID;
      }
    if (empty($LicGID2Name[1])) { $LicGID2Name[1] = 'Phrase'; }
    if (empty($LicPk2GID[1])) { $LicPk2GID[1] = 1; }

    /* Load licenses for this file */
    $Results = $DB->Action("SELECT * FROM agent_lic_meta WHERE pfile_fk = $Pfile ORDER BY tok_pfile_start;");

    /* Process all licenses */
    foreach($Results as $R)
	{
	if (empty($R['pfile_path'])) { continue; }
	if (!empty($R['phrase_text']))
		{
		$LicName = "Phrase: " . $R['phrase_text'];
		}
	else
		{
		$LicGID = $LicPk2GID[$R['lic_fk']];
		$LicName = $LicGID2Name[$LicGID];
		}
	$this->ConvertLicPathToHighlighting($R,$LicName);
	}

    $View->ShowView($this->Name);
    return;
    } // Output()

  };
$NewPlugin = new ui_view_license;
$NewPlugin->Initialize();
?>
