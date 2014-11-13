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
 * \file common-cli.php
 * \brief general purpose classes used by fossology cli programs.
 *
 * \package common-cli
 *
 * \version "$Id: common-cli.php 3444 2010-09-10 03:52:55Z madong $"
 */

/**
 * \brief Initalize the fossology environment for cli use.  This routine loads
 * the plugins so they can be use by cli programs.  In the process of doing
 * that it disables the core-auth plugin.
 *
 * \return true
 */
function cli_Init()
{
  // every cli must perform these steps
  global $Plugins;

  /* Load the plugins */
  plugin_load(0); /* load but do not initialize */

  /* Turn off authentication */
  /** The auth module hijacks and disables plugins, so turn it off. **/
  $P = &$Plugins[plugin_find_any_id("auth")];
  if (!empty($P)) {
    $P->State = PLUGIN_STATE_FAIL;
  }
  $_SESSION['User'] = 'fossy';

  /* Initialize plugins */
  /** This registers plugins with the menu structure and start the DB
   connection. **/
  plugin_preinstall(); /* this registers plugins with menus */

  return(true);
} // cli_Init()

/**
 * \brief write/append a message to the log handle passed in.
 *
 * \param $handle - the path to the file
 * \param $message - the message to put in the log file, the string
 * should not have a new line at the end, this function will add it.
 * \param $mode - the open mode, either 'a' or 'w'
 *
 * \note it is up to the caller to manage the mode
 *
 * \return null on sucess, string for failure
 */
function cli_logger($handle, $message, $mode='a')
{
  $message .= "\n";
  $FR = fopen($handle, $mode) or
  die("Can't open $handle, $php_errormsg\n");
  $wrote = fwrite ($FR, $message);
  fflush($FR);
  if ($wrote == -1)
  {
    fclose($FR);
    $text = _("ERROR: could not write message to");
    return("$text $handle\n");
  }
  fclose($FR);
  return(Null);
}
?>
