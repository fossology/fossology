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

class core_debug_repo extends FO_Plugin
  {
  var $Name       = "debug_repo";
  var $Title      = "Debug Repository";
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug::Debug Repository";
  var $Dependency = array("db","view","browse");
  var $DBaccess   = PLUGIN_DB_DEBUG;

  /***********************************************************
   GetUfileFromPfile(): Given a pfile_pk, return all ufiles.
   ***********************************************************/
  function GetUfileFromPfile($Pfilepk)
    {
    global $DB;
    $SQL = "SELECT * FROM pfile
	INNER JOIN ufile ON pfile_pk = '$Pfilepk'
	  AND ufile.pfile_fk = pfile.pfile_pk
	INNER JOIN uploadtree ON uploadtree.ufile_fk = ufile.ufile_pk;";
    $Results = $DB->Action($SQL);
    $V = "";
    foreach($Results as $R)
	{
	if (empty($R['pfile_pk'])) { continue; }
	$V .= Dir2Browse("browse",$R['uploadtree_pk'],$R['ufile_fk'],"view") . "<P />\n";
	}
    return($V);
    } // GetUfileFromPfile()

  /***********************************************************
   GetUfileFromRepo(): Given a sha1.md5.len, return all ufiles.
   ***********************************************************/
  function GetUfileFromRepo($Repo)
    {
    /* Split repo into Sha1, Md5, and Len */
    $Repo = strtoupper($Repo);
    list($Sha1,$Md5,$Len) = split("[.]",$Repo,3);
    $Sha1 = preg_replace("/[^A-F0-9]/","",$Sha1);
    $Md5 = preg_replace("/[^A-F0-9]/","",$Md5);
    $Len = preg_replace("/[^0-9]/","",$Len);
    if (strlen($Sha1) != 40) { return; }
    if (strlen($Md5) != 32) { return; }
    if (strlen($Len) < 1) { return; }

    /* Get the pfile */
    global $DB;
    $SQL = "SELECT pfile_pk FROM pfile
	WHERE pfile_sha1 = '$Sha1'
	AND pfile_md5 = '$Md5'
	AND pfile_size = '$Len';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['pfile_pk'])) { return; }
    return($this->GetUfileFromPfile($Results[0]['pfile_pk']));
    } // GetUfileFromRepo()

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

	$Akey = GetParm("akey",PARM_INTEGER);
	$A = GetParm("a",PARM_STRING);

	$V .= "Given a pfile_pk or repository id, return the list of files.\n";
	$V .= "<form method='post'>\n";
	$V .= "<ul>\n";
	$V .= "<li>Enter the pfile key: ";
	$V .= "<INPUT type='text' name='akey' size='8' value='" . htmlentities($Akey) . "'><P>\n";
	$V .= "<li>Enter the repository key (sha1.md5.len):<br>";
	$V .= "<INPUT type='text' name='a' size='80' value='" . htmlentities($A) . "'>\n";
	$V .= "</ul>\n";
	$V .= "<input type='submit' value='Find!'>\n";
	$V .= "</form>\n";

	if (!empty($Akey))
	  {
	  $V .= "<hr>\n";
	  $V .= "<H2>Files associated with pfile " . htmlentities($Akey) . "</H2>\n";
	  $V .= $this->GetUfileFromPfile($Akey);
	  }

	if (!empty($A))
	  {
	  $V .= "<hr>\n";
	  $V .= "<H2>Files associated with repository item " . htmlentities($A) . "</H2>\n";
	  $V .= $this->GetUfileFromRepo($A);
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
$NewPlugin = new core_debug_repo;
$NewPlugin->Initialize();

?>
