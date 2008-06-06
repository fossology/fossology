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

class search_file extends FO_Plugin
  {
  var $Name       = "search_file";
  var $Title      = "Search for File";
  var $Version    = "1.0";
  var $MenuList   = "Search";
  var $Dependency = array("db","view","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   GetUfileFromName(): Given a name, return all ufiles.
   ***********************************************************/
  function GetUfileFromName($Filename,$Page, $ContainerOnly=1)
    {
    global $DB;
    $Max = 50;
    $Filename = str_replace("'","''",$Filename); // protect DB
    $Terms = split("[[:space:]][[:space:]]*",$Filename);
    $SQL = "SELECT ufile.ufile_name, uploadtree.* FROM ufile,uploadtree,pfile
	WHERE uploadtree.ufile_fk=ufile.ufile_pk ";
    $SQL .= " AND pfile_pk=uploadtree.pfile_fk";
    if ($ContainerOnly) $SQL .= " AND ((uploadtree.ufile_mode & (1<<29)) != 0) ";
    foreach($Terms as $Key => $T)
	{
	$SQL .= " AND ufile.ufile_name like '$T'";
	}
    $Offset = $Page * $Max;
    $SQL .= " ORDER BY pfile_size DESC LIMIT $Max OFFSET $Offset;";
    $Results = $DB->Action($SQL);
    $V = "";
    $Count = count($Results);

    if (($Page > 0) || ($Count >= $Max))
      {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&filename=" . urlencode($Filename);
      $VM = MenuEndlessPage($Page, ($Count >= $Max),$Uri) . "<P />\n";
      $V .= $VM;
      }
    else
      {
      $VM = "";
      }

    if ($Count == 0)
	{
	$V .= "No results.\n";
	return($V);
	}

    $V .= Dir2FileList($Results,"browse","view",$Page*$Max + 1);

    /* put page menu at the bottom, too */
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
    } // GetUfileFromName()

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    $URI = $this->Name;
    menu_insert("Search::Filename",0,$URI,"Search based on filename");
    } // RegisterMenus()

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	$V .= menu_to_1html(menu_find("Search",$MenuDepth),1);

	$Filename = GetParm("filename",PARM_STRING);
	$Page = GetParm("page",PARM_INTEGER);
	$allfiles = GetParm("allfiles",PARM_STRING);
	$Uri = preg_replace("/&filename=[^&]*/","",Traceback());
	$Uri = preg_replace("/&page=[^&]*/","",$Uri);

	$V .= "You can use '%' as a wild-card.\n";
	$V .= "<form action='$Uri' method='POST'>\n";
	$V .= "<ul>\n";
	$V .= "<li>Enter the filename to find:<P>";
	$V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
	$V .= "<li>By default only containers (rpms, tars, isos, etc) are shown.<P>";
	$V .= "<INPUT type='checkbox' name='allfiles' value='1'";
	if ($allfiles == '1') { $V .= " checked"; }
	$V .= "> Show All Files\n";
	$V .= "</ul>\n";
	$V .= "<input type='submit' value='Search!'>\n";
	$V .= "</form>\n";

	if (!empty($Filename))
	  {
	  if (empty($Page)) { $Page = 0; }
	  if (empty($allfiles)) { $ContainerOnly = 1; }
	  $V .= "<hr>\n";
	  $V .= "<H2>Files matching " . htmlentities($Filename) . "</H2>\n";
	  $V .= $this->GetUfileFromName($Filename,$Page, $ContainerOnly);
	  }
        break;
      case "Text":
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    } // Output()

  };
$NewPlugin = new search_file;
$NewPlugin->Initialize();

?>
