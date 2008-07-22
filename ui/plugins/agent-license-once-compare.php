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

class agent_license_once_compare extends FO_Plugin
{
  public $Name       = "agent_license_once_compare";
  public $Title      = "One-Shot License Comparison";
  public $Version    = "1.0";
  public $Dependency = array("db","view","view-license");
  public $NoHTML     = 0;
  /** To require login access, use: **/
  public $DBaccess   = PLUGIN_DB_ANALYZE;
  public $LoginFlag  = 1;

  /*********************************************
   AnalyzeOne(): Analyze one uploaded file.
   Returns 0 on success, !0 on failure.
   *********************************************/
  function AnalyzeOne ($Highlight,$LicList)
  {
    global $Plugins;
    global $AGENTDIR;
    global $DATADIR;
    $V = "";
    $View = &$Plugins[plugin_find_id("view")];
    $Bsam = array(); /* results from bSAM */

    /* move the temp file */
    $TempFile = $_FILES['licfile']['tmp_name'];
    $TempCache = $TempFile . ".bsam";
    $TempLics = $TempFile . ".lic.bsam";
    // print "TempFile=$TempFile   TempCache=$TempCache\n";

    /* Create cache file */
    $Sys = "$AGENTDIR/Filter_License -O '$TempFile' > '$TempCache'";
    system($Sys);
    // print "Cached file $TempCache = " . filesize($TempCache) . " bytes.\n";

    /* Create cache file for licenses */
    global $DB;
    $SQL = "";
    for($i=0; !empty($LicList[$i]); $i++)
      {
      if (!empty($SQL)) { $SQL .= " OR"; }
      $SQL .= " lic_id = '" . intval($LicList[$i]) . "'";
      }
    if (empty($SQL))
	{
	print "No comparison licenses found.";
	return(1);
	}
    $SQL = "SELECT DISTINCT lic_name FROM agent_lic_raw WHERE $SQL;";
    $Lics = $DB->Action($SQL);
    if (empty($Lics))
	{
	print "No comparison licenses found.";
	return(1);
	}
    chdir("$DATADIR/agents/licenses/");
    for($i=0; !empty($Lics[$i]['lic_name']); $i++)
	{
	$Filename = $Lics[$i]['lic_name'];
	$Sys = "$AGENTDIR/Filter_License -O '$Filename' >> '$TempLics'";
	system($Sys);
	}

    /* Create bsam results */
    $Sys = "$AGENTDIR/bsam-engine -L 20 -A 0 -B 60 -G 15 -M 10 -E -O t '$TempCache' '$TempLics'";
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
	  $Denominator += intval(substr($Line,6));
	  }
	else if (substr($Line,0,6) == "|B| = ")
	  {
	  $Bsam['Btok'] = intval(substr($Line,6));
	  $Denominator += intval(substr($Line,6));
	  }
	else if (substr($Line,0,11) == "max(AxB) = ")
	  {
	  $Bsam['ABmatch'] = intval(substr($Line,11));
	  if ($Denominator > 0)
	    {
	    $Numerator = intval(substr($Line,11));
	    $Match = intval($Numerator*200 / $Denominator) . "%";
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
	  $Sys .= " '" . $Bsam['Atok'] . "' ";
	  $Sys .= " '" . $Bsam['Btok'] . "' ";
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
    unlink($TempLics);

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
      if (@$_SESSION['UserLevel'] >= PLUGIN_DB_DEBUG)	// Debugging changes to license analysis
	{
	$URI = $this->Name . Traceback_parm_keep(array("format","pfile","item","ufile"));
	menu_insert("View::[BREAK]",100);
	menu_insert("View::Recompare",101,$URI,"One-shot, real-time license recomparison");
	menu_insert("View-Meta::[BREAK]",100);
	menu_insert("View-Meta::Recompare",101,$URI,"One-shot, real-time license recomparison");
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
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* If this is a POST, then process the request. */
	/* You can also specify the file by pfile_pk */
	$PfilePk = GetParm('pfile',PARM_INTEGER); // may be null
	$UfilePk = GetParm('ufile',PARM_INTEGER); // may be null
	$Item = GetParm('item',PARM_INTEGER); // may be null
	$LicList = GetParm('liclist',PARM_RAW); // may be null
	$Highlight=1; /* Always highlight. */
	if (is_array($LicList) && !empty($LicList[0]) && file_exists(@$_FILES['licfile']['tmp_name']))
	  {
	  if ($_FILES['licfile']['size'] <= 1024*1024*10)
	    {
	    /* Size is not too big.  */
	    print $this->AnalyzeOne($Highlight,$LicList) . "\n";
	    }
	  if (!empty($_FILES['licfile']['unlink_flag']))
	    { unlink($_FILES['licfile']['tmp_name']); }
	  return;
	  }
	else if (is_array($LicList) && !empty($LicList[0]) && !empty($PfilePk) && !empty($DB))
	  {
	  /* Get the pfile info */
	  $Results = $DB->Action("SELECT * FROM pfile WHERE pfile_pk = '$PfilePk';");
	  if (!empty($Results[0]['pfile_pk']))
	    {
	    global $LIBEXECDIR;
	    $Repo = $Results[0]['pfile_sha1'] . "." . $Results[0]['pfile_md5'] . "." . $Results[0]['pfile_size'];
	    $Repo = trim(shell_exec("$LIBEXECDIR/reppath files '$Repo'"));
	    $_FILES['licfile']['tmp_name'] = $Repo;
	    $_FILES['licfile']['size'] = $Results[0]['pfile_size'];
	    if ($_FILES['licfile']['size'] <= 1024*1024*10)
	      {
	      /* Size is not too big.  */
	      print $this->AnalyzeOne($Highlight,$LicList) . "\n";
	      }
	    /* Do not unlink the or it will delete the repo file! */
	    if (!empty($_FILES['licfile']['unlink_flag']))
	      { unlink($_FILES['licfile']['tmp_name']); }
	    return;
	    }
	  }

	/* Display instructions */
	$V .= "This analyzer allows you to upload a single file for license analysis and select the licenses to compare against.\n";
	$V .= "The analysis is done in real-time.\n";
	$V .= "<P>The limitations:\n";
	$V .= "<ul>\n";
	$V .= "<li>The analysis is done in real-time. Large files may take a while. This method is not recommended for files larger than a few hundred kilobytes.\n";
	$V .= "<li>The analysis is done in real-time. Selecting many licenses to compare against can take a long time.\n";
	$V .= "<li>Files that contain files are <b>not</b> unpacked. If you upload a 'zip' or 'deb' file, then the binary file will be scanned for licenses and nothing will likely be found.\n";
	$V .= "<li>Results are <b>not</b> stored. As soon as you get your results, your analysis is removed from the system.\n";
	$V .= "</ul>\n";

	$V .= "<form enctype='multipart/form-data' method='post'>\n";
	$V .= "<ol>\n";
	/* Display the form */
	if (empty($PfilePk))
	  {
	  $V .= "<li>Select the file to upload:<br />\n";
	  $V .= "<input name='licfile' size='60' type='file' /><br />\n";
	  $V .= "<b>NOTE</b>: Files larger than 100K will be discarded and not analyzed.<P />\n";
	  $V .= "<input type='hidden' name='pfile' value='$PfilePk'>";
	  $V .= "<input type='hidden' name='ufile' value='$UfilePk'>";
	  $V .= "<input type='hidden' name='item' value='$Item'>";
	  }
	else
	  {
	  $V .= "<li>This is the selected file to re-analyze:<br />\n";
	  $V .= Dir2Browse("license",$Item,$UfilePk) . "<P />\n";
	  }

	$V .= "<li>Select one or more licenses to compare against:<br>\n";
	$V .= "<table border=0><tr>";
	$V .= "<td><select size='10' multiple='multiple' id='liclist' name='liclist[]'>\n";
	$Lics = $DB->Action("SELECT DISTINCT lic_name,lic_id FROM agent_lic_raw ORDER BY lic_name;");
	for($i=0; !empty($Lics[$i]['lic_name']); $i++)
	  {
	  $V .= "<option value='" . $Lics[$i]['lic_id'] . "'>" . $Lics[$i]['lic_name'] . "</option>\n";
	  }
	$V .= "</select>\n";
	$Uri = "if (document.getElementById('liclist').value) { window.open('";
	$Uri .= Traceback_uri();
	$Uri .= "?mod=view-license";
	$Uri .= "&format=flow";
	$Uri .= "&lic=";
	$Uri .= "' + document.getElementById('liclist').value + '";
	$Uri .= "&licset=";
	$Uri .= "' + document.getElementById('liclist').value";
	$Uri .= ",'License','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes'); }";
	$V .= "</td><td>";
	$V .= "<a href='#' onClick=\"$Uri\">View</a>\n";
	$V .= "</td></tr></table>";

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
$NewPlugin = new agent_license_once_compare;
$NewPlugin->Initialize();
?>
