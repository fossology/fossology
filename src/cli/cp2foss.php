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
 * \file cp2foss.php
 * \brief cp2foss
 * Import a file (or directory) into FOSSology for processing.
 * \return 0 for success, 1 for failure.
 */

/**
 * include common-cli.php directly, common.php can not include common-cli.php
 * becuase common.php is included before UI_CLI is set
 */
require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

global $Enotification;
global $Email;
global $ME;
global $webServer;

$Usage = "Usage: " . basename($argv[0]) . " [options] [archives]
  Options:
    -h       = this help message
    -v       = enable verbose debugging
    --user string = user name
    --password string = password

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
      exit(-1);
    }
    if ($Verbose) {
      print "Folder not found: Creating $Folder\n";
    }
    if (!$Test) {
      $P->Create($Parent, $Folder, "");
    }
    pg_free_result($result);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
  }
  $row = pg_fetch_assoc($result);
  $Parent = $row['folder_pk'];
  pg_free_result($result);
  return (GetFolder($FolderPath, $Parent));
} /* GetFolder() */

/**
 * \brief the process email notifciation
 * If being run as an agent, get the user name from the previous upload
 *
 * @param int $UploadPk the upload pk
 * @return NULL on success, String on failure.
 */
function ProcEnote($UploadPk) {

  global $Email;
  global $PG_CONN;
  global $ME;
  global $webServer;

  /* get the user name from the previous upload */
  $previous = $UploadPk-1;
  $Sql = "SELECT upload_pk,user_fk,job_upload_fk,job_user_fk FROM upload,job WHERE " .
           "job_upload_fk=$previous and upload_pk=$previous order by upload_pk desc;";
  $result = pg_query($PG_CONN, $Sql);
  DBCheckResult($result, $Sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  $UserPk = $row['job_user_fk'];
  $UserId = $row['user_fk'];
  $Sql = "SELECT user_pk, user_name, user_email, email_notify FROM users WHERE " .
             "user_pk=$UserPk; ";
  $result = pg_query($PG_CONN, $Sql);
  DBCheckResult($result, $Sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  $UserName = $row['user_name'];
  $UserEmail = $row['user_email'];

  /**
   * If called as agent, current upload user name will be fossy with a user_fk of NULL.
   * Get the information to check that condition
   */
  $Sql = "SELECT upload_pk,user_fk,job_upload_fk,job_user_fk FROM upload,job WHERE " .
           "job_upload_fk=$UploadPk and upload_pk=$UploadPk order by upload_pk desc;";
  $result = pg_query($PG_CONN, $Sql);
  DBCheckResult($result, $Sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  $UserPk = $row['job_user_fk'];
  $UserId = $row['user_fk'];

  // need to find the jq_pk's of bucket, copyright, nomos and package
  // agents to use as dependencies.

  $Depends = FindDependent($UploadPk);
  /* are we being run as fossy?, either as agent or from command line */
  if($UserId === NULL && $ME == 'fossy') {
    /*
     * When run as agent or fossy, pass in the UserEmail and UserName.  This
    * ensures the email address is correct, the UserName is used for the
    * salutation.
    */
    $sched = scheduleEmailNotification($UploadPk,$webServer,$UserEmail,$UserName,$Depends);
  }
  else {
    /* run as cli, use the email passed in and $ME */
    $sched = scheduleEmailNotification($UploadPk,$webServer,$Email,$ME,$Depends);
    print "  Scheduling email notification for $Email\n";
  }
  if ($sched !== NULL) {
    return("Warning: Queueing email failed:\n$sched\n");
  }
  return(NULL);
} // ProcEnote

/**
 * \brief Given one object (file or URL), upload it.
 * This is a function because it is can also be recursive!
 */
function UploadOne($FolderPath, $UploadArchive, $UploadName, $UploadDescription, $TarSource = NULL) {
  global $Verbose;
  global $Test;
  global $QueueList;
  global $Enotification;
  global $Email;
  global $fossjobs_command;

  /* $Mode determines where it came from */
  if (preg_match("@^[a-zA-Z0-9_]+://@", $UploadArchive)) {
    $Mode = 1 << 2; /* Looks like a URL */
  }
  else if (is_dir($UploadArchive)) {
    /* It's a directory, tar it! */
    global $LIBEXECDIR;
    global $TarExcludeList;

    /**
     * User reppath to get a path in the repository for temp storage.  Only
     * use the part up to repository, as the path returned may not exist.
     */
    $FilePart = "cp2foss-" . uniqid() . ".tar";
    exec("$LIBEXECDIR/reppath files $FilePart", $Path);
    $FilePath = $Path[0];
    $match = preg_match('/^(.*?repository)/', $FilePath, $matches);
    $Filename = $matches[1] . '/' . $FilePart;


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
    /* email notification will be scheduled below, above we just handed UploadOne
     * a tar'ed up file.
    */
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
  global $SYSCONFDIR;
  $Cmd = "$SYSCONFDIR/mods-enabled/wget_agent/agent/wget_agent -k '$UploadPk' -C '$UploadArchive' -c $SYSCONFDIR";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  if (!$Test) {
    system($Cmd);
  }
  /* Schedule the unpack */
  $Cmd = "$fossjobs_command -U '$UploadPk' -A agent_unpack";
  if ($Verbose) {
    print "CMD=$Cmd\n";
  }
  if (!$Test) {
    system($Cmd);
  }

  if (!empty($QueueList)) {
    switch ($QueueList) {
      case 'agent_unpack':
        $Cmd = "";
        break; /* already scheduled */
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
    /**
     * this is gross: by the time you get here, the uploadPk is one more than the
     * uploadPk reported to the user.  That's because the first upload pk is for
     * the fosscp_agent, then the second (created in cp2foss) is for the rest
     * of the processing.  Unless being run as a cli....  See ProcEnote.
     */
    if($Enotification) {
      $res = ProcEnote($UploadPk);
      if(!is_null($res)) {
        print $res;
      }
    }
  }
  else {
    /* No other agents other than unpack scheduled, attach to unpack*/
    if($Enotification) {
      $res = ProcEnote($UploadPk);
      if(!is_null($res)) {
        print $res;
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
$ME = exec('id -un',$toss,$rtn);


$user = "";
$passwd = "";

for ($i = 1;$i < $argc;$i++) {
  switch ($argv[$i]) {
    case '-h':
    case '-?':
      print $Usage . "\n";
      return (0);
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
    case '-W': /* webserver */
      $i++;
      $webServer = $argv[$i];
      //print "DBG: setting webServer to $webServer\n";
      break;
    case '-w': /* obsolete: URL switch to use wget */
      break;
    case '-d': /* specify upload description */
      $i++;
      $UploadDescription = $argv[$i];
      break;
    case '-e': /* email notification wanted */
      $i++;
      $Email = $argv[$i];
      // Make sure email looks valid
      $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $Email);
      if ($Check != $Email) {
        print "Invalid email address. $Email\n";
        print $Usage;
        exit(1);
      }
      $Enotification = TRUE;
      break;
    case '-n': /* specify upload name */
      $i++;
      $UploadName = $argv[$i];
      break;
    case '-Q':
      system("$fossjobs_command -a");
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
        exit(1);
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
      if($Verbose)
        $fossjobs_command = "fossjobs --user $user --password $passwd -v ";
      else 
        $fossjobs_command = "fossjobs --user $user --password $passwd ";

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
