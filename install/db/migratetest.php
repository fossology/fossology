#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file migratetest.php
 * @brief Test migration function
 *
 * @return int 0 for success, 1 for failure.
 **/

/* Initialize the program configuration variables */
$PG_CONN = 0;   // Database connection
$Plugins = array();

/* defaults */
$Verbose = false;
$DatabaseName = "fossology";
$UpdateLiceneseRef = false;
$sysconfdir = '/usr/local/etc/fossology';

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap($sysconfdir);
$projectGroup = $SysConf['DIRECTORIES']['PROJECTGROUP'] ?: 'fossy';
$gInfo = posix_getgrnam($projectGroup);
posix_setgid($gInfo['gid']);
$groups = `groups`;
if (!preg_match("/\s$projectGroup\s/",$groups) && (posix_getgid() != $gInfo['gid']))
{
  print "FATAL: You must be in group '$projectGroup' to update the FOSSology database.\n";
  exit(1);
}

// don't think we want all the other common libs
require_once("$MODDIR/lib/php/common.php");
/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

/* migration */
require_once("./dbmigrate_2.1-2.2.php");
print "Migrate data from 2.1 to 2.2 \n";
Migrate_21_22($Verbose);

exit(0);


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
 * \param string $sysconfdir Typically from the caller's -c command line parameter
 *
 * \return mixed the $SysConf array of values.  The first array dimension
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
