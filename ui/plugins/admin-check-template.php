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

class admin_check_template extends FO_Plugin
  {
  var $Name       = "admin_check_template";
  var $Version    = "1.0";
  var $Title      = "License Template Check";
  var $MenuList   = "Admin::Database::Check Templates";
  var $Dependency = array("db","agent_license");
  var $DBaccess   = PLUGIN_DB_USERADMIN;

  /************************************************
   ReadBsamUnique(): Load every "Unique" value from the
   License.bsam file.  Return them in an array.
   ************************************************/
  function ReadBsamUnique	($Verbose=0)
    {
    global $DATADIR;
    $Fin = @fopen("$DATADIR/agents/License.bsam","rb");
    if (!$Fin)
      {
      print "Failed to read License.bsam<br>\n";
      return;
      }

    /* Parse the file, but only read the unique stuff */
    $Hash = array();
    $Name = "";
    $Section = "";
    $Bad=0;
    if ($Verbose) { print "<H3>Checking for duplicate errors within the license templates</H3>\n"; }
    while(!feof($Fin))
      {
      $Type = ord(fgetc($Fin))*256 + ord(fgetc($Fin));
      $Len = ord(fgetc($Fin))*256 + ord(fgetc($Fin));
      if ($Len > 0) { $Data = trim(fread($Fin,$Len)); }
      else { $Data = ""; }
      if ($Len % 2 != 0) { fgetc($Fin); }
      /* 0x0001 == Filename */
      if ($Type == 0x0001) { $Name=$Data; }
      /* 0x0101 == Section/Function name */
      if ($Type == 0x0101) { $Section=$Data; }
      /* 0x0110 == Unique checksum */
      if (!empty($Hash[$Data]))
	{
	if ($Verbose) { print "Duplicate: $Name ($Section) <i>is</i> " .  $Hash[$Data] . "<br>\n"; }
	$Bad++;
	}
      if ($Type == 0x0110) { $Hash[$Data]="$Name ($Section)"; }
      }

    fclose($Fin);
    if ($Verbose) { print "<br>Total duplicate templates that need cleaning up: $Bad</b><br>\n"; }
    return($Hash);
    } // ReadBsamUnique()

  /************************************************
   ReadDBUnique(): Load every "Unique" value from the
   License.bsam file.  Flag every one of them that
   does not exist in License.bsam.
   Return the array of lic_pk values that no longer exist.
   ************************************************/
  function ReadDBUnique	($Bsam,$Verbose=0)
    {
    global $DB;
    $SQL = "SELECT lic_pk,lic_id,lic_unique,lic_name,lic_section FROM agent_lic_raw ORDER BY lic_name;";
    $Results = $DB->Action($SQL);
    if ($Verbose) { print "<H3>Checking for obsolete license templates</H3>\n"; }
    $BadList = array();
    if ($Verbose) { print "<ol>\n"; }
    for($i=0; !empty($Results[$i]['lic_pk']); $i++)
      {
      $Uniq = trim($Results[$i]['lic_unique']);
      if (empty($Bsam[$Uniq]) && ($Results[$i]['lic_unique'] != "1"))
	{
	if ($Verbose)
	  {
	  print "<li>Obsolete: " . $Results[$i]['lic_pk'] . ": ";
	  print $Results[$i]['lic_name'] . " (" . $Results[$i]['lic_section'] . ")<br>\n";
	  }
	$BadList[] = $Results[$i]['lic_pk'];
	}
      }
    if ($Verbose) { print "</ol>\n"; }

    /* Check for any other licenses found in the same obsolete template. */
    /** This should not be the case if new templates are created. **/
    $OldCount=count($BadList);
    for($i=0; !empty($Results[$i]['lic_pk']); $i++)
      {
      $Bad = $DB->Action("SELECT lic_pk FROM agent_lic_raw WHERE lic_id = '" . $Results[$i]['lic_id'] . "' AND lic_pk != lic_pk;");
      for($j=0; !empty($Bad[$j]['lic_pk']); $j++)
        {
	if (!in_array($Bad[$j]['lic_pk'],$BadList))
	  {
	  $BadList[] = $Bad[$j]['lic_pk'];
	  }
	}
      }
    $NewCount=count($BadList);

    if ($Verbose)
      {
      print "<b>Total obsolete template records that need cleaning up: " . count($BadList) . "</b><br>\n";
      if ($OldCount != $NewCount)
        {
	print "<font color='red'>Reinstall licenses before doing the cleanup.</font><br>\n";
	}
      }
    return($BadList);
    } // ReadDBUnique()

  /************************************************
   FindPfiles(): Given a list of lic_pk, identify
   the pfiles that need to be re-analyzed.
   Returns the list of pfiles.
   ************************************************/
  function FindPfiles	($List, $Verbose=0)
    {
    global $DB;

    $PfileList=array();

    /* Get pfiles to fix */
    $i = 0;
    /* Loop through sql so that the max sql stmt size isn't reached */
    do
    {
    $SQL = "SELECT DISTINCT pfile_fk FROM agent_lic_meta WHERE";
    $start = $i;
    for(; !empty($List[$i]); $i++)
    {
      if ($i > 0) { $SQL .= " OR"; }
      $SQL .= " lic_fk='" . $List[$i] . "'";
      if (($i % 50) == 0) break;
    }
    $end = $i;
    $SQL .= ";";
    $Pfiles = $DB->Action($SQL);

    for($j=0; !empty($Pfiles[$j]); $j++)
      {
      $PfileList[] = $Pfiles[$j]['pfile_fk'];
      }

    $i++;

    } while (!empty($List[$i]));

    return($PfileList);
    } // FindPfiles()

  /************************************************
   FindUploads(): Given a list of lic_pk, count
   the number of pfiles and projects that need to
   be re-analyzed.
   Returns the upload_pk values impacted.
   ************************************************/
  function FindUploads	($List, $Verbose=0)
    {
    global $DB;

    $VerboseInit = 1;
    $UploadList=array();
    /* Loop through sql so that the max sql stmt size isn't reached */
    $i = 0;
    do
    {
      $start = $i;
      /* Get uploads to fix */
      $SQL = "SELECT DISTINCT upload_fk,upload_filename FROM uploadtree
          INNER JOIN upload ON upload_pk=upload_fk
          INNER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk=uploadtree.pfile_fk
          WHERE";

      for(; !empty($List[$i]); $i++)
      {
        if ($i > 0) { $SQL .= " OR"; }
        $SQL .= " lic_fk='" . $List[$i] . "'";
        if (($i % 50) == 0) break;
      }
      $end = $i;
      $SQL .= " ORDER BY upload_filename";
      $SQL .= ";";
      $Uploads = $DB->Action($SQL);

      for($j=0; !empty($Uploads[$j]); $j++)
      {
        $UploadList[] = $Uploads[$j]['upload_fk'];
        if ($Verbose)
        {
          if ($VerboseInit)
          { 
            print "<H3>Checking for Uploads to Re-analyze</H3>\n";
            print "The impacted uploads:<br>\n";
            print "<ul>\n";
            $VerboseInit = 0;
          }
          if (!empty($Uploads[$j]['upload_filename']))
            print "<li>" . htmlentities($Uploads[$j]['upload_filename']) . "\n";
        }
      }
      $i++;
    } while (!empty($List[$i]));

    if (!$VerboseInit) print "</ul>\n";

    return($UploadList);
    } // FindUploads()

  /************************************************
   Cleanup(): Remove obsolete templates, reset files
   for analysis, and reschedule jobs.
   ************************************************/
  function Cleanup	($OldTemplates, $PfileList, $UploadList)
    {
    global $DB;
    global $Plugins;

    $DB->Action("BEGIN;");

    /* Delete the metadata for the licenses, in batches */
    print "Resetting license analysis for " . count($PfileList) . " files.<br>\n";
    $SQL="";
    for($i=0; !empty($PfileList[$i]); $i++)
      {
      if (!empty($SQL) && ($i % 40 == 0))
	{
	$DB->Action("DELETE FROM licterm_name WHERE $SQL;");
	$DB->Action("DELETE FROM agent_lic_meta WHERE $SQL;");
	$DB->Action("DELETE FROM agent_lic_status WHERE $SQL;");
	$SQL="";
	}
      if (!empty($SQL)) { $SQL .= " OR"; }
      $SQL .= " pfile_fk='" . $PfileList[$i] . "'";
      }
    if (!empty($SQL))
	{
	$DB->Action("DELETE FROM licterm_name WHERE $SQL;");
	$DB->Action("DELETE FROM agent_lic_meta WHERE $SQL;");
	$DB->Action("DELETE FROM agent_lic_status WHERE $SQL;");
	}

    /* Delete the licenses */
    print "Removing " . count($OldTemplates) . " obsolete license templates.<br>\n";
    $SQL="";
    $SQL2="";
    for($i=0; !empty($OldTemplates[$i]); $i++)
      {
      if (!empty($SQL) && ($i % 40 == 0))
	{
	$DB->Action("DELETE FROM agent_lic_raw WHERE $SQL;");
	$DB->Action("DELETE FROM licgroup_lics WHERE $SQL2;");
	$DB->Action("DELETE FROM licterm_maplic WHERE $SQL2;");
	$SQL="";
	$SQL2="";
	}
      if (!empty($SQL)) { $SQL .= " OR"; $SQL2 .= " OR"; }
      $SQL .= " lic_id='" . $OldTemplates[$i] . "'";
      $SQL .= " OR lic_pk='" . $OldTemplates[$i] . "'";
      $SQL2 .= " lic_fk='" . $OldTemplates[$i] . "'";
      }
    if (!empty($SQL))
	{
	$DB->Action("DELETE FROM agent_lic_raw WHERE $SQL;");
	$DB->Action("DELETE FROM licgroup_lics WHERE $SQL2;");
	$DB->Action("DELETE FROM licterm_maplic WHERE $SQL2;");
	}
    $DB->Action("COMMIT;");

    /* Reset the jobs */
    print "Resetting " . count($UploadList) . " license analysis jobs.<br>\n";
    $Analyze = &$Plugins[plugin_find_id("agent_license")];
    for($i=0; !empty($UploadList[$i]); $i++)
      {
      $JobPk = JobFindKey($UploadList[$i],"license");
      /* If no job, then don't reschedule it. */
      if ($JobPk >= 0)
        {
	/* Clear the job, then reschedule it */
	JobChangeStatus($JobPk,"delete");
	$Analyze->AgentAdd($UploadList[$i]);
	}
      }
    } // Cleanup()

  /************************************************
   Output(): Generate output.
   ************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $DB;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	if (GetParm('cleanup',PARM_INTEGER) == 1)
	  {
	  $BsamUniq = $this->ReadBsamUnique(0);
	  $BadUniq = $this->ReadDBUnique($BsamUniq,0);
	  $PfileList = $this->FindPfiles($BadUniq);
	  $UploadList = $this->FindUploads($BadUniq);
	  $this->Cleanup($BadUniq,$PfileList,$UploadList);
	  }

	$BsamUniq = $this->ReadBsamUnique(1);
	$BadUniq = $this->ReadDBUnique($BsamUniq,1);
	$PfileList = $this->FindPfiles($BadUniq);
	$UploadList = $this->FindUploads($BadUniq,1);
	$V .= "<hr>\n";
	$V .= "<H3>Clean-Up</H3>\n";
	$V .= "<b>Total obsolete templates to remove: " . count($BadUniq) . "</b><br>\n";
	$V .= "<b>Total pfiles to re-analyze: " . count($PfileList) . "</b><br>\n";
	$V .= "<b>Total uploads to re-analyze: " . count($UploadList) . "</b><P>\n";
	$V .= "Cleaning up obsolete analysis requires removing the old analysis and rescheduling the projects for license analysis.\n";
	$V .= "Only the files linked to the obsolete templates need to be analyzed.\n";
	$V .= "This will not re-analyze every file in the uploaded package.\n";
	$V .= "<form method='POST'>";
	$V .= "<P><input type='checkbox' name='cleanup' value='1'> Check to cleanup and re-analyze.\n";
	$V .= "<P><input type='submit' value='Clean!'>\n";
	$V .= "</form>";
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
$NewPlugin = new admin_check_template;
$NewPlugin->Initialize();
?>
