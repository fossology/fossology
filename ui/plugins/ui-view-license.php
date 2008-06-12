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

class ui_view_license extends FO_Plugin
  {
  var $Name       = "view-license";
  var $Title      = "View License";
  var $Version    = "1.0";
  var $Dependency = array("db","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $NoMenu     = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item","ufile","pfile"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
      {
      if (GetParm("mod",PARM_STRING) == $this->Name)
	{
	menu_insert("View::License",1);
	menu_insert("View-Meta::License",1);
	}
      else
	{
	menu_insert("View::License",1,$URI,"View license histogram");
	menu_insert("View-Meta::License",1,$URI,"View license histogram");
	}
      }
    $Lic = GetParm("lic",PARM_INTEGER);
    if (!empty($Lic)) { $this->NoMenu = 1; }
    } // RegisterMenus()

  /***********************************************************
   ConvertLicPathToHighlighting(): Given a license path, insert
   it into the View highlighting.
   ***********************************************************/
  function ConvertLicPathToHighlighting($Row,$LicName,$RefURL=NULL)
    {
    global $Plugins;
    $View = &$Plugins[plugin_find_id("view")];

    $First=1;
    foreach(split(",",$Row['pfile_path']) as $Segment)
	{
	if (!empty($Segment))
	  {
	  $Parts = split("-",$Segment,2);
	  if (empty($Parts[1])) { $Parts[1] = $Parts[0]; }
	  $Denominator = ($Row['tok_pfile'] + $Row['tok_license']);
	  if ($Denominator > 0) { $Match = intval($Row['tok_match']*200 / $Denominator) . "%"; }
	  else { $Match = "0%"; }
	  if ($First) { $First = 0; $Color=-2; }
	  else { $Color=-1; $LicName=NULL; }

	  $View->AddHighlight($Parts[0],$Parts[1],$Color,$Match,$LicName,-1,$RefURL);
	  }
	}
    } // ConvertLicPathToHighlighting()

  /***********************************************************
   ViewLicense(): Given a pfile_pk, lic_pk, and tok_pfile_start,
   retrieve the license text and display it.
   One caveat: The "ShowView" function only displays file contents.
   But the license is located in the DB.
   Solution: Save license to a temp file.
   NOTE: If the pfile is provided, then highlighting is enabled.
   ***********************************************************/
  function ViewLicense($PfilePk, $LicPk, $TokPfileStart)
    {
    global $DB;
    global $Plugins;
    $View = &$Plugins[plugin_find_id("view")];

    /* Find the license path */
    if (!empty($PfilePk))
      {
      $Results = $DB->Action("SELECT license_path,tok_match,tok_license FROM agent_lic_meta WHERE pfile_fk = $PfilePk AND lic_fk = $LicPk AND tok_pfile_start = $TokPfileStart ORDER BY version DESC LIMIT 1;");
      $Lic = $Results[0];
      if (empty($Lic['license_path'])) { return; }
      }

    /* For ConvertLicPathToHighlighting, reverse the columns */
    $Lic['pfile_path'] = $Lic['license_path'];
    $Lic['tok_pfile'] = $Lic['tok_license'];

    /* Load the License name and data */
    $Results = $DB->Action("SELECT lic_name, lic_url FROM agent_lic_raw WHERE lic_pk = $LicPk;");
    if (empty($Results[0]['lic_name'])) { return; }

    /* View license text as a temp file */
    global $DATADIR;
    $Ftmp = fopen("$DATADIR/agents/licenses/" . $Results[0]['lic_name'],"rb");

    /* Save the path */
    $this->ConvertLicPathToHighlighting($Lic,NULL);
    $Text = "<div class='text'>";
    $Text .= "<H1>License: " . $Results[0]['lic_name'] . "</H1>\n";
    $Text .= "Reference URL: <a href=\"" . $Results[0]['lic_url'] . "\" target=_blank> " . $Results[0]['lic_url'] . "</a> <br>\n";
    $Text .= "</div>";
    $View->ShowView($Ftmp,"view",0,0,$Text);
    } // ViewLicense()

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
    $LicId = GetParm("lic",PARM_INTEGER);
    $LicIdSet = GetParm("licset",PARM_INTEGER);
    $Pfile = GetParm("pfile",PARM_INTEGER);
    if (!empty($LicId))
	{
	$this->ViewLicense($Pfile,$LicId,$LicIdSet);
	return;
	}
    if (empty($Pfile)) { return; }
    $ModBack = GetParm("modback",PARM_STRING);
    if (empty($ModBack)) { $ModBack='license'; }

    /* Load licenses for this file */
    $Results = $DB->Action("SELECT * FROM agent_lic_meta WHERE pfile_fk = $Pfile ORDER BY tok_pfile_start;");

    /* Process all licenses */
    if (count($Results) <= 0)
      {
      $View = &$Plugins[plugin_find_id("view")];
      $View->AddHighlight(-1,-1,'white',NULL,"No licenses found");
      }
    else
      {
      foreach($Results as $R)
	{
	if (empty($R['pfile_path'])) { continue; }
	if (!empty($R['phrase_text']))
		{
		$LicName = "Phrase: " . $R['phrase_text'];
		$RefURL = NULL;
		}
	else
		{
		$LicName = LicenseGetName($R['agent_lic_meta_pk'],1);
		$RefURL=Traceback() . "&lic=" . $R['lic_fk'] . "&licset=" . $R['tok_pfile_start'];
		}
	$this->ConvertLicPathToHighlighting($R,$LicName,$RefURL);
	}
      }

    $View->ShowView(NULL,$ModBack);
    return;
    } // Output()

  };
$NewPlugin = new ui_view_license;
$NewPlugin->Initialize();
?>
