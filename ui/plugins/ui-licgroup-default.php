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

 -----------------------------------------------------

 The Javascript code to move values between tables is based
 on: http://www.mredkj.com/tutorials/tutorial_mixed2b.html
 The page, on 28-Apr-2008, says the code is "public domain".
 His terms and conditions (http://www.mredkj.com/legal.html)
 says "Code marked as public domain is without copyright, and
 can be used without restriction."
 This segment of code is noted in this program with "mredkj.com".
 ***********************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************
 Plugin for creating License Groups
 *************************************************/
class licgroup_default extends FO_Plugin
  {
  var $Name       = "license_groups_default";
  var $Title      = "Create Default License Groups";
  var $Version    = "1.0";
  var $MenuList   = "Organize::License::Default Groups";
  var $Dependency = array("db","license_groups_manage");
  var $DBaccess   = PLUGIN_DB_USERADMIN;
  var $LoginFlag  = 1; /* must be logged in to use this */

  var $DefaultName = "Similar Text";

  /***********************************************************
   DefaultGroups(): Create a default "family" of groups based
   on the installed raw directories.
   ***********************************************************/
  function DefaultGroups	()
    {
    global $DB;
    global $Plugins;

    $LG = &$Plugins[plugin_find_id("license_groups_manage")];

    /* Get the list of licenses */
    $Lics = $DB->Action("SELECT lic_pk,lic_name FROM agent_lic_raw WHERE lic_pk=lic_id ORDER BY lic_name;");

    /* Create default groups */
    /** This will delete and blow away old groups **/
    $GroupPk = array();
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Lics[$i]['lic_name'] = "/" . $this->DefaultName . "/" . $Lics[$i]['lic_name'];
      $Name = preg_replace("@/[^/]*$@","",$Lics[$i]['lic_name']);
      foreach(split('/',$Name) as $N)
        {
	if (empty($N)) { continue; }
	if (empty($GroupPk[$N]))
	  {
          $LG->LicGroupInsert(-1,$N,$N,'#ffffff',NULL,NULL);
	  }
	$N1 = str_replace("'","''",$N);
	$Results = $DB->Action("SELECT licgroup_pk FROM licgroup WHERE licgroup_name = '$N1';");
	$GroupPk[$N] = $Results[0]['licgroup_pk'];
	}
      }

    /* Now for the fun part: Populate each of the default groups */
    foreach($GroupPk as $GroupName => $Val)
      {
      $LicList = array(); /* licenses in this group */
      $GrpList = array(); /* groups in this group */
      /* For each group, find all groups that contain the same path.
         Then store the group number and any licenses. */
      for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
        {
	/* Remove filename */
	$Name = preg_replace("@/[^/]*$@","",$Lics[$i]['lic_name']);
	/* Check if it matches a license */
	if (preg_match("@/$GroupName\$@",$Name))
	  {
	  $LicList[] = $Lics[$i]['lic_pk'];
	  }
	/* Check if it matches a group containing a group */
	if (preg_match("@/$GroupName/@",$Name))
	  {
	  $Member = preg_replace("@^.*/$GroupName/@","",$Name);
	  $Member = preg_replace("@/.*@","",$Member);
	  $GrpList[] = $GroupPk[$Member];
	  }
	}
      /* Save the license info */
      $GrpList = array_unique($GrpList);
      sort($GrpList);
      $LG->LicGroupInsert(-1,$GroupName,$GroupName,'#ffffff',$LicList,$GrpList);
      }
    print "Created " . count($GroupPk) . " default license groups,";
    print " containing " . count($Lics) . " licenses.\n";
    print "All default groups are stored under the license group '" . $this->DefaultName . "'.";
    print "<hr>\n";
    } // DefaultGroups()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   (OutputOpen and Output are separated so one plugin
   can call another plugin's Output.)
   This uses $OutputType.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
   ***********************************************************/
  function Output()
    {
    global $Plugins;

    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$Init = GetParm('init',PARM_STRING);
	if ($Init == 1)
	  {
	  $rc = $this->DefaultGroups();
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Default license groups created.')\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }
	$V .= "<form method='post'>\n";
	$V .= "License groups provide organization for licenses.\n";
	$V .= "By selecting the 'Create' button, you will initialize the license groups.\n";
	$V .= "This initialization will create many default groups, based on a similar-text heirarchy.\n";
	$V .= "All of these default groups are organized under the parent group '" . $this->DefaultName . "'.\n";
	$V .= "<ul>\n";
	$V .= "<li>The default license groups are based on a heirarchy of similar text.\n";
	$V .= "<li>The default license groups are <b>NOT</b> a recommendation or legal interpretation.\n";
	$V .= "In particular, licenses may have similar text but very different legal meanings.\n";
	$V .= "<li>If you create these default groups twice, then any modification you made to the default groups <b>will be lost</b>.\n";
	$V .= "<li>Creating default groups will not impact any new groups you created.\n";
	$V .= "</ul>\n";
	$V .= "If you are still sure you want to do this:<P/>\n";
	$V .= "<input type='hidden' name='init' value='1'>";
	$V .= "<input type='submit' value='Create!'>";
	$V .= "<P/>\n";
	$V .= "After the default groups are created, you can modify, edit, or delete the default groups with the ";
	$P = &$Plugins[plugin_find_id("license_groups_manage")];
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $P->Name . "'>" . $P->Title . "</a>";
	$V .= " menu option.\n";
	$V .= "You can also use the ";
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $P->Name . "'>" . $P->Title . "</a>";
	$V .= " menu option to add new groups.\n";
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
$NewPlugin = new licgroup_default;
$NewPlugin->Initialize();
?>
