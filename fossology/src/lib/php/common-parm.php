<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 ***********************************************************/

/**
 * \file common-parm.php
 * \brief common function of parmeters
 */

define("PARM_INTEGER",1);
define("PARM_NUMBER",2);
define("PARM_STRING",3);
define("PARM_TEXT",4);
define("PARM_RAW",5);

/**
 * \brief This function will retrieve the variables and check data types.
*  Plugins should not use globals to access HTTP variables.
 * This is because HTTP variables may contain hostile code/values.
 * PARM_INTEGER: Only integers are returned.
 * PARM_NUMBER: Only numbers (decimals are fine) are returned.
 * PARM_STRING: The variable is converted from URI encoding to text.
 * PARM_TEXT: Like PARM_STRING, but all safe quoting is removed.
 * PARM_RAW: Return the raw value.
 * If the variable does not exist, OR is the wrong type (e.g., a string
 * when it should be a number), then nothing is returned.
 * NOTE: If a plugin wants to access these variable directly, it can.
 * But it is responsible for all safety checks.
 *
 * \param $Name variable name
 * \param $Type variable type
 *
 * \return string of variables
 */
function GetParm($Name,$Type)
{
  $Var = @$_GET[$Name];
  if (!isset($Var)) { $Var = @$_POST[$Name]; }
  if (!isset($Var)) { $Var = @$_SERVER[$Name]; }
  if (!isset($Var)) { $Var = @$_SESSION[$Name]; }
  if (!isset($Var)) { $Var = @$_COOKIE[$Name]; }
  if (!isset($Var)) {
    return;
  }
  /* Convert $Var to a string */
  switch($Type)
  {
    case PARM_INTEGER:
      return(intval($Var));
    case PARM_NUMBER:
      return(floatval($Var));
    case PARM_TEXT:
      return(stripslashes($Var));
    case PARM_STRING:
      return(urldecode($Var));
    case PARM_RAW:
      return($Var);
  }
  return;
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
 * If ShowMod is set, then the module name is included.
 * Else, this begins with the first parameter.
 */
function Traceback_parm($ShowMod=1)
{
  $V = array();
  $V = explode('?',@$_SERVER['REQUEST_URI'],2);
  /* need to check the size to avoid accessing past the array, there are
   * request URI's that only have a single entry after the explode.
   */
  if(count($V) >= 2) {
    $V = preg_replace("/^mod=/","",$V[1]);
  }

  if (!$ShowMod)
  {
    $V = preg_replace("/^[^&]*/","",$V);
  }
  return($V);
} // Traceback_parm()

/**
 * \brief Create a new URI, keeping only these items.
 */
function Traceback_parm_keep($List)
{
  $Opt="";
  $Max = count($List);
  for($i=0; $i < $Max ; $i++)
  {
    $L = &$List[$i];
    $Val = GetParm($L,PARM_STRING);
    if (!empty($Val)) { $Opt .= "&" . "$L=$Val"; }
  }
  return($Opt);
} // Traceback_parm_keep()

/**
 * \brief Get the directory of the URI without query.
 */
function Traceback_dir()
{
  $V = explode('?',@$_SERVER['REQUEST_URI'],2);
  $V = $V[0];
  $i = strlen($V);
  while(($i > 0) && ($V[$i-1] != '/')) { $i--; }
  $V = substr($V,0,$i);
  return($V);
} // Traceback_uri()

?>
