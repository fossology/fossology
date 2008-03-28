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
 * @param (optional) string -l lists agents or
 * @param int -u $upload_pk upload primary key to run agents on....
 *
 * @return 0 for success, string for failure....
 * 
 * note: there is a user interface issue here in that the user has no
 * easy way to discover and specify what the upload_pk is.
 */
global $Plugins;
global $WEBDIR;

// Have to set this or else plugins will not load.
$GlobalReady = 1;

require_once("pathinclude.h.php");
require_once("$WEBDIR/common/common.php");
require_once("$WEBDIR/template/template-plugin.php");

$usage = <<<USAGE
fossjob [-h] [-l] | -u upload_pk
   Where -h is help, this messagge.
         -l list configured agents
         -u upload_pk the upload_pk to run the agents on

USAGE;

//process parameters, see usage above
$options = getopt("hlu:");
//print_r($options);
if(empty($options))
{
  echo $usage;
  exit(1);
}
$help    = array_key_exists("h",$options);
$list    = array_key_exists("l",$options);
$dashu   = array_key_exists("u",$options);
$upload_pk = $options['u'];

// every cli must perform these steps (make this a func/class);
/* Load the plugins */
plugin_load("$WEBDIR/plugins",0); /* load but do not initialize */

/* Turn off authentication */
/** The auth module hijacks and disables plugins, so turn it off. **/
$P = &$Plugins[plugin_find_any_id("auth")];
if (!empty($P)) { $P->State = PLUGIN_STATE_FAIL; }

/* Initialize plugins */
/** This registers plugins with the menu structure and start the DB
 connection. **/
plugin_init(); /* this registers plugins with menus */
//plugin_load("/home/markd/Fossology/src/fossology/ui-nk/plugins");
plugin_load("$WEBDIR/plugins");

// checking the parameters in this order makes things work according
// to the spec (-l | -u <arg>)
if ($help)
{
  echo $usage;
  exit(0);
}
if($list)
{
  $alist = list_agents();
  if(empty($alist))
  {
    echo "No agents configured\n";
    exit(0);
  }
  echo "The configured agents are:\n";
  $agent_count = count($alist);
  for ($ac=0; $ac<$agent_count; $ac++)
  {
    $agents[$ac] = ($alist[$ac]->URI);
    echo " $agents[$ac]\n";
  }
  exit(0);
}
if(empty($upload_pk))
{
  echo "Error, no upload_pk supplied\n";
  exit(1);
}
//echo "upload:$upload_pk\n";

// good to go, get the list of registered agents
$agent_list = list_agents();
if (empty($agent_list))
{
  echo "ERROR! could not get list of agents\n";
  echo "Are Plugins configured?\n";
  exit(1);
}
$reg_agents = array();
$results    = array();
// Schedule them
$agent_count = count($agent_list);
for ($ac=0; $ac<$agent_count; $ac++)
{
  $reg_agents[$ac] = ($agent_list[$ac]->URI);
  //echo "$results[$ac] = $reg_agents[$ac]->AgentAdd($upload_pk)";
  if (empty($results[$ac]))
  {
    echo "Error! Scheduling failed for Agent {$reg_agents[$ac]}\n";
    exit(1);
  }
}
exit(0);
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
?>