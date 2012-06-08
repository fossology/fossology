#!/usr/bin/php
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
 * @file fossinit.php
 * @brief This program applies core-schema.dat to the database (which
 *        must exist) and updates the license_ref table.
 *
 * This should be used immediately after an install or update.
 *
 * @return 0 for success, 1 for failure.
 **/

/* User must be in group fossy! */
$GID = posix_getgrnam("fossy");
posix_setgid($GID['gid']);
$Group = `groups`;
if (!preg_match("/\sfossy\s/",$Group) && (posix_getgid() != $GID['gid']))
{
  print "FATAL: You must be in group 'fossy' to update the FOSSology database.\n";
  exit(1);
}

/* Initialize the program configuration variables */
$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection
$Plugins = array();

/* Note: php 5 getopt() ignores options not specified in the function call, so add
 * dummy options in order to catch invalid options.
 */
$AllPossibleOpts = "abc:d:ef:ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

/* defaults */
$Verbose = false;
$DatabaseName = "fossology";
$UpdateLiceneseRef = false;
$sysconfdir = '';

/* command-line options */
$Options = getopt($AllPossibleOpts);
foreach($Options as $Option => $OptVal)
{
  switch($Option)
  {
    case 'c': /* set SYSCONFIDR */
      $sysconfdir = $OptVal;
      break;
    case 'd': /* optional database name */
      $DatabaseName = $OptVal;
      break;
    case 'f': /* schema file */
      $SchemaFilePath = $OptVal;
      break;
    case 'h': /* help */
      Usage();
    case 'l': /* update the license_ref table */
      $UpdateLiceneseRef = true;
      break;
    case 'v': /* verbose */
      $Verbose = true;
      break;
    default:
      echo "Invalid Option \"$Option\".\n";
      Usage();
  }
}

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap($sysconfdir);
require_once("$MODDIR/lib/php/libschema.php");
// don't think we want all the other common libs
require_once("$MODDIR/lib/php/common.php");

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

if (empty($SchemaFilePath)) $SchemaFilePath = "$MODDIR/www/ui/core-schema.dat";

if (!file_exists($SchemaFilePath))
{
  print "FAILED: Schema data file ($SchemaFilePath) not found.\n";
  exit(1);
}

$FailMsg = ApplySchema($SchemaFilePath, $Verbose, $DatabaseName);
if ($FailMsg)
{
  print "ApplySchema failed: $FailMsg\n";
  exit(1);
}
else
{
  if ($Verbose) { print "DB schema has been updated.\n"; }
  $State = 1;
  $Filename = "$MODDIR/www/ui/init.ui";
  if (file_exists($Filename))
  {
    if ($Verbose) { print "Removing flag '$Filename'\n"; }
    if (is_writable("$MODDIR/www/ui/")) { $State = unlink($Filename); }
    else { $State = 0; }
  }
  if (!$State)
  {
    print "Failed to remove $Filename\n";
    print "Remove this file to complete the initialization.\n";
  }
  else
  {
    print "Database schema update completed successfully.\n";
  }
}

/* initialize the license_ref table */
if ($UpdateLiceneseRef) 
{
  print "Update reference licenses\n";
  initLicenseRefTable(false);
}

/* for the 1.4 -> 2.0 migration, create and populate the new ars tables */
global $LIBEXECDIR;
require_once("$LIBEXECDIR/dbmigrate_1.4-2.0.php");
print "Migrate data\n";
Migrate_14_20($Verbose);

exit(0);


/** \brief Print Usage statement.
 *  \return No return, this calls exit.
 **/
function Usage()
{
  global $argv;

  $usage = "Usage: " . basename($argv[0]) . " [options]
  Update FOSSology database.  Options are:
  -c  path to fossology configuration files
  -d  {database name} default is 'fossology'
  -f  {file} update the schema with file generaged by schema-export.php
  -l  update the license_ref table with fossology supplied licenses
  -v  enable verbose preview (prints sql that would happen, but does not execute it, DB is not updated)
  -h  this help usage";
  print "$usage\n";
  exit(0);
}

/**
 * \brief Load the license_ref table with licenses.
 *
 * \param $Verbose display database load progress information.  If $Verbose is false,
 * this function only prints errors.
 *
 * \return 0 on success, 1 on failure
 **/
function initLicenseRefTable($Verbose)
{
  global $LIBEXECDIR;
  global $PG_CONN;

  $sql = "SELECT count(rf_pk) from license_ref;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if ($row && $row['count'] > 0) {
    echo "NOTE: license_ref table already initialized; skipping...\n";
    return;
  }

  if (!is_dir($LIBEXECDIR)) {
    print "FATAL: Directory '$LIBEXECDIR' does not exist.\n";
    return (1);
  }
  $dir = opendir($LIBEXECDIR);
  if (!$dir) {
    print "FATAL: Unable to access '$LIBEXECDIR'.\n";
    return (1);
  }
  $file = "$LIBEXECDIR/licenseref.sql";

  if (is_file($file)) {
    $handle = fopen($file, "r");
    $pattern = '/^INSERT INTO/';
    $sql = "";
    $flag = 0;
    while(!feof($handle))
    {
      $buffer = fgets($handle, 4096);
      if ( preg_match($pattern, $buffer) == 0)
      {
        $sql .= $buffer;
        continue;
      } else {
        if ($flag)
        {
          @$result = pg_query($PG_CONN, $sql);
          if ($result == FALSE)
          {
            $PGError = pg_last_error($PG_CONN);
            print "SQL failed: $PGError\n";
          }
          @pg_free_result($result);
        }
        $sql = $buffer;
        $flag = 1;
      }
    }
    @$result = pg_query($PG_CONN, $sql);
    if ($result == FALSE)
    {
      $PGError = pg_last_error($PG_CONN);
      print "SQL failed: $PGError\n";
    }
    @pg_free_result($result);
    fclose($handle);
  } else {
    print "FATAL: Unable to access '$file'.\n";
    return (1);
  }
  return (0);
} // initLicenseRefTable()

/************************************************/
/******  From src/lib/php/bootstrap.php  ********/
/** Included here so that fossinit can run from any directory **/
/**
 * \brief Bootstrap the fossology php library.
 *  - Determine SYSCONFDIR
 *  - parse fossology.conf
 *  - source template (require_once template-plugin.php)
 *  - source common files (require_once common.php)
 *
 * The following precedence is used to resolve SYSCONFDIR:
 *  - $SYSCONFDIR path passed in ($sysconfdir)
 *  - environment variable SYSCONFDIR
 *  - ./fossology.rc
 *
 * Any errors are fatal.  A text message will be printed followed by an exit(1)
 *
 * \param $sysconfdir Typically from the caller's -c command line parameter
 *
 * \return the $SysConf array of values.  The first array dimension
 * is the group, the second is the variable name.
 * For example:
 *  -  $SysConf[DIRECTORIES][MODDIR] => "/mymoduledir/
 *
 * The global $SYSCONFDIR is also set for backward compatibility.
 *
 * \Note Since so many files expect directory paths that used to be in pathinclude.php
 * to be global, this function will define the same globals (everything in the
 * DIRECTORIES section of fossology.conf).
 */
function bootstrap($sysconfdir="")
{
  $rcfile = "fossology.rc";

  if (empty($sysconfdir))
  {
    $sysconfdir = getenv('SYSCONFDIR');
    if ($sysconfdir === false)
    {
      if (file_exists($rcfile)) $sysconfdir = file_get_contents($rcfile);
      if ($sysconfdir === false)
      {
        /* NO SYSCONFDIR specified */
        $text = _("FATAL! System Configuration Error, no SYSCONFDIR.");
        echo "$text\n";
        exit(1);
      }
    }
  }

  $sysconfdir = trim($sysconfdir);
  $GLOBALS['SYSCONFDIR'] = $sysconfdir;

  /*************  Parse fossology.conf *******************/
  $ConfFile = "{$sysconfdir}/fossology.conf";
  if (!file_exists($ConfFile))
  {
    $text = _("FATAL! Missing configuration file: $ConfFile");
    echo "$text\n";
    exit(1);
  }
  $SysConf = parse_ini_file($ConfFile, true);
  if ($SysConf === false)
  {
    $text = _("FATAL! Invalid configuration file: $ConfFile");
    echo "$text\n";
    exit(1);
  }

  /* evaluate all the DIRECTORIES group for variable substitutions.
   * For example, if PREFIX=/usr/local and BINDIR=$PREFIX/bin, we
   * want BINDIR=/usr/local/bin
   */
  foreach($SysConf['DIRECTORIES'] as $var=>$assign)
  {
    /* Evaluate the individual variables because they may be referenced
     * in subsequent assignments.
     */
    $toeval = "\$$var = \"$assign\";";
    eval($toeval);

    /* now reassign the array value with the evaluated result */
    $SysConf['DIRECTORIES'][$var] = ${$var};
    $GLOBALS[$var] = ${$var};
  }

  if (empty($MODDIR))
  {
    $text = _("FATAL! System initialization failure: MODDIR not defined in $SysConf");
    echo $text. "\n";
    exit(1);
  }

  //require("i18n.php"); DISABLED until i18n infrastructure is set-up.
  require_once("$MODDIR/www/ui/template/template-plugin.php");
  require_once("$MODDIR/lib/php/common.php");
  return $SysConf;
}
?>
