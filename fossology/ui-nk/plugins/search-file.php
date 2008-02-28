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

class search_file extends Plugin
  {
  var $Name       = "search_file";
  var $Version    = "1.0";
  var $MenuList   = "Tools::Search::Filename";
  var $Dependency = array("db","view","browse");
  var $DBaccess   = PLUGIN_DB_READ;

  /***********************************************************
   GetUfileFromName(): Given a pfile_pk, return all ufiles.
   ***********************************************************/
  function GetUfileFromName($Filename)
    {
    global $DB;
    $Filename = str_replace("'","''",$Filename); // protect DB
    $SQL = "SELECT * FROM ufile
	INNER JOIN uploadtree ON uploadtree.ufile_fk = ufile.ufile_pk
	WHERE ufile_name like '$Filename';";
    $Results = $DB->Action($SQL);
    $V = "";
    foreach($Results as $R)
	{
	if (empty($R['pfile_fk'])) { continue; }
	$V .= Dir2Browse("browse",$R['uploadtree_pk'],-1,"view") . "<P />\n";
	}
    return($V);
    } // GetUfileFromName()

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

	$Filename = GetParm("filename",PARM_STRING);

	$V .= "<H1>Search for Filename</H1>";
	$V .= "You can use '%' as a wild-card.\n";
	$V .= "<form method='post'>\n";
	$V .= "<ul>\n";
	$V .= "<li>Enter the filename to find:<P>";
	$V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
	$V .= "</ul>\n";
	$V .= "<input type='submit' value='Search!'>\n";
	$V .= "</form>\n";

	if (!empty($Filename))
	  {
	  $V .= "<hr>\n";
	  $V .= "<H2>Files matching " . htmlentities($Filename) . "</H2>\n";
	  $V .= $this->GetUfileFromName($Filename);
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
