<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
 * \file fossjobs.php
 * 
 * \brief fossjobs
 * list fossology agents that are configured in the ui or
 * run the default configured in the ui.
 *
 * @param (optional) string -h standard help switch
 * @param (optional) string -v verbose debugging
 * @param (optional) string -a lists agents
 * @param (optional) string -A specify agents
 * @param (optional) string -u list available uploads
 * @param (optional) string --user specify user name
 * @param (optional) string --password specify password
 * @param (optional) string -c Specify the directory for the system configuration
 * @param (optional) string -D Delete upload
 * @param int -U $upload_pk upload primary key to run agents on....
 *
 * @exit 0 for success, others for failure....
 *
 * \note there is a user interface issue here in that the user has no
 * easy way to discover and specify what the upload_pk is.
 */
/**********************************************************************
 **********************************************************************
SUPPORT FUNCTIONS
**********************************************************************
**********************************************************************/
/**
 * \brief list agents
 *
 * lists the agents that are registered with the system.
 * Assumes that the agent plugins have been configured.
 *
 * @return array $agent_list
 */
function list_agents() {
  $agent_list = menu_find("Agents", $depth);
  return ($agent_list);
}

/**
 * include common-cli.php directly, common.php can not include common-cli.php
 * becuase common.php is included before UI_CLI is set
 */
require_once("$MODDIR/lib/php/common-cli.php");

global $Plugins;
global $PERM_NAMES;
global $SysConf;
global $PG_CONN;

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
  -U upload :: the upload identifier for scheduling agent tasks
               The string can be a comma-separated list of upload ids.
               Or, use 'ALL' to specify all upload ids.
  -D upload :: the upload identifier for scheduling delete tasks
               The string can either be 'ALL', a string (the upload_pk),
               or an array of upload_pk's if multiple -D's were specified.
  --user string :: user name
  --password string :: password
  -c string :: Specify the directory for the system configuration
";
//process parameters, see usage above
$longopts = array("user:", "password:");
$options = getopt("c:haA:P:uU:D:v", $longopts);
//print_r($options);
if (empty($options)) {
  echo $usage;
  exit(1);
}
if (array_key_exists("h", $options)) {
  echo $usage;
  exit(0);
}

/**********************************************************************
 **********************************************************************
PROCESS COMMAND LINE SELECTION
**********************************************************************
**********************************************************************/
$user = "";
$passwd = "";
if (array_key_exists("user", $options)) {
  $user = $options["user"];
}

if (array_key_exists("password", $options)) {
  $passwd = $options["password"];
}

account_check($user, $passwd); // check username/password
$user_pk = $SysConf['auth']['UserId'];

/* init plugins */
cli_Init();

$Verbose = 0;
if (array_key_exists("v", $options)) {
  $Verbose = 1;
}

// Get the list of registered agents
$agent_list = list_agents();
if (empty($agent_list)) {
  echo "ERROR! could not get list of agents\n";
  echo "Are Plugins configured?\n";
  exit(1);
}

/* List available agents */
if (array_key_exists("a", $options)) {
  if (empty($agent_list)) {
    echo "No agents configured\n";
  } else {
    echo "The available agents are:\n";
    $agent_count = count($agent_list);
    for ($ac = 0;$ac < $agent_count;$ac++) {
      $agent = ($agent_list[$ac]->URI);
      if (!empty($agent)) {
        echo " $agent\n";
      }
    }
  }
}

/* Hide agents  that aren't related to data scans */
$Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
for($ac=0; !empty($agent_list[$ac]->URI); $ac++)
  if (array_search($agent_list[$ac]->URI, $Skip) !== false) 
  {
      unset($agent_list[$ac]);
  }

/* If the user specified a list, then disable every agent not in the list */
$Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
if (array_key_exists("A", $options)) 
{
  $agent_count = count($agent_list);
  for ($ac = 0;$ac < $agent_count;$ac++) 
  {
    $Found = 0;
    foreach(explode(',', $options["A"]) as $Val) 
    {
      if (!strcmp($Val, $agent_list[$ac]->URI))  $Found = 1; 
    }
    if ($Found == 0) $agent_list[$ac]->URI = NULL;
  }
}

/* List available uploads */
if (array_key_exists("u", $options)) 
{
  $root_folder_pk = GetUserRootFolder();
  $FolderPath = NULL;
  $FolderList = FolderListUploadsRecurse($root_folder_pk, $FolderPath, PERM_WRITE);

  print "# The following uploads are available (upload id: name)\n";
  foreach($FolderList as $Folder)
  {
    $Label = $Folder['name'] . " (" . $Folder['upload_desc'] . ')';
    print $Folder['upload_pk'] . ": $Label\n";
  }
  exit(0);
}

if (array_key_exists("U", $options)) 
{
  /* $options['U'] can either be 'ALL', a string (the upload_pk), 
     or an array of upload_pk's if multiple -U's were specified. 
   */
  $upload_options = $options['U'];
  $upload_pk_array = array();
  if ($upload_options == 'ALL') 
  {
    $SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result) and !empty($row['upload_pk'])) 
    {
        $upload_pk_array[] = $row['upload_pk'];
    }
    pg_free_result($result);
  }
  else if (is_array($upload_options))
  {
    $upload_pk_array = $upload_options;
  }
  else
  {
    $upload_pk_array[] = $upload_options;
  }
   
  /* check permissions */
  $checked_list = array();
  foreach($upload_pk_array as $upload_pk)
  {
    $UploadPerm = GetUploadPerm($upload_pk);
    if ($UploadPerm < PERM_WRITE)
    {
      print "You have no permission to queue agents for upload " . $upload_pk . "\n";
      continue;
    }
    $checked_list[] = $upload_pk;
  }
  $checked_list_str = implode(",", $checked_list);

  /** scheduling agent tasks on upload ids */
  QueueUploadsOnAgents($checked_list_str, $agent_list, $Verbose);
}

if (array_key_exists("D", $options))
{
  /* $options['D'] can either be 'ALL', a string (the upload_pk),
     or an array of upload_pk's if multiple -D's were specified.
   */
  $upload_options = $options['D'];
  $upload_pk_array = array();
  if ($upload_options == 'ALL')
  {
    $SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result) and !empty($row['upload_pk']))
    {
        $upload_pk_array[] = $row['upload_pk'];
    }
    pg_free_result($result);
  }
  else if (is_array($upload_options))
  {
    $upload_pk_array = $upload_options;
  }
  else
  {
    $upload_pk_array[] = $upload_options;
  }
  /* check permissions */
  $checked_list = array();
  foreach($upload_pk_array as $upload_pk)
  {
    $UploadPerm = GetUploadPerm($upload_pk);
    if ($UploadPerm < PERM_WRITE)
    {
      print "You have no permission to delete upload " . $upload_pk . "\n";
      continue;
    }
    $checked_list[] = $upload_pk;
  }
  $checked_list_str = implode(",", $checked_list);

  /** scheduling delagent tasks on upload ids */
  QueueUploadsOnDelagents($checked_list_str, $Verbose);
}

exit(0);
?>
