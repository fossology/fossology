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
  /** For anyone to access, without login, use:
  public $DBaccess   = PLUGIN_DB_NONE;
  public $LoginFlag  = 0;
  **/
  public $DBaccess   = PLUGIN_DB_ANALYZE;
  public $LoginFlag  = 1;
  public $NoHTML     = 0;

  /*********************************************
   AnalyzeOne(): Analyze one uploaded file.
   *********************************************/
  function AnalyzeOne ($Highlight)
  {
    global $Plugins;
    global $AGENTDIR;
    global $DATADIR;
    $V = "";
    $View = &$Plugins[plugin_find_id("view")];

    /* move the temp file */
    $TempFile = $_FILES['licfile']['tmp_name'];
    $TempCache = $TempFile . ".bsam";
    // print "TempFile=$TempFile   TempCache=$TempCache\n";

    /* Create cache file */
    $Sys = "$AGENTDIR/Filter_License -O '$TempFile' > '$TempCache'";
    system($Sys);
    // print "Cached file $TempCache = " . filesize($TempCache) . " bytes.\n";

    /* Create bsam results */
    $Sys = "$AGENTDIR/bsam-engine -L 20 -A 0 -B 60 -G 10 -M 10 -E -O t '$TempCache' '$DATADIR/agents/License.bsam'";
    $Fin = popen($Sys,"r");
    $LicSummary = array();
    $LicName = "";
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
	if (substr($Line,0,4) == "B = ")
	  {
	  $LicNum++;
	  $LicName = trim(substr($Line,4));
	  $LicShort = preg_replace("@^.*/@","",$LicName);
	  /* Really simplify the data, per Paul's request */
	  $LicShort = preg_replace("@ variant.*@","",$LicShort);
	  $LicShort = preg_replace("@ reference.*@","",$LicShort);
	  $LicShort = preg_replace("@ short.*@","",$LicShort);
	  $LicShort = preg_replace("@^BSD .*@","BSD",$LicShort);
	  $LicShort = preg_replace("@^MIT .*@","MIT",$LicShort);
	  $LicShort = preg_replace("@^.*Phrase:.*@","Phrase",$LicShort);
	  $LicShort = trim($LicShort);
	  $LicSummary[$LicShort] = 1;
	  $Denominator = 0;
	  $Match = "0%";
	  }
	else if (substr($Line,0,6) == "|A| = ")
	  {
	  $Denominator += intval(substr($Line,6));
	  }
	else if (substr($Line,0,6) == "|B| = ")
	  {
	  $Denominator += intval(substr($Line,6));
	  }
	else if (substr($Line,0,11) == "max(AxB) = ")
	  {
	  if ($Denominator > 0)
	    {
	    $Numerator = intval(substr($Line,11));
	    $Match = intval($Numerator*200 / $Denominator) . "%";
	    }
	  else { $Match = "0%"; }
	  }
	else if (substr($Line,0,8) == "Apath = ")
	  {
	  $Line = trim(substr($Line,8));
	  $Name = preg_replace("@^.*/@","",$LicName);
	  foreach(split(",",$Line) as $Segment)
	    {
	    if (empty($Segment)) { continue; }
	    $Parts = split("-",$Segment,2);
	    if (empty($Parts[1])) { $Parts[1] = $Parts[0]; }
	    $View->AddHighlight($Parts[0],$Parts[1],$LicNum,$Match,$Name);
	    $Name = NULL;
	    }
	  }
	}
      }
    pclose($Fin);

    if ($Highlight)
      {
      $Fin = fopen($TempFile,"r");
      print "<center>";
      print $View->GetHighlightMenu(-1);
      print "</center>";
      print "<hr />\n";
      $View->ShowText($Fin,0,1,-1);
      fclose($Fin);
      }
    else
      {
      $LicSummary = array_keys($LicSummary);
      sort($LicSummary);
      $V .= implode(", ",$LicSummary);
      }

    /* Clean up */
    unlink($TempCache);
    unlink($TempFile);
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
      menu_insert("Main::Jobs::Analyze::One-Shot License",$this->MenuOrder,$this->Name,$this->MenuTarget);
      }
  } // RegisterMenus()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* If this is a POST, then process the request. */
	$Highlight = GetParm('highlight',PARM_INTEGER); // may be null
	if (file_exists(@$_FILES['licfile']['tmp_name']))
	  {
	  if ($_FILES['licfile']['size'] <= 1024*1024*10)
	    {
	    /* Size is not too big.  */
	    print $this->AnalyzeOne($Highlight) . "\n";
	    }
	  @unlink($_FILES['licfile']['tmp_name']);
	  return;
	  }

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
