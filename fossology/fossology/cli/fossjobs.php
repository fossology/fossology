#!/usr/bin/php
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
/**
 * fossjobs
 *
 * list fossology agents that are configured in the ui or 
 * run the default configured in the ui.
  *
 * @param (optional) string -h standard help switch
 * @param (optional) string -v verbose debugging
 * @param (optional) string -a lists agents
 * @param (optional) string -A specify agents
 * @param (optional) string -u list available uploads
 * @param (optional) string -P priority for the jobs (default: 0)
 * @param int -U $upload_pk upload primary key to run agents on....
 *
 * @return 0 for success, string for failure....
 * 
 * @version "$Id$"
 * 
 * note: there is a user interface issue here in that the user has no
 * easy way to discover and specify what the upload_pk is.
 */

// Have to set this or else plugins will not load.
$GlobalReady = 1;

/**********************************************************************
 **********************************************************************
 SUPPORT FUNCTIONS
 **********************************************************************
 **********************************************************************/

/**
 * function: list agents
 *
 * lists the agents that are registered with the system.
 * Assumes that the agent plugins have been configured.
 *
 * @return array $agent_list
 */
function list_agents()
{
  $agent_list = menu_find("Agents", $depth);
  return($agent_list);
}
require_once("pathinclude.h.php");
global $WEBDIR;
$UI_CLI = 1;
require_once("$WEBDIR/common/common.php");
cli_Init();


/**********************************************************************
 **********************************************************************
 INITIALIZE THIS INTERFACE
 **********************************************************************
 **********************************************************************/
$usage = basename($argv[0]) . " [options]
  Options:
  -h        :: help, this message
  -v        :: verbose output
  -a        :: list available agent tasks
  -A string :: specify agent to schedule (default is everything from -a)
               The string can be a comma-separated list of agent tasks.
  -u        :: list available upload ids
  -U upload :: the upload identifier to for scheduling agent tasks
  -P num    :: priority for the jobs (higher = more important, default:0)
";

//process parameters, see usage above
$options = getopt("haA:P:uU:v");
//print_r($options);
if (empty($options))
  {
  echo $usage;
  exit(1);
  }

if (array_key_exists("h",$options))
  {
  echo $usage;
  exit(0);
  }


global $Plugins;
global $DB;
if (empty($DB))
  {
  print "ERROR: Unable to connect to the database.\n";
  exit(1);
  }


/**********************************************************************
 **********************************************************************
 PROCESS COMMAND LINE SELECTION
 **********************************************************************
 **********************************************************************/

$Verbose = 0;
if (array_key_exists("v",$options))
  {
  $Verbose = 1;
  }

$Priority = 0;
if (array_key_exists("P",$options))
  {
  $Priority = intval($options["P"]);
  }

// Get the list of registered agents
$agent_list = list_agents();
if (empty($agent_list))
  {
  echo "ERROR! could not get list of agents\n";
  echo "Are Plugins configured?\n";
  exit(1);
  }

/* If the user specified a list, then disable every agent not in the list */
if (array_key_exists("A",$options))
  {
  $agent_count = count($agent_list);
  for($ac=0; $ac<$agent_count; $ac++)
    {
    $Found=0;
    foreach(split(',',$options["A"]) as $Val)
      {
      if (!strcmp($Val,$agent_list[$ac]->URI)) { $Found=1; }
      }
    if ($Found == 0) { $agent_list[$ac]->URI = NULL; }
    }
  }

/* List available agents */
if (array_key_exists("a",$options))
  {
  if (empty($agent_list))
    {
    echo "No agents configured\n";
    }
  else
    {
    echo "The available agents are:\n";
    $agent_count = count($agent_list);
    for ($ac=0; $ac<$agent_count; $ac++)
      {
      $agent = ($agent_list[$ac]->URI);
      if (!empty($agent))
	{
	echo " $agent\n";
	}
      }
    }
  }

/* List available uploads */
if (array_key_exists("u",$options))
  {
  $SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
  $Results = $DB->Action($SQL);
  print "# The following uploads are available (upload id: name)\n";
  for($i=0; !empty($Results[$i]['upload_pk']); $i++)
    {
    $Label = $Results[$i]['upload_filename'];
    if (!empty($Results[$i]['upload_desc']))
      {
      $Label .= " (" . $Results[$i]['upload_desc'] . ')';
      }
    print $Results[$i]['upload_pk'] . ": $Label\n";
    }
  }

$upload_pk = $options['U'];
if (!empty($upload_pk))
  {
  $reg_agents = array();
  $results    = array();
  // Schedule them
  $agent_count = count($agent_list);
  for ($ac=0; $ac<$agent_count; $ac++)
    {
    $agentname = $agent_list[$ac]->URI;
    if (!empty($agentname))
      {
      $Agent = &$Plugins[plugin_find_id($agentname)];
      $results = $Agent->AgentAdd($upload_pk,NULL,$Priority);
      if (!empty($results))
        {
        echo "ERROR: Scheduling failed for Agent $agentname\n";
        echo "ERROR message: $results\n";
        exit(1);
        }
      else if ($Verbose)
        {
	print "Scheduled: $upload_pk -> $agentname\n";
	}
      }
    }
  } // if $upload_pk is defined
exit(0);
?>
