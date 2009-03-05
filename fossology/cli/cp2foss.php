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
/**************************************************************
 cp2foss

 Import a file (or directory) into FOSSology for processing.

 @return 0 for success, 1 for failure.
 *************************************************************/
/* Have to set this or else plugins will not load. */
$GlobalReady = 1;
/* Load all code */
require_once (dirname(__FILE__) . '/../share/fossology/php/pathinclude.php');
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once ("$WEBDIR/common/common.php");
cli_Init();
global $Plugins;
error_reporting(E_NOTICE & E_STRICT);
$Usage = "Usage: " . basename($argv[0]) . " [options] [archives]
  Options:
    -h       = this help message
    -v       = enable verbose debugging

  FOSSology storage options:
    -f path  = folder path for placing files (e.g., -f 'Fedora/ISOs/Disk 1')
               You do not need to specify '/System Repository'.
               All paths are under '/System Repository'.
    -A       = alphabet folders; organize uploads into folder a-c, d-f, etc.
    -AA num  = specify the number of letters per folder (default: 3); implies -A
    -n name  = (optional) name for the upload (default: name it after the file)
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

  NOTE: you may specify multiple parameters on the command-line.
  For example, to load three files into two different paths:
    -f path1 -d 'the first file' /tmp/file1 \\
             -d 'the second file' /tmp/file2 \\
    -f path2 -d 'the third file' http://server/file3

  Depricated options:
    -a archive = (depricated) see archive
    -p path    = (depricated) see -f
    -R         = (depricated and ignored)
    -w         = (depricated and ignored)
  ";
/* Load command-line options */
global $DB;
$Verbose = 0;
$Test = 0;
/************************************************************************/
/************************************************************************/
/************************************************************************/
/****************************************************
 GetBucketFolder(): Given an upload name and the number
 of letters per bucket, return the bucket folder name.
 ****************************************************/
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
/****************************************************
 GetFolder(): Given a folder path, return the folder_pk.
 NOTE: If any part of the folder path does not exist, thenscp cp2foss will
 create it.
 NOTE: This is recursive!
 ****************************************************/
function GetFolder($FolderPath, $Parent = NULL) {
  global $DB;
  global $Verbose;
  global $Test;
  //print "  GetFolder: FolderPath is:$FolderPath\n";
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
  list($Folder, $FolderPath) = split('/', $FolderPath, 2);
  if (empty($Folder)) {
    return (GetFolder($FolderPath, $Parent));
  }
  /* See if it exists */
  $SQLFolder = str_replace("'", "''", $Folder);
  $SQL = "SELECT * FROM folder
	INNER JOIN foldercontents ON child_id = folder_pk
	AND foldercontents_mode = '1'
	WHERE parent_fk = '$Parent' AND folder_name='$SQLFolder';";
  if ($Verbose > 1) {
    print "SQL=\n$SQL\n";
  }
  $Results = $DB->Action($SQL);
  if (count($Results) <= 0) {
    /* Need to create folder */
    global $Plugins;
    $P = & $Plugins[plugin_find_id("folder_create") ];
    if (empty($P)) {
      print "FATAL: Unable to find folder_create plugin.\n";
      exit(-1);
    }
    if ($Verbose) {
      print "Folder not found: Creating $Folder\n";
    }
    if (!$Test) {
      $P->Create($Parent, $Folder, "");
    }
    $Results = $DB->Action($SQL);
  }
  $Parent = $Results[0]['folder_pk'];
  return (GetFolder($FolderPath, $Parent));
} /* GetFolder() */
/****************************************************
 UploadOne(): Given one object (file or URL), upload it.
 This is a function because it is can also be recursive!
 ****************************************************/
function UploadOne($FolderPath, $UploadArchive, $UploadName, $UploadDescription, $TarSource = NULL) {
  global $Verbose;
  global $Test;
  global $QueueList;
  /* $Mode determines where it came from */
  if (preg_match("@^[a-zA-Z0-9_]+://@", $UploadArchive)) {
    $Mode = 1 << 2; /* Looks like a URL */
  }
  else if (is_dir($UploadArchive)) {
    /* It's a directory, tar it! */
    global $VARDATADIR;
    global $TarExcludeList;
    $Filename = "$VARDATADIR/cp2foss-" . uniqid() . ".tar";
    if (empty($UploadName)) {
      $UploadName = basename($UploadArchive);
    }
    if ($Verbose > 1) {
      $TarArg = "-cvf";
    } else {
      $TarArg = "-cf";
    }
    $Cmd = "tar $TarArg '$Filename' $TarExcludeList '$UploadArchive'";
    if ($Verbose) {
      print "CMD=$Cmd\n";
    }
    system($Cmd);
    UploadOne($FolderPath, $Filename, $UploadName, $UploadDescription, $UploadArchive);
    unlink($Filename);
    return;
  }
  else if (file_exists($UploadArchive)) {
    $Mode = 1 << 4; /* Looks like a filesystem */
  }
  else {
    /* Don't know what it is... */
    print "FATAL: '$UploadArchive' does not exist.\n";
    exit(1);
  }
  if (empty($UploadName)) {
    return;
  }
  /* Get the folder's primary key */
  global $OptionA; /* Should it use bucket names? */
  if ($OptionA) {
    global $bucket_size;
    $FolderPath.= "/" . GetBucketFolder($UploadName, $bucket_size);
  }
  $FolderPk = GetFolder($FolderPath);
  if ($FolderPk == 1) {
    print "  Uploading to folder: Software Repository\n";
  }
  else {
    print "  Uploading to folder: '$FolderPath'\n";
  }
  print "  Uploading as '$UploadName'\n";
  if (!empty($UploadDescription)) {
    print "  Upload description: '$UploadDescription'\n";
  }
  /* Create the upload for the file */
  if ($Verbose > 1) {
    print "JobAddUpload($UploadName,$UploadArchive,$UploadDescription,$Mode,$FolderPk);\n";
  }
  if (!$Test) {
    $Src = $UploadArchive;
    if (!empty($TarSource)) {
      $Src = $TarSource;
    }
    $UploadPk = JobAddUpload($UploadName, $Src, $UploadDescription, $Mode, $FolderPk);
  }
  /* Tell wget_agent to actually grab the upload */
  global $AGENTDIR;
  $Cmd = "$AGENTDIR/wget_agent -k '$UploadPk' '$UploadArchive'";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  if (!$Test) {
    system($Cmd);
  }
  /* Schedule the unpack */
  $Cmd = "fossjobs -U '$UploadPk' -A agent_unpack";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  if (!$Test) {
    //print "CP2: CMD=$Cmd\n";
    system($Cmd);
  }
  if (!empty($QueueList)) {
    switch ($QueueList) {
      case 'agent_unpack':
        $Cmd = "";
        break; /* already scheduled */
      case 'ALL':
      case 'all':
        $Cmd = "fossjobs -U '$UploadPk'";
        break;
      default:
        $Cmd = "fossjobs -U '$UploadPk' -A '$QueueList'";
        break;
    }
    //print "  CP2:CMD=$Cmd\n";
    if ($Verbose) {
      print "CMD=$Cmd\n";
    }
    if (!$Test) {
      system($Cmd);
    }
    /* $_SESSION may not be valid, go get the enotification flag */
    global $DB;
    print "  CP2: Before 1st query UploadPk is:$UploadPk\n";
    $Sql = "SELECT upload_pk, job_upload_fk,job_user_fk FROM upload,job WHERE " .
           "job_upload_fk=$UploadPk order by upload_pk desc;";
    $Users = $DB->Action($Sql);
    print "  CP2: After 1st query\n";
    $UserPk = $Users[0]['job_user_fk'];
    print "  CP2: after 1st query to get user_pk, UserPk is:$UserPk\n";
    $Sql = "SELECT user_pk, user_name, email_notify FROM users WHERE " .
           "user_pk=$UserPk; ";
    //"user_pk=$UserPk and user_name='fossy'; ";
    $Fossy= $DB->Action($Sql);
    $EmailNote = $Fossy[0]['email_notify'];
    print "  CP2: after 2nd query to get EN, EmailNote is:$EmailNote\n";
    if ($EmailNote == 'y') {
      print "  Scheduling email notification\n";
      $sched = scheduleEmailNotification($UploadPk);
      if ($sched !== NULL) {
        return($sched);
      }
    }
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
for ($i = 1;$i < $argc;$i++) {
  switch ($argv[$i]) {
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
    case '-Q':
      system("fossjobs -a");
      return (0);
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
    case '-h':
    case '-?':
      print $Usage . "\n";
      return (0);
    case '-a': /* it's an archive! */
      /* ignore -a since the next name is a file. */
      break;
    case '-': /* it's an archive list from stdin! */
      $Fin = fopen("php://stdin", "r");
      while (!feof($Fin)) {
        $UploadArchive = trim(fgets($Fin));
        if (strlen($UploadArchive) > 0) {
          print "Loading $UploadArchive\n";
          if (empty($UploadName)) {
            $UploadName = basename($UploadArchive);
          }
          UploadOne($FolderPath, $UploadArchive, $UploadName, $UploadDescription);
          /* prepare for next parameter */
          $UploadName = "";
        }
      }
      fclose($Fin);
      break;
    default:
      if (substr($argv[$i], 0, 1) == '-') {
        print "Unknown parameter: '" . $argv[$i] . "'\n";
        print $Usage . "\n";
        return (1);
      }
      /* No break! No hyphen means it is a file! */
      $UploadArchive = $argv[$i];
      print "Loading $UploadArchive\n";
      if (empty($UploadName)) {
        $UploadName = basename($UploadArchive);
      }
      //print "  CAlling UploadOne in 'main': '$FolderPath'\n";
      UploadOne($FolderPath, $UploadArchive, $UploadName, $UploadDescription);
      /* prepare for next parameter */
      $UploadName = "";
      break;
  } /* switch */
} /* for each parameter */
return (0);
?>
