<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file bootstrap.php
 * \brief Fossology system bootstrap
 * This file may be DUPLICATED in any php utility that needs to
 * bootstrap itself.
 *
 * @version "$Id$"
 */

/**
 * \brief Bootstrap the fossology test system
 *  - Determine SYSCONFDIR
 *  - parse fossology.conf
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
  $TR = NULL;
  if(!defined('TESTROOT'))
  {
    $TR = getenv('TESTROOT');
    if($TR = NULL )
    {
      echo "FATAL! bootstrap cannot determine TESTROOT\n";
    }

  }
  $rcfile = TESTROOT. '/fossologyTest.rc';

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
  return $SysConf;
}
