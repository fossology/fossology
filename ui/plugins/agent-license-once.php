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

class agent_license_once extends FO_Plugin
{
  public $Name       = "agent_license_once";
  public $Title      = "One-Shot License Analysis";
  public $Version    = "1.0";
  public $Dependency = array("view","view-license");
  public $NoHTML     = 0;
  /** For anyone to access, without login, use: **/
  // public $DBaccess   = PLUGIN_DB_NONE;
  // public $LoginFlag  = 0;
  /** To require login access, use: **/
  public $DBaccess   = PLUGIN_DB_ANALYZE;
  public $LoginFlag  = 1;

  /*********************************************
   AnalyzeOne(): Analyze one uploaded file.
   *********************************************/
  function AnalyzeOne ($Highlight,$LicCache)
  {
    global $Plugins;
    global $AGENTDIR;
    $V = "";
    $View = &$Plugins[plugin_find_id("view")];
    $Bsam = array(); /* results from bSAM */

    /* move the temp file */
    $TempFile = $_FILES['licfile']['tmp_name'];
    $TempCache = $TempFile . ".bsam";
    // print "TempFile=$TempFile   TempCache=$TempCache\n";

    /* Create cache file */
    $Sys = "$AGENTDIR/Filter_License -O '$TempFile' > '$TempCache'";
    system($Sys);
    // print "Cached file $TempCache = " . filesize($TempCache) . " bytes.\n";

    /* Create bsam results */
    $Sys = "$AGENTDIR/bsam-engine -L 20 -A 0 -B 60 -G 15 -M 10 -E -O t '$TempCache' '$LicCache'";
    $Fin = popen($Sys,"r");
    $LicSummary = array();
    $LicNum = -1;
    $Denominator = 0;
    $Match = "0%";
    while(!feof($Fin))
      {
      $Line = fgets($Fin);
      // print "<pre>$Line</pre>";
      if (strlen($Line) > 0)
	{
	// print "<pre>$Line</pre>";
	if (substr($Line,0,4) == "A = ")
	  {
	  $Bsam = array(); /* clear the structure */
	  $Bsam['Aname'] = trim(substr($Line,4));
	  /* Initialize all other counters */
	  $LicNum++;
	  $Denominator = 0;
	  $Match = "0%";
	  }
	else if (substr($Line,0,4) == "B = ")
	  {
	  $Bsam['Bname'] = trim(substr($Line,4));
	  }
	else if (substr($Line,0,6) == "|A| = ")
	  {
	  $Bsam['Atok'] = intval(substr($Line,6));
	  }
	else if (substr($Line,0,6) == "|B| = ")
	  {
	  $Bsam['Btok'] = intval(substr($Line,6));
	  }
	else if (substr($Line,0,11) == "|Atotal| = ")
	  {
	  $Bsam['Atotal'] = intval(substr($Line,11));
	  }
	else if (substr($Line,0,11) == "|Btotal| = ")
	  {
	  $Bsam['Btotal'] = intval(substr($Line,11));
	  $Denominator = intval(substr($Line,11));
	  }
	else if (substr($Line,0,11) == "max(AxB) = ")
	  {
	  $Bsam['ABmatch'] = intval(substr($Line,11));
	  if ($Denominator > 0)
	    {
	    $Numerator = intval(substr($Line,11));
	    $Match = intval($Numerator*100 / $Denominator) . "%";
	    }
	  else { $Match = "0%"; }
	  }
	else if (substr($Line,0,8) == "Apath = ")
	  {
	  $Bsam['Apath'] = trim(substr($Line,8));
	  }
	else if (substr($Line,0,8) == "Bpath = ")
	  {
	  $Bsam['Bpath'] = trim(substr($Line,8));
	  /* This is the last record.  Generate the results. */
	  $Sys = "$AGENTDIR/licinspect -X ";
	  $Sys .= " '" . $Bsam['Aname'] . "' ";
	  $Sys .= " '" . $Bsam['Bname'] . "' ";
	  $Sys .= " '" . $Bsam['ABmatch'] . "' ";
	  $Sys .= " '" . $Bsam['Atotal'] . "' ";
	  $Sys .= " '" . $Bsam['Btotal'] . "' ";
	  $Sys .= " '" . $Bsam['Apath'] . "' ";
	  $Sys .= " '" . $Bsam['Bpath'] . "'";
	  $Fin2 = popen($Sys,"r");
	  $NameList = '';
	  while(!feof($Fin2))
	    {
	    $Line = fgets($Fin2);
	    $LicShort = trim($Line);
	    if (strlen($LicShort) > 0)
	      {
	      $LicSummary[$LicShort] = 1;
	      if (empty($NameList)) { $NameList = $LicShort; }
	      else { $NameList .= ", $LicShort"; }
	      }
	    }
	  pclose($Fin2);

	  /* Special case: if no "-style" and not >= 60%, then it must
	     have matched the terms! Give it a 100% match. */
	  if (!preg_match('/-style/',$NameList) && (intval($Match) < 60))
	    {
	    $Match='100%';
	    }

	  /* Add the namelist to the highlighting */
	  foreach(split(",",$Bsam['Apath']) as $Segment)
	    {
	    if (empty($Segment)) { continue; }
	    $Parts = split("-",$Segment,2);
	    if (empty($Parts[1])) { $Parts[1] = $Parts[0]; }
	    $View->AddHighlight($Parts[0],$Parts[1],$LicNum,$Match,$NameList);
	    $NameList = NULL;
	    }
	  }
	} /* while read a line */
      } /* while read from bsam */
    pclose($Fin);

    if ($Highlight)
      {
      $Fin = fopen($TempFile,"r");
      if ($Fin)
        {
	$View->SortHighlightMenu();
        print "<center>";
        print $View->GetHighlightMenu(-1);
        print "</center>";
        print "<hr />\n";
        $View->ShowText($Fin,0,1,-1);
        fclose($Fin);
	}
      }
    else
      {
      $LicSummary = array_keys($LicSummary);
      sort($LicSummary);
      $V .= implode(", ",$LicSummary);
      }

    /* Clean up */
    unlink($TempCache);
    return($V);
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
    if (!empty($_SESSION['User']))
      {
      if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)	// Debugging changes to license analysis
	{
	menu_insert("Main::Upload::One-Shot License",$this->MenuOrder,$this->Name,$this->MenuTarget);
	}
      if (@$_SESSION['UserLevel'] >= PLUGIN_DB_DEBUG)	// Debugging changes to license analysis
	{
	$URI = $this->Name . Traceback_parm_keep(array("format","item"));
	menu_insert("View::[BREAK]",100);
	menu_insert("View::One-Shot",101,$URI,"One-shot, real-time license analysis");
	menu_insert("View-Meta::[BREAK]",100);
	menu_insert("View-Meta::One-Shot",101,$URI,"One-shot, real-time license analysis");
	}
      }
  } // RegisterMenus()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    global $DATADIR;
    $LicCache = "$DATADIR/agents/License.bsam";
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* If this is a POST, then process the request. */
	$Highlight = GetParm('highlight',PARM_INTEGER); // may be null
	/* You can also specify the file by uploadtree_pk as 'item' */
	$Item = GetParm('item',PARM_INTEGER); // may be null
	if (file_exists(@$_FILES['licfile']['tmp_name']))
	  {
	  if ($_FILES['licfile']['size'] <= 1024*1024*10)
	    {
	    /* Size is not too big.  */
	    print $this->AnalyzeOne($Highlight,$LicCache) . "\n";
	    }
	  if (!empty($_FILES['licfile']['unlink_flag']))
	    { unlink($_FILES['licfile']['tmp_name']); }
	  return;
	  }
	else if (!empty($Item) && !empty($DB))
	  {
	  /* Get the pfile info */
	  $Results = $DB->Action("SELECT * FROM pfile
		INNER JOIN uploadtree ON uploadtree_pk = $Item
		AND pfile_pk = pfile_fk;");
	  if (!empty($Results[0]['pfile_pk']))
	    {
	    global $LIBEXECDIR;
	    $Highlight=1; /* processing a pfile? Always highlight. */
	    $Repo = $Results[0]['pfile_sha1'] . "." . $Results[0]['pfile_md5'] . "." . $Results[0]['pfile_size'];
	    $Repo = trim(shell_exec("$LIBEXECDIR/reppath files '$Repo'"));
	    $_FILES['licfile']['tmp_name'] = $Repo;
	    $_FILES['licfile']['size'] = $Results[0]['pfile_size'];
	    if ($_FILES['licfile']['size'] <= 1024*1024*10)
	      {
	      /* Size is not too big.  */
	      print $this->AnalyzeOne($Highlight,$LicCache) . "\n";
	      }
	    /* Do not unlink the or it will delete the repo file! */
	    if (!empty($_FILES['licfile']['unlink_flag']))
	      { unlink($_FILES['licfile']['tmp_name']); }
	    return;
	    }
	  }
	if (!empty($_FILES['licfile']['unlink_flag']))
	      { unlink($_FILES['licfile']['tmp_name']); }

	/* Display instructions */
	$V .= "This analyzer allows you to upload a single file for license analysis.\n";
	$V .= "The analysis is done in real-time.\n";
	$V .= "The limitations:\n";
	$V .= "<ul>\n";
	$V .= "<li>The analysis is done in real-time. Large files may take a while. This method is not recommended for files larger than a few hundred kilobytes.\n";
	$V .= "<li>Files that contain files are <b>not</b> unpacked. If you upload a 'zip' or 'deb' file, then the binary file will be scanned for licenses and nothing will likely be found.\n";
	$V .= "<li>Results are <b>not</b> stored. As soon as you get your results, your uploaded file is removed from the system.\n";
	$V .= "</ul>\n";

	/* Display the form */
	$V .= "<form enctype='multipart/form-data' method='post'>\n";
	$V .= "<ol>\n";
	$V .= "<li>Select the file to upload:<br />\n";
	$V .= "<input name='licfile' size='60' type='file' /><br />\n";
	$V .= "<b>NOTE</b>: Files larger than 100K will be discarded and not analyzed.<P />\n";
	$V .= "<li><input type='checkbox' name='highlight' value='1'>Check if you want to see the highlighted licenses.\n";
	$V .= "Unchecked returns a simple list that summarizes the identified license types.";
	$V .= "<P />\n";

	$V .= "</ol>\n";
	$V .= "<input type='hidden' name='showheader' value='1'>";
	$V .= "<input type='submit' value='Analyze!'>\n";
	$V .= "</form>\n";
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
$NewPlugin = new agent_license_once;
$NewPlugin->Initialize();
?>
