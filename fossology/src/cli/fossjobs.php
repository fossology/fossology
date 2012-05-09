<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 * @param (optional) string -P priority for the jobs (default: 0)
 * @param int -U $upload_pk upload primary key to run agents on....
 *
 * @return 0 for success, string for failure....
 *
 * @version "$Id: fossjobs.php 3140 2010-05-04 21:37:56Z rrando $"
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
  -P num    :: priority for the jobs (higher = more important, default:0)
  --user string :: user name
  --password string :: password
  -c string :: Specify the directory for the system configuration
";
//process parameters, see usage above
$longopts = array("user:", "password:");
$options = getopt("c:haA:P:uU:v", $longopts);
//print_r($options);
if (empty($options)) {
  echo $usage;
  exit(1);
}
if (array_key_exists("h", $options)) {
  echo $usage;
  exit(0);
}
global $Plugins;
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

/** get username/passwd from ~/.fossology.rc */
$user_passwd_file = getenv("HOME") . "/.fossology.rc";
if (file_exists($user_passwd_file)) {
  $user_passwd_array = parse_ini_file($user_passwd_file, true);

  if(!empty($user_passwd_array) && !empty($user_passwd_array['user']))
    $user = $user_passwd_array['user'];
  if(!empty($user_passwd_array) && !empty($user_passwd_array['password']))
    $passwd = $user_passwd_array['password'];
}
/* check if the user name/passwd is valid */

if (empty($user)) {
  $uid_arr = posix_getpwuid(posix_getuid());
  $user = $uid_arr['name'];
}

if (empty($passwd)) {
  echo "The user is: $user, please enter the password:\n";
  system('stty -echo');
  $passwd = trim(fgets(STDIN));
  system('stty echo');
}

if (!empty($user) and !empty($passwd)) {
  $SQL = "SELECT * from users where user_name = '$user';";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  if(empty($row)) {
    echo "User name or password is invalid.\n";
    pg_free_result($result);
    exit(0);
  }
  $SysConf['auth']['UserId'] = $row['user_pk'];
  pg_free_result($result);
  if (!empty($row['user_seed']) && !empty($row['user_pass'])) {
    $passwd_hash = sha1($row['user_seed'] . $passwd);
    if (strcmp($passwd_hash, $row['user_pass']) != 0) {
      echo "User name or password is invalid.\n";
      exit(0);
    }
  }
}

/* init plugins */
cli_Init();

$Verbose = 0;
if (array_key_exists("v", $options)) {
  $Verbose = 1;
}
$Priority = 0;
if (array_key_exists("P", $options)) {
  $Priority = intval($options["P"]);
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

global $PG_CONN;
/* List available uploads */
if (array_key_exists("u", $options)) {
  $SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  print "# The following uploads are available (upload id: name)\n";
  $upload_count = pg_num_rows($result);
  $AllUploadPk = "";
  while ($row = pg_fetch_assoc($result) and !empty($row['upload_pk'])) {
    $Label = $row['upload_filename'];
    if (!empty($row['upload_desc'])) {
      $Label.= " (" . $row['upload_desc'] . ')';
    }
    print $row['upload_pk'] . ": $Label\n";
    if ($upload_count == 1) {
      $AllUploadPk = $row['upload_pk'];
    } else {
      $AllUploadPk.= "," . $row['upload_pk'];
    }
  }
  pg_free_result($result);
}

$upload_pk_list = "";
if (array_key_exists("U", $options)) {
  $upload_pk_list = $options['U'];
  if ($upload_pk_list == 'ALL') {
    $upload_pk_list = "";
    $SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    $i = 0;
    while ($row = pg_fetch_assoc($result) and !empty($row['upload_pk'])) {
      if ($i == 0) {
        $upload_pk_list = $row['upload_pk'];
      } else {
        $upload_pk_list.= "," . $row['upload_pk'];
      }
      $i++;
    }
    pg_free_result($result);
  }
}
/** scheduling agent tasks on upload ids */
QueueUploadsOnAgents($upload_pk_list, $agent_list, $Verbose, $Priority);

exit(0);
?>
