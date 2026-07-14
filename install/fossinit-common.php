<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Common functions required by init scripts
 */


/**
 * Resolve simple $VAR and ${VAR} placeholders against already-known values.
 *
 * @param string $value The raw config value.
 * @param array $scope Previously resolved values.
 * @return string
 */
if (!function_exists('resolve_config_value')) {
  function resolve_config_value($value, array $scope)
  {
    return preg_replace_callback('/\$(?:([A-Za-z_][A-Za-z0-9_]*)|\{([A-Za-z_][A-Za-z0-9_]*)\})/', static function ($matches) use ($scope) {
      $name = !empty($matches[1]) ? $matches[1] : $matches[2];
      return array_key_exists($name, $scope) ? $scope[$name] : $matches[0];
    }, $value);
  }
}


function guessSysconfdir()
{
  $rcfile = "fossology.rc";
  $varfile = dirname(__DIR__).'/variable.list';
  $sysconfdir = getenv('SYSCONFDIR');
  if ((false===$sysconfdir) && file_exists($rcfile))
  {
    $sysconfdir = file_get_contents($rcfile);
  }
  if ((false===$sysconfdir) && file_exists($varfile))
  {
    $ini_array = parse_ini_file($varfile);
    if($ini_array!==false && array_key_exists('SYSCONFDIR', $ini_array))
    {
      $sysconfdir = $ini_array['SYSCONFDIR'];
    }
  }
  if (false===$sysconfdir)
  {
    $text = _("FATAL! System Configuration Error, no SYSCONFDIR.");
    echo "$text\n";
    exit(1);
  }
  return $sysconfdir;
}


/**
 * \brief Determine SYSCONFDIR, parse fossology.conf
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
  if (empty($sysconfdir))
  {
    $sysconfdir = guessSysconfdir();
    echo "assuming SYSCONFDIR=$sysconfdir\n";
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
  $resolvedValues = array();
  foreach($SysConf['DIRECTORIES'] as $var=>$assign)
  {
    $resolvedValue = resolve_config_value($assign, $resolvedValues);
    $resolvedValues[$var] = $resolvedValue;

    /* now reassign the array value with the evaluated result */
    $SysConf['DIRECTORIES'][$var] = $resolvedValue;
    ${$var} = $resolvedValue;
    $GLOBALS[$var] = $resolvedValue;
  }

  if (empty($MODDIR))
  {
    $text = _("FATAL! System initialization failure: MODDIR not defined in $SysConf");
    echo "$text\n";
    exit(1);
  }

  //require("i18n.php"); DISABLED until i18n infrastructure is set-up.
  require_once("$MODDIR/lib/php/common.php");
  require_once("$MODDIR/lib/php/Plugin/FO_Plugin.php");
  return $SysConf;
}

/**
 * @brief Using bash's read command, read input from STDIN.
 *
 * This function runs a new bash shell and execute read command on it with a
 * timeout set.
 * @param integer $seconds Timeout in seconds
 * @param string  $default Default value to return (in case of timeout)
 * @return string The input read from STDIN or default value.
 */
function readlineTimeout($seconds, $default)
{
  return trim(shell_exec('bash -c ' .
    escapeshellarg('fossstdin=' . escapeshellarg($default) .
    ';read -t ' . ((int)$seconds) . ' fossstdin;echo "$fossstdin"')));
}
