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

class agent_license_reanalyze extends FO_Plugin
{
  public $Name       = "agent_license_reanalyze";
  public $Title      = "Reanalyze License and Store Results";
  public $Version    = "1.0";
  public $Dependency = array("db","view","view-license");
  public $NoHTML     = 0;
  /** To require login access, use: **/
  public $DBaccess   = PLUGIN_DB_DEBUG;
  public $LoginFlag  = 1;

  /*********************************************
   AnalyzeOne(): Analyze one uploaded file.
   Returns 0 on success, !0 on failure.
   *********************************************/
  function AnalyzeOne ($Item)
  {
    global $Plugins;
    global $AGENTDIR;
    global $PROJECTSTATEDIR;
    global $DB;

    print "<pre>";

    /* Get the pfile information */
    $Results = $DB->Action("SELECT * FROM pfile
	INNER JOIN uploadtree ON uploadtree_pk = '$Item'
	AND pfile_pk = pfile_fk");
    $A = $Results[0]['pfile_sha1'] . "." . $Results[0]['pfile_md5'] . "." . $Results[0]['pfile_size'];
    $Akey = $Results[0]['pfile_pk'];
    $ASize = $Results[0]['pfile_size'];

    /* Remove old database information */
    print "Removing previous license information.\n"; flush();
    $DB->Action("BEGIN;");
    $DB->Action("DELETE FROM licterm_name WHERE pfile_fk = '$Akey';");
    $DB->Action("DELETE FROM agent_lic_meta WHERE pfile_fk = '$Akey';");
    $DB->Action("DELETE FROM agent_lic_status WHERE pfile_fk = '$Akey';");
    $DB->Action("COMMIT;");

    /* Don't analyze containers! */
    $Results = $DB->Action("SELECT * FROM uploadtree WHERE parent = '$UploadtreePk';");
    if (Iscontainer($Results[0]['ufile_mode']))
      {
      print "Container not processed.\n";
      }
    else
      {
      /* Run the analysis */
      $CmdOk = "echo \"akey='$Akey' a='$A' size='$ASize'\"";
      $CmdEnd = "2>&1 > /dev/null";

      $Cmd = "$CmdOk | $AGENTDIR/Filter_License $CmdEnd";
      print "Creating license cache\n"; flush();
      system($Cmd);

      $Results = $DB->Action("SELECT * FROM agent_lic_status WHERE pfile_fk = '$Akey' AND inrepository = TRUE AND processed = FALSE;");
      if (!empty($Results[0]['pfile_fk']))
	{
	$Cmd = "$CmdOk | $AGENTDIR/bsam-engine -L 20 -A 0 -B 60 -G 15 -M 10 -E -T license -O n -- - $PROJECTSTATEDIR/agents/License.bsam $CmdEnd";
	print "Finding licenses based on templates\n"; flush();
	system($Cmd);

	$Cmd = "$CmdOk | $AGENTDIR/licinspect $CmdEnd";
	print "Finding license names based on terms and keywords\n"; flush();
	system($Cmd);

	/* Update license count */
	$SQL = "SELECT count(*) AS count FROM licterm_name WHERE pfile_fk = $Akey;");
	$Results = $DB->Action($SQL);
	$Count = $Results[0]['count'];
	if (!empty($Count))
	  { 
	  $SQL = "UPDATE pfile SET pfile_liccount = '$Count' WHERE pfile_pk = $Akey;";
	  $DB->Action($SQL);
	  }
	}
      else
	{
	print "No licenses found.\n";
	}

      $Cmd = "$CmdOk | $AGENTDIR/filter_clean -s $CmdEnd";
      print "Cleaning up\n"; flush();
      system($Cmd);
      }

    /* Clean up */
    print "</pre>";
    return;
  } // AnalyzeOne()

  /*********************************************
   RegisterMenus(): Change the type of output
   based on user-supplied parameters.
   Returns 1 on success.
   *********************************************/
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); } // don't run
    $Highlight = GetParm('highlight',PARM_INTEGER);
    if (empty($Hightlight)) { $Highlight=0; }
    $ShowHeader = GetParm('showheader',PARM_INTEGER);
    if (empty($ShowHeader)) { $ShowHeader=0; }

    /* Check for a wget post (wget cannot post to a variable name) */
    if (empty($_POST['licfile']))
	{
	$Fin = fopen("php://input","r");
	$Ftmp = tempnam(NULL,"fosslic");
	$Fout = fopen($Ftmp,"w");
	while(!feof($Fin))
	  {
	  $Line = fgets($Fin);
	  fwrite($Fout,$Line);
	  }
	fclose($Fout);
	if (filesize($Ftmp) > 0)
	  {
	  $_FILES['licfile']['tmp_name'] = $Ftmp;
	  $_FILES['licfile']['size'] = filesize($Ftmp);
	  $_FILES['licfile']['unlink_flag'] = 1;
	  }
	else
	  {
	  unlink($Ftmp);
	  }
	fclose($Fin);
	}

    if (file_exists(@$_FILES['licfile']['tmp_name']) &&
       ($Highlight != 1) && ($ShowHeader != 1))
      {
      $this->NoHTML=1;
      /* default header is plain text */
      }

    /* Only register with the menu system if the user is logged in. */
    if (!empty($_SESSION['User']) && (GetParm("mod",PARM_STRING) == 'view-license'))
	{
	$URI = $this->Name . Traceback_parm(0);
	menu_insert("View::[BREAK]",200);
	menu_insert("View::Reanalyze",201,$URI,"Reanalyze license and store results");
	menu_insert("View-Meta::[BREAK]",200);
	menu_insert("View-Meta::Reanalyze",201,$URI,"Reanalyze license and store results");
	}
  } // RegisterMenus()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    global $Plugins;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* If this is a POST, then process the request. */
	/* You can also specify the file by pfile_pk */
	$UploadtreePk = GetParm('item',PARM_INTEGER); // may be null
	if (!empty($UploadtreePk))
	  {
	  $this->AnalyzeOne($UploadtreePk);
	  if (!empty($_FILES['licfile']['unlink_flag']))
            { unlink($_FILES['licfile']['tmp_name']); }
	  }
	/* Refresh the screen */
	$Uri = Traceback();
	$Uri = str_replace("agent_license_reanalyze","view-license",$Uri);
	print "<script>\n";
	print "function Refresh() { window.open('$Uri','_top'); }\n";
	print "window.setTimeout('Refresh()',2000);\n";
	print "</script>";
	print "Refreshing in 2 seconds...";
	break;
      case "Text":
	break;
      default:
	break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
  }
};
$NewPlugin = new agent_license_reanalyze;
$NewPlugin->Initialize();
?>
