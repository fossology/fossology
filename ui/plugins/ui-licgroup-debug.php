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

/************************************************
 Plugin for License Groups
 *************************************************/
class licgroup_debug extends FO_Plugin
  {
  var $Name       = "license_groups_debug";
  var $Title      = "License Groups Debug";
  var $Version    = "1.0";
  var $MenuList   = "Help::Debug::Debug License Groups";
  var $Dependency = array("db","licgroup");
  var $DBaccess   = PLUGIN_DB_WRITE;
  var $LoginFlag  = 0;

  var $LicName = array();
  var $GrpName = array();
  var $LicGroupPlugin;

  /***********************************************************
   DrawGroupTree(): This is recursive!
   ***********************************************************/
  function DrawGroupTree	($Group)
    {
    print "<li>Group: ";
    if (!empty($this->LicGroupPlugin->GrpInGroup[$Group]['head']))
      {
      print "[HEAD] ";
      }
    if (!empty($this->LicGroupPlugin->GrpInGroup[$Group]['tail']))
      {
      print "[TAIL] ";
      }
    print htmlentities($this->GrpName[$Group]) . "\n";
    if (!empty($this->LicGroupPlugin->GrpInGroup[$Group]))
      {
      print "<ul>\n";
      foreach($this->LicGroupPlugin->GrpInGroup[$Group] as $G => $g)
	{
	if (substr($G,0,1) == 'g')
	  {
	  if ($g == 0)
	    {
	    print "<li><b><font color='red'>loop</font></b> to group " . htmlentities($this->GrpName[$G]) . "\n";
	    }
	  else
	    {
	    $this->DrawGroupTree($G);
	    }
	  }
	else if (substr($G,0,1) == 'l')
	  {
	  if (!empty($this->LicGroupPlugin->LicInGroup[$Group][$G]))
	    {
	    print "<li>License: ";
	    }
	  else
	    {
	    print "<li>Inherited license: ";
	    }
	  print htmlentities($this->LicName[$G]) . "\n";
	  }
	}
      print "</ul>\n";
      }
    } // DrawGroupTree()
  
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
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    global $Plugins;
    $V="";

    /* Load the DB info */
    $SQL = "SELECT lic_pk,lic_name FROM agent_lic_raw WHERE lic_id = lic_pk;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['lic_pk']); $i++)
      {
      $this->LicName['l'.$Results[$i]['lic_pk']] = $Results[$i]['lic_name'];
      }
    $SQL = "SELECT licgroup_pk,licgroup_name FROM licgroup;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['licgroup_pk']); $i++)
      {
      $this->GrpName['g'.$Results[$i]['licgroup_pk']] = $Results[$i]['licgroup_name'];
      }
    $this->GrpName['g0'] = 'Phrase';
    $this->LicName['l1'] = 'Phrase';
    $this->LicGroupPlugin = &$Plugins[plugin_find_id('license_groups')];
    $this->LicGroupPlugin->MakeGroupTables();

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	print "The current license group tree.\n";
	print "Inherited groups are expanded and licenses are listed.\n";
	print "Loops in group inheritance are identified.\n";
	print "<P/>\n";
	foreach($this->GrpName as $G => $g)
	  {
	  if ($this->LicGroupPlugin->GrpInGroup[$G]['head'] == 1)
	    {
	    print "<ul>\n";
	    $this->DrawGroupTree($G);
	    print "</ul>\n";
	    }
	  }
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
$NewPlugin = new licgroup_debug;
$NewPlugin->Initialize();
?>
