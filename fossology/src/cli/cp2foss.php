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
 * \file cp2foss.php
 * \brief cp2foss
 * Import a file (or director or url) into FOSSology for processing.
 * \exit 0 for success, 1 for failure.
 */

/**
 * include common-cli.php directly, common.php can not include common-cli.php
 * becuase common.php is included before UI_CLI is set
 */
require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

$Usage = "Usage: " . basename($argv[0]) . " [options] [archives]
  Options:
    -h       = this help message
    -v       = enable verbose debugging
    --user string = user name
    --password string = password
    -c string = Specify the directory for the system configuration

  FOSSology storage options:
    -f path  = folder path for placing files (e.g., -f 'Fedora/ISOs/Disk 1')
               You do not need to specify '/System Repository'.
               All paths are under '/System Repository'.
    -A       = alphabet folders; organize uploads into folder a-c, d-f, etc.
    -AA num  = specify the number of letters per folder (default: 3); implies -A
    -n name  = (optional) name for the upload (default: name it after the file)
    -e addr  = email results to addr
    -d desc  = (optional) description for the update

  FOSSology processing queue options:
    -Q       = list all available processing agents
    -q       = specify a comma-separated list of agents, or 'all'
    NOTE: By default, no analysis agents are queued up.
    -T       = TEST. No database or repository updates are performed.
               Test mode enables verbose mode.

  FOSSology source options:
    archive  = file, directory, or URL to the archive.
             If the archive is a URL, then it is retrieved and added.
             If the archive is a file, then it is used as the source to add.
             If the archive is a directory, then ALL files under it are
             recursively added.
    -        = a single hyphen means the archive list will come from stdin.
    -X path  = item to exclude when archive is a directory
             You can specify more than one -X.  For example, to exclude
             all svn and cvs directories, include the following before the
             archive's directory path:
               -X .svn -X .cvs
    NOTES:
      If you use -n, then -n must be set BEFORE each archive.
      If you specify a directory, then -n and -d are ignored.
      Multiple archives can be specified after each storage option.

  One example, to load a file into one path:
  cp2foss \\
    --user USER --password PASSWORD \\
    -f path -d 'the file' /tmp/file

  Depricated options:
    -a archive = (depricated) see archive
    -p path    = (depricated) see -f
    -R         = (depricated and ignored)
    -w         = (depricated and ignored)
    -W         = (depricated and ignored)
  ";
/* Load command-line options */
global $PG_CONN;
$Verbose = 0;
$Test = 0;
$fossjobs_command = "";
/************************************************************************/
/************************************************************************/
/************************************************************************/

/**
 * \brief Given an upload name and the number
 * of letters per bucket, return the bucket folder name.
 */
function GetBucketFolder($UploadName, $BucketGroupSize) {
  $Letters = "abcdefghijklmnopqrstuvwxyz";
  $Numbers = "0123456789";
  if (empty($UploadName)) {
    return;
  }
  $Name = strtolower(substr($UploadName, 0, 1));
  /* See if I can find the bucket */
  if (empty($BucketGroupSize) || ($BucketGroupSize < 1)) {
    $BucketGroupSize = 3;
  }
  for ($i = 0;$i < 26;$i+= $BucketGroupSize) {
    $Range = substr($Letters, $i, $BucketGroupSize);
    $Find = strpos($Range, $Name);
    if ($Find !== false) {
      if (($BucketGroupSize <= 1) || (strlen($Range) <= 1)) {
        return ($Range);
      }
      return (substr($Range, 0, 1) . '-' . substr($Range, -1, 1));
    }
  }
  /* Not a letter.  Check for numbers */
  $Find = strpos($Numbers, $Name);
  if ($Find !== false) {
    return ("0-9");
  }
  /* Not a letter. */
  return ("Other");
} /* GetBucketFolder() */

/**
 * \brief Given a folder path, return the folder_pk.
 * 
 * \param $FolderPath - path from -f
 * \param $Parent - parent folder of $FolderPath
 * 
 * \return folder_pk, 1: 'Software Repository', others: specified folder 

 * \note If any part of the folder path does not exist, thenscp cp2foss will create it.
 * This is recursive!
 */
function GetFolder($FolderPath, $Parent = NULL) {
  global $PG_CONN;
  global $Verbose;
  global $Test;
  if (empty($Parent)) {
    $Parent = FolderGetTop();
  }
  /*/ indicates it's the root folder. Empty folder path ends recursion. */
  if ($FolderPath == '/') {
    return ($Parent);
  }
  if (empty($FolderPath)) {
    return ($Parent);
  }
  list($Folder, $FolderPath) = explode('/', $FolderPath, 2);
  if (empty($Folder)) {
    return (GetFolder($FolderPath, $Parent));
  }
  /* See if it exists */
  $SQLFolder = str_replace("'", "''", $Folder);
  $SQL = "SELECT * FROM folder
  INNER JOIN foldercontents ON child_id = folder_pk
  AND foldercontents_mode = '1'
  WHERE parent_fk = '$Parent' AND folder_name='$SQLFolder';";
  if ($Verbose) {
    print "SQL=\n$SQL\n";
  }
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $row_count = pg_num_rows($result);

  if (row_count <= 0) {
    /* Need to create folder */
    global $Plugins;
    $P = & $Plugins[plugin_find_id("folder_create") ];
    if (empty($P)) {
      print "FATAL: Unable to find folder_create plugin.\n";
      exit(1);
    }
    if ($Verbose) {
      print "Folder not found: Creating $Folder\n";
    }
    if (!$Test) {
      $P->Create($Parent, $Folder, "");
    }
    pg_free_result($result);
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
  }
  $Parent = $row['folder_pk'];
  pg_free_result($result);
  return (GetFolder($FolderPath, $Parent));
} /* GetFolder() */

/**
 * \brief Given one object (file or URL), upload it.
 *
 * \param $FolderPath - folder path
 * \param $UploadArchive - upload file(absolute path) or url
 * \param $UploadName - uploaded file/dir name
 * \param $UploadDescription - upload description
 *
 * \return 1: error, 0: success
 */
function UploadOne($FolderPath, $UploadArchive, $UploadName, $UploadDescription, $TarSource = NULL) {
  global $Verbose;
  global $Test;
  global $QueueList;
  global $fossjobs_command;

  if (empty($UploadName)) {
    $text = "UploadName is empty\n";
    echo $text;
    return 1;
  }
  /* Get the folder's primary key */
  global $OptionA; /* Should it use bucket names? */
  if ($OptionA) {
    global $bucket_size;
    $FolderPath.= "/" . GetBucketFolder($UploadName, $bucket_size);
  }
  $FolderPk = GetFolder($FolderPath);
  if ($FolderPk == 1) {
    print "  Uploading to folder: 'Software Repository'\n";
  }
  else {
    print "  Uploading to folder: '$FolderPath'\n";
  }
  print "  Uploading as '$UploadName'\n";
  if (!empty($UploadDescription)) {
    print "  Upload description: '$UploadDescription'\n";
  }

  $Mode = (1 << 3); // code for "it came from web upload"
  global $SysConf;
  $user_pk = $SysConf['auth']['UserId'];

  /* Create the upload for the file */
  if ($Verbose) {
    print "JobAddUpload($user_pk, $UploadName,$UploadArchive,$UploadDescription,$Mode,$FolderPk);\n";
  }
  if (!$Test) {
    $Src = $UploadArchive;
    if (!empty($TarSource)) {
      $Src = $TarSource;
    }
    $UploadPk = JobAddUpload($user_pk, $UploadName, $Src, $UploadDescription, $Mode, $FolderPk);
    print "  UploadPk is: '$UploadPk'\n";
  }

  /* Prepare the job: job "wget" */
  $jobpk = JobAddJob($user_pk, "wget", $UploadPk);
  if (empty($jobpk) || ($jobpk < 0)) {
    $text = _("Failed to insert job record");
    echo $text;
    return 1;
  }

  $jq_args = "$UploadPk - $Src";

  $jobqueuepk = JobQueueAdd($jobpk, "wget_agent", $jq_args, "no", NULL);
  if (empty($jobqueuepk)) {
    $text = _("Failed to insert task 'wget' into job queue");
    echo $text;
    return 1;
  }

  /* schedule agents */
  global $Plugins;
  $unpackplugin = &$Plugins[plugin_find_id("agent_unpack") ];
  $ununpack_jq_pk = $unpackplugin->AgentAdd($jobpk, $UploadPk, $ErrorMsg, array("wget_agent"));
  if ($ununpack_jq_pk < 0) {
    echo  $ErrorMsg;
    return 1;
  }

  $adj2nestplugin = &$Plugins[plugin_find_id("agent_adj2nest") ];
  $adj2nest_jq_pk = $adj2nestplugin->AgentAdd($jobpk, $UploadPk, $ErrorMsg, array());
  if ($adj2nest_jq_pk < 0) {
    echo $ErrorMsg;
    return 1;
  }

  if (!empty($QueueList)) {
    switch ($QueueList) {
      case 'ALL':
      case 'all':
        $Cmd = "$fossjobs_command -U '$UploadPk'";
        break;
      default:
        $Cmd = "$fossjobs_command -U '$UploadPk' -A '$QueueList'";
      break;
    }
    if ($Verbose) {
      print "CMD=$Cmd\n";
    }
    if (!$Test) {
      system($Cmd);
    }
  }
  else {
    /* No other agents other than unpack scheduled, attach to unpack*/
  }
} /* UploadOne() */


/************************************************************************/
/************************************************************************/
/************************************************************************/
/* Process each parameter */
$FolderPath = "/";
$UploadDescription = "";
$UploadName = "";
$QueueList = "";
$TarExcludeList = "";
$bucket_size = 3;

$user = "";
$passwd = "";

for ($i = 1;$i < $argc;$i++) {
  switch ($argv[$i]) {
    case '-c':
      $i++;
      break; /* handled in fo_wrapper */
    case '-h':
    case '-?':
      print $Usage . "\n";
      exit(0);
    case '--user':
      $i++;
      $user = $argv[$i];
      break;
    case '--password':
      $i++;
      $passwd = $argv[$i];
      break;
    case '-A': /* use alphabet buckets */
      $OptionA = true;
      break;
    case '-AA': /* use alphabet buckets */
      $OptionA = true;
      $i++;
      $bucket_size = intval($argv[$i]);
      if ($bucket_size < 1) {
        $bucket_size = 1;
      }
      break;
    case '-f': /* folder path */
    case '-p': /* depricated 'path' to folder */
      $i++;
      $FolderPath = $argv[$i];
      /* idiot check for absolute paths */
      //print "  Before Idiot Checks: '$FolderPath'\n";
      /* remove starting and ending / */
      $FolderPath = preg_replace('@^/*@', "", $FolderPath);
      $FolderPath = preg_replace('@/*$@', "", $FolderPath);
      /* Note: the pattern below should probably be generalized to remove everything
       * up to and including the 1st /, This pattern works in what I've
      * tested: @^.*\/@ ( I had to escape the / so the comment works!)
      */
      $FolderPath = preg_replace("@^S.*? Repository@", "", $FolderPath);
      $FolderPath = preg_replace('@//*@', "/", $FolderPath);
      $FolderPath = '/' . $FolderPath;
      //print "  AFTER Idiot Checks: '$FolderPath'\n";

      break;
    case '-R': /* obsolete: recurse directories */
      break;
    case '-W': /* obsolete: webserver */
      break;
    case '-w': /* obsolete: URL switch to use wget */
      break;
    case '-d': /* specify upload description */
      $i++;
      $UploadDescription = $argv[$i];
      break;
    case '-n': /* specify upload name */
      $i++;
      $UploadName = $argv[$i];
      break;
    case '-Q': /** list all available processing agents */
      $OptionQ = 1;
      break;
    case '-q':
      $i++;
      $QueueList = $argv[$i];
      break;
    case '-T': /* Test mode */
      $Test = 1;
      if (!$Verbose) {
        $Verbose++;
      }
      break;
    case '-v':
      $Verbose++;
      break;
    case '-X':
      if (!empty($TarExcludeList)) {
        $TarExcludeList.= " ";
      }
      $i++;
      $TarExcludeList.= "--exclude '" . $argv[$i] . "'";
      break;
    case '-a': /* it's an archive! */
      /* ignore -a since the next name is a file. */
      break;
    case '-': /* it's an archive list from stdin! */
      $stdin_flag = 1;
      break;
    default:
      if (substr($argv[$i], 0, 1) == '-') {
        print "Unknown parameter: '" . $argv[$i] . "'\n";
        print $Usage . "\n";
        exit(1);
      }
    /* No hyphen means it is a file! */
    $UploadArchive = $argv[$i];
  } /* switch */
} /* for each parameter */

/** get username/passwd from ~/.fossology.rc */
$user_passwd_file = getenv("HOME") . "/.fossology.rc";
if (empty($user) && empty($passwd) && file_exists($user_passwd_file)) {
  $user_passwd_array = parse_ini_file($user_passwd_file, true);

  if(!empty($user_passwd_array) && !empty($user_passwd_array['user']))
    $user = $user_passwd_array['user'];
  if(!empty($user_passwd_array) && !empty($user_passwd_array['password']))
    $passwd = $user_passwd_array['password'];
}

/* check if the user name/passwd is valid */
if (empty($user)) {
  /*
  $uid_arr = posix_getpwuid(posix_getuid());
  $user = $uid_arr['name'];
  */
  echo "FATAL: You should add '--user USERNAME' when running OR add 'user=USERNAME' in ~/.fossology.rc before running.\n";
  exit(1);
}
if (empty($passwd)) {
  echo "The user is: $user, please enter the password:\n";
  system('stty -echo');
  $passwd = trim(fgets(STDIN));
  system('stty echo');
  if (empty($passwd)) {
    echo "You entered an empty password.\n";
    exit(1);
  }
}

if (!empty($user) and !empty($passwd)) {
  $SQL = "SELECT * from users where user_name = '$user';";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  if(empty($row)) {
    echo "User name or password is invalid.\n";
    pg_free_result($result);
    exit(1);
  }
  $SysConf['auth']['UserId'] = $row['user_pk'];
  pg_free_result($result);
  if (!empty($row['user_seed']) && !empty($row['user_pass'])) {
    $passwd_hash = sha1($row['user_seed'] . $passwd);
    if (strcmp($passwd_hash, $row['user_pass']) != 0) {
      echo "User name or password is invalid.\n";
      exit(1);
    }
  }
}

/** list all available processing agents */
if (!$Test && $OptionQ) {
  $Cmd = dirname(__FILE__) . "/fossjobs --user $user --password $passwd -c $SYSCONFDIR -a";
  system($Cmd);
  exit(0);
}

/** get archive from stdin */
if ($stdin_flag)
{
  $Fin = fopen("php://stdin", "r");
  if (!feof($Fin)) {
    $UploadArchive = trim(fgets($Fin));
  }
  fclose($Fin);
}

/** compose fossjobs command */
if($Verbose) {
  $fossjobs_command = dirname(__FILE__) . "/fossjobs --user $user --password $passwd -c $SYSCONFDIR -v "; 
} else {
  $fossjobs_command = dirname(__FILE__) . "/fossjobs --user $user --password $passwd -c $SYSCONFDIR  ";
}

//print "fossjobs_command is:$fossjobs_command\n";

if (!$UploadArchive) {  // upload is empty
  print "FATAL: you want to upload '$UploadArchive'.\n";
  exit(1);
}

/** get real path, and file name */
$UploadArchiveTmp = "";
$UploadArchiveTmp = realpath($UploadArchive);
if (!$UploadArchiveTmp)  { // neither a file nor folder from server?
    print "NOTE: '$UploadArchive' is a URL or does not exist.\n";
} else {  // is a file or folder from server
  $UploadArchive = $UploadArchiveTmp;
}

if (strlen($UploadArchive) > 0) {
  if (empty($UploadName)) {
    $UploadName = basename($UploadArchive);
  }
}

print "Loading '$UploadArchive'\n";
//print "  CAlling UploadOne in 'main': '$FolderPath'\n";
$res = UploadOne($FolderPath, $UploadArchive, $UploadName, $UploadDescription);
if ($res) exit(1); // fail to upload
exit(0);
?>
