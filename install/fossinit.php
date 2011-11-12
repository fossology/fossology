#!/usr/bin/php
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
 @file fossinit.php
 @brief This program applies core-schema.dat to the database.

 This should be used immediately after an install, and before
 starting up the scheduler.

 @return 0 for success, 1 for failure.
 **/

/* Must run as group fossy! */
$GID = posix_getgrnam("fossy");
posix_setgid($GID['gid']);
$Group = `groups`;
if (!preg_match("/\sfossy\s/",$Group) && (posix_getgid() != $GID['gid']))
{
  print "FATAL: You must be in group 'fossy' to update the FOSSology database.\n";
  exit(1);
}

/* command-line options */
$Options = getopt('vh');
if (array_key_exists('h',$Options)) Usage();
$Verbose = array_key_exists("v",$Options);
if ($Verbose == "")  $Verbose=0;

/* Initialize the program configuration variables */
$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection
$Plugins = array();

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

//debugprint($SysConf, "SysConf");
/* Load plugins */
plugin_load();

/* Initialize the system! */
$Schema = &$Plugins[plugin_find_any_id("schema")];
if (empty($Schema))
{
  print "FAILED: Unable to find the schema plugin.\n";
  exit(1);
}

$Filename = "$MODDIR/www/ui/core-schema.dat";
if (!file_exists($Filename))
{
  print "FAILED: Schema data file ($Filename) not found.\n";
  exit(1);
}

$FailFlag = ApplySchema($Filename,0,$Verbose);
if (!$FailFlag)
{
  $State = 1;
  $Filename = "$WEBDIR/init.ui";
  if (file_exists($Filename))
  {
    if ($Verbose) { print "Removing flag '$Filename'\n"; }
    if (is_writable($WEBDIR)) { $State = unlink($Filename); }
    else { $State = 0; }
  }
  if (!$State)
  {
    print "Failed to remove $Filename\n";
    print "Remove this file to complete the initialization.\n";
  }
  else
  {
    print "Initialization completed successfully.\n";
  }
}
else
{
  print "Initialization had errors.\n";
  exit(1);
}
exit(0);

/** \brief Print Usage statement.
 *  \return No return, this calls exit.
 **/
function Usage()
{
   $usage = "Usage: " . basename($argv[0]) . " [options]
  Initialize database schema\n
  -v  = enable verbose mode (lists each module being processed)
  -h  = this help usage";
  print "$usage\n";
  exit(0);
}

/************************************************/
/******  From src/lib/php/bootstrap.php  ********/
/** Included here so that fossinit can run from any directory **/
/**
 * \file bootstrap.php
 * \brief Fossology system bootstrap
 * This file may be DUPLICATED in any php utility that needs to 
 * bootstrap itself.
 */

/**
 * \brief Bootstrap the fossology php library.
 *  - Determine SYSCONFDIR
 *  - parse fossology.conf
 *  - source template (require_once template-plugin.php)
 *  - source common files (require_once common.php)
 * 
 * The following precedence is used to resolve SYSCONFDIR:
 *  - $SYSCONFDIR path passed in
 *  - environment variable SYSCONFDIR
 *  - ./fossology.rc
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
function bootstrap()
{
  $rcfile = "fossology.rc";

  $sysconfdir = getenv('SYSCONFDIR');
  if ($sysconfdir === false)
  {
    if (file_exists($rcfile)) $sysconfdir = file_get_contents($rcfile);
    if ($sysconfdir === false)
    {
      /* NO SYSCONFDIR specified */
      $text = _("FATAL: System Configuration Error, no SYSCONFDIR.");
      echo "<hr><h3>$text</h3><hr>";
      exit(1);
    }
  }

  $sysconfdir = trim($sysconfdir);
  $GLOBALS['SYSCONFDIR'] = $sysconfdir;

  /*************  Parse fossology.conf *******************/
  $ConfFile = "{$sysconfdir}/fossology.conf";
  $SysConf = parse_ini_file($ConfFile, true);

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
    $text = _("FATAL: System initialization failure: MODDIR not defined in fossology.conf");
    echo $text. "\n"; 
    exit;
  }

  //require("i18n.php"); DISABLED until i18n infrastructure is set-up.
  require_once("$MODDIR/www/ui/template/template-plugin.php");
  require_once("$MODDIR/lib/php/common.php");
  return $SysConf;
}
?>
