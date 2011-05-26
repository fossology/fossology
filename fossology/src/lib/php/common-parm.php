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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

define("PARM_INTEGER",1);
define("PARM_NUMBER",2);
define("PARM_STRING",3);
define("PARM_TEXT",4);
define("PARM_RAW",5);

/************************************************************
 GetParm(): Plugins should not use globals to access HTTP variables.
 This is because HTTP variables may contain hostile code/values.
 This function will retrieve the variables and check data types.
 PARM_INTEGER: Only integers are returned.
 PARM_NUMBER: Only numbers (decimals are fine) are returned.
 PARM_STRING: The variable is converted from URI encoding to text.
 PARM_TEXT: Like PARM_STRING, but all safe quoting is removed.
 PARM_RAW: Return the raw value.
 If the variable does not exist, OR is the wrong type (e.g., a string
 when it should be a number), then nothing is returned.
 NOTE: If a plugin wants to access these variable directly, it can.
 But it is responsible for all safety checks.
 ************************************************************/
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

/************************************************************
 Traceback(): The URI + query to this location.
 ************************************************************/
function Traceback()
{
  return(@$_SERVER['REQUEST_URI']);
} // Traceback()

/************************************************************
 Traceback_uri(): The URI without query to this location.
 ************************************************************/
function Traceback_uri()
{
  $V = explode('?',@$_SERVER['REQUEST_URI'],2);
  return($V[0]);
} // Traceback_uri()

/************************************************************
 Traceback_parm(): The URI query to this location.
 If ShowMod is set, then the module name is included.
 Else, this begins with the first parameter.
 ************************************************************/
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

/************************************************************
 Traceback_parm_keep(): Create a new URI, keeping only these
 items.
 ************************************************************/
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

/************************************************************
 Traceback_dir(): The directory of the URI without query.
 ************************************************************/
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
