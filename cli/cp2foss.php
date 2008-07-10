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
require_once("pathinclude.h.php");
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once("$WEBDIR/common/common.php");
cli_Init();

global $Plugins;

$Usage = "Usage: " . basename($argv[0]) . " [options] [archives]
  Options:
    -h       = this help message
    -v       = enable verbose debugging

  FOSSology storage options:
    -f path  = folder path for placing files (e.g., -f 'Fedora/ISOs/Disk 1')
               You do not need to specify '/System Repository'.
               All paths are under '/System Repository'.
    -A       = alphabet folders; organize uploads into folder a-c, d-f, etc.
    -AA num  = When using -A, specify the number of letters but folder (default: 3)
    -n name  = (optional) name for the upload (default: name it after the file)
    -d desc  = (optional) description for the update

  FOSSology processing queue options:
    -Q       = list all available processing agents
    -q       = specify a comma-separated list of agents, or 'all'
    NOTE: By default, no analysis agents are queued up.

  FOSSology source options:
    archive  = file, directory, or URL to the archive.
             If the archive is a file, then it is used as the source to add.
             If the archive is a directory, then ALL files under it are
             recursively added.
             If the archive is a URL, then it is retrieved and added.
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
$Verbose=0;

/************************************************************************/
/************************************************************************/
/************************************************************************/

/****************************************************
 GetBucketFolder(): Given an upload name and the number
 of letters per bucket, return the bucket folder name.
 ****************************************************/
function GetBucketFolder ($UploadName, $BucketGroupSize)
{
  $Letters = "abcdefghijklmnopqrstuvwxyz";
  $Numbers = "0123456789";
  $Name = strtolower(substr($UploadName,0,1));
  /* See if I can find the bucket */
  for($i=0; $i < 26; $i += $BucketGroupSize)
    {
    $Range = substr($Letters,$i,$BucketGroupSize);
    $Find = strpos($Range,$Name);
    if ($Find !== false)
      {
      if (($BucketGroupSize <= 1) || (strlen($Range) <= 1)) { return($Range); }
      return(substr($Range,0,1) . '-' . substr($Range,-1,1));
      }
    }
  /* Not a letter.  Check for numbers */
  $Find = strpos($Numbers,$Name);
  if ($Find !== false) { return("0-9"); }
  /* Not a letter. */
  return("Other");
} /* GetBucketFolder() */

/****************************************************
 GetFolder(): Given a folder path, return the folder_pk.
 NOTE: If any part of the folder path does not exist, then
 create it.
 NOTE: This is recursive!
 ****************************************************/
function GetFolder	($FolderPath, $Parent=NULL)
{
  global $DB;
  global $Verbose;
  if (empty($Parent)) { $Parent = FolderGetTop(); }
  if (empty($FolderPath)) { return($Parent); }
  list($Folder,$FolderPath) = split('/',$FolderPath,2);
  if (empty($Folder)) { return(GetFolder($FolderPath,$Parent)); }

  /* See if it exists */
  $SQLFolder = str_replace("'","''",$Folder);
  $SQL = "SELECT * FROM folder
	INNER JOIN foldercontents ON child_id = folder_pk
	AND foldercontents_mode = '1'
	WHERE parent_fk = '$Parent' AND folder_name='$SQLFolder';";
  if ($Verbose > 1) { print "SQL=\n$SQL\n"; }
  $Results = $DB->Action($SQL);
  if (count($Results) <= 0)
    {
    /* Need to create folder */
    global $Plugins;
    $P = &$Plugins[plugin_find_id("folder_create")];
    if (empty($P))
      {
      print "FATAL: Unable to find folder_create plugin.\n";
      exit(-1);
      }
    if ($Verbose) { print "Folder not found: Creating $Folder\n"; }
    $P->Create($Parent,$Folder,"");
    $Results = $DB->Action($SQL);
    }

  $Parent = $Results[0]['folder_pk'];
  return(GetFolder($FolderPath,$Parent));
} /* GetFolder() */

/****************************************************
 UploadOne(): Given one object (file or URL), upload it.
 This is a function because it is can also be recursive!
 ****************************************************/
function UploadOne ($FolderPath,$UploadArchive,$UploadName,$UploadDescription)
{
  global $Verbose;
  global $QueueList;

  /* $Mode determines where it came from */
  if (preg_match("@^[a-zA-Z0-9_]+://@",$UploadArchive))
    {
    $Mode = 1<<2; /* Looks like a URL */
    }
  else if (file_exists($UploadArchive))
    {
    $Mode = 1<<4; /* Looks like a filesystem */
    }
  else if (is_dir($UploadArchive))
    {
    /* It's a directory, punt! */
    return;
    }

  /* Get the folder's primary key */
  global $OptionA; /* Should it use bucket names? */
  if ($OptionA)
    {
    $FolderPk = GetFolder($FolderPath . "/" . GetBucketFolder($UploadName,$bucket_size));
    }
  else
    {
    $FolderPk = GetFolder($FolderPath);
    }

  /* Create the upload for the file */
  if ($Verbose > 1) { print "JobAddUpload($UploadName,$UploadArchive,$UploadDescription,$Mode,$FolderPk);\n"; }
  $UploadPk = JobAddUpload($UploadName,$UploadArchive,$UploadDescription,$Mode,$FolderPk);

  /* Tell wget_agent to actually grab the upload */
  global $AGENTDIR;
  $Cmd = "$AGENTDIR/wget_agent -k '$UploadPk' '$UploadArchive'";
  if ($Verbose) { print "CMD=$Cmd\n"; }
  system($Cmd);

  /* Schedule the unpack */
  $Cmd = "fossjobs.php -U '$UploadPk' -A agent_unpack";
  if ($Verbose) { print "CMD=$Cmd\n"; }
  system($Cmd);
  if (!empty($QueueList))
    {
    switch($QueueList)
      {
      case 'agent_unpack':	$Cmd=""; break; /* already scheduled */
      case 'ALL':
      case 'all':	
	$Cmd = "fossjobs.php -U '$UploadPk'";
	break;
      default:
	$Cmd = "fossjobs.php -U '$UploadPk' -A '$QueueList'";
	break;
      }
    if ($Verbose) { print "CMD=$Cmd\n"; }
    system($Cmd);
    }
} /* UploadOne() */

/************************************************************************/
/************************************************************************/
/************************************************************************/
/* Process each parameter */
$FolderPath="/";
$UploadDescription="";
$UploadName="";
$QueueList="";
$bucket_size = 3;
for($i=1; $i < $argc; $i++)
  {
  switch($argv[$i])
    {
    case '-A': /* use alphabet buckets */
	$OptionA = true;
	break;
    case '-AA': /* use alphabet buckets */
	$i++; $bucket_size = intval($argv[$i]);
	if ($bucket_size < 1) { $bucket_size=1; }
	break;
    case '-f': /* folder path */
    case '-p': /* depricated 'path' to folder */
	$i++; $FolderPath = $argv[$i];
	/* idiot check for absolute paths */
	$FolderPath = preg_replace('@^/*@',"",$FolderPath);
	$FolderPath = preg_replace('@/*$@',"",$FolderPath);
	$FolderPath = preg_replace("@^Software Repository/@","",$FolderPath);
	$FolderPath = preg_replace('@//*@',"/",$FolderPath);
	$FolderPath = '/' . $FolderPath;
	break;
    case '-R': /* obsolete: recurse directories */
    case '-w': /* obsolete: URL switch to use wget */
	break;
    case '-d': /* specify upload description */
	$i++; $UploadDescription = $argv[$i];
	break;
    case '-n': /* specify upload name */
	$i++; $UploadName = $argv[$i];
	break;
    case '-Q':
	system("fossjobs.php -a");
	return(0);
    case '-q':
	$i++; $QueueList = $argv[$i];
	break;
    case '-v':
	$Verbose++;
	break;
    case '-h':
    case '-?':
	print $Usage . "\n";
	return(0);
    case '-a': /* it's an archive! */
	/* ignore -a since the next name is a file. */
	break;
    default:
	if (substr($argv[$i],0,1)=='-')
	  {
	  print "Unknown parameter: '" . $argv[$i] . "'\n";
	  print $Usage . "\n";
	  return(1);
	  }
	/* No break! No hyphen means it is a file! */
	$UploadArchive = $argv[$i];
	print "Loading $UploadArchive\n";
	print "  Uploading to folder: '$FolderPath'\n";
	if (empty($UploadName)) { $UploadName = basename($UploadArchive); }
	print "  Uploading as '$UploadName'\n";
	if (!empty($UploadDescription)) { print "  Upload description: '$UploadDescription'\n"; }
	UploadOne($FolderPath,$UploadArchive,$UploadName,$UploadDescription);

	/* prepare for next parameter */
	$UploadName="";
	break;
    } /* switch */
  } /* for each parameter */

?>
