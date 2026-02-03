<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief Common function of REQUEST parmeters
 */

/** Integer parameter */
define("PARM_INTEGER",1);
/** Number (decimal) parameter */
define("PARM_NUMBER",2);
/** String parameter (URI decoded) */
define("PARM_STRING",3);
/** String parameter (stripslashed) */
define("PARM_TEXT",4);
/** Raw parameter */
define("PARM_RAW",5);

/**
 * \brief This function will retrieve the variables and check data types.
 *
 * Plugins should not use globals to access HTTP variables.
 * This is because HTTP variables may contain hostile code/values.
 * - \b PARM_INTEGER: Only integers are returned.
 * - \b PARM_NUMBER: Only numbers (decimals are fine) are returned.
 * - \b PARM_STRING: The variable is converted from URI encoding to text.
 * - \b PARM_TEXT: Like PARM_STRING, but all safe quoting is removed.
 * - \b PARM_RAW: Return the raw value.
 *
 * If the variable does not exist, OR is the wrong type (e.g., a string
 * when it should be a number), then nothing is returned.
 *
 * \note If a plugin wants to access these variable directly, it can.
 * But it is responsible for all safety checks.
 *
 * \param string $parameterName Variable name
 * \param $parameterType        Variable type (see the defines for allowed values)
 *
 * \return String of variables
 */
function GetParm($parameterName, $parameterType)
{
  $Var = null;
  if (array_key_exists($parameterName, $_GET)) {
    $Var = $_GET[$parameterName];
  }
  if (! isset($Var) && isset($_POST) && array_key_exists($parameterName, $_POST)) {
    $Var = $_POST[$parameterName];
  }
  if (! isset($Var) && isset($_SERVER) &&
    array_key_exists($parameterName, $_SERVER)) {
    $Var = $_SERVER[$parameterName];
  }
  if (! isset($Var) && isset($_SESSION) &&
    array_key_exists($parameterName, $_SESSION)) {
    $Var = $_SESSION[$parameterName];
  }
  if (! isset($Var) && isset($_COOKIE) &&
    array_key_exists($parameterName, $_COOKIE)) {
    $Var = $_COOKIE[$parameterName];
  }
  if (! isset($Var)) {
    return null;
  }
  /* Convert $Var to a string */
  switch ($parameterType) {
    case PARM_INTEGER:
      return (intval($Var));
    case PARM_NUMBER:
      return (floatval($Var));
    case PARM_TEXT:
      return (stripslashes($Var));
    case PARM_STRING:
      return (urldecode($Var));
    case PARM_RAW:
      return ($Var);
  }
  return null;
} // GetParm()

/**
 * \brief Get the URI + query to this location.
 */
function Traceback()
{
  return(@$_SERVER['REQUEST_URI']);
} // Traceback()

/**
 * \brief Get the URI without query to this location.
 */
function Traceback_uri()
{
  $V = explode('?',@$_SERVER['REQUEST_URI'],2);
  return($V[0]);
} // Traceback_uri()

/**
 * \brief Get the URI query to this location.
 *
 * If ShowMod is set, then the module name is included.
 * Else, this begins with the first parameter.
 */
function Traceback_parm($ShowMod=1)
{
  $V = explode('?',@$_SERVER['REQUEST_URI'],2);
  /* need to check the size to avoid accessing past the array, there are
   * request URI's that only have a single entry after the explode.
   */
  if (count($V) >= 2) {
    $V = preg_replace("/^mod=/", "", $V[1]);
  } else if (count($V) == 1) {
    $V = 'Default';
  }

  if (! $ShowMod) {
    $V = preg_replace("/^[^&]*/", "", $V);
  }

  if (is_array($V)) {
    return $V[0];
  }
  return $V;
} // Traceback_parm()

/**
 * \brief Create a new URI, keeping only these items.
 *
 * \param array $List Array of parameter names
 */
function Traceback_parm_keep($List)
{
  $Opt="";
  $Max = count($List);
  for ($i = 0; $i < $Max; $i ++) {
    $L = &$List[$i];
    $Val = GetParm($L, PARM_STRING);
    if (! empty($Val)) {
      $Opt .= "&" . "$L=$Val";
    }
  }
  return($Opt);
} // Traceback_parm_keep()

  return($V);
} // Traceback_uri()

/**
 * \brief Get the protocol scheme (http or https)
 *
 * @return string
 */
function getProtocolScheme()
{
  if (!empty(@$_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
  }

  if ((!empty(@$_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
      (@$_SERVER['SERVER_PORT'] == 443)) {
    return 'https';
  }

  return 'http';
}

/**
 * \brief Get the total url without query
 */
function tracebackTotalUri()
{
  $protoUri = getProtocolScheme() . '://';
  $portUri = (@$_SERVER["SERVER_PORT"] == "80" || @$_SERVER["SERVER_PORT"] == "443") ? "" : (":" . @$_SERVER["SERVER_PORT"]);
  $V = $protoUri . @$_SERVER['SERVER_NAME'] . $portUri . Traceback_uri();
  return($V);
} // tracebackTotalUri()

/**
 * \brief Get the total url without query
 */
function tracebackTotalUri()
{
  if (! empty(@$_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' &&
    $_SERVER['HTTPS'] == 'on' || @$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $protoUri = 'https://';
  } else {
    $protoUri = 'http://';
  }
  $portUri = (@$_SERVER["SERVER_PORT"] == "80") ? "" : (":" . @$_SERVER["SERVER_PORT"]);
  $V = $protoUri . @$_SERVER['SERVER_NAME'] . $portUri . Traceback_uri();
  return($V);
} // tracebackTotalUri()
