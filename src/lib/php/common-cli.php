<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief General purpose classes used by fossology CLI programs.
 *
 * \package common-cli
 *
 * \version "$Id: common-cli.php 3444 2010-09-10 03:52:55Z madong $"
 */

/**
 * \brief Initialize the fossology environment for CLI use. This routine loads
 * the plugins so they can be use by cli programs. In the process of doing
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
  /* The auth module hijacks and disables plugins, so turn it off. */
  if (array_key_exists("auth", $Plugins)) {
    $P = &$Plugins["auth"];
    if (!empty($P)) {
      $P->State = PLUGIN_STATE_FAIL;
    }
  }
  $_SESSION['User'] = 'fossy';

  /* Initialize plugins */
  /* This registers plugins with the menu structure and start the DB
   connection. */
  plugin_preinstall();

  return(true);
} // cli_Init()

/**
 * \brief Write/append a message to the log handle passed in.
 *
 * \param string $handle  The path to the file
 * \param string $message The message to put in the log file, the string
 * should not have a new line at the end, this function will add it.
 * \param string $mode    The open mode, either 'a' or 'w'
 *
 * \note It is up to the caller to manage the mode
 *
 * \return null on success, string for failure
 */
function cli_logger($handle, $message, $mode='a')
{
  $message .= "\n";
  $FR = fopen($handle, $mode) or
  die("Can't open $handle, $php_errormsg\n");
  $wrote = fwrite ($FR, $message);
  fflush($FR);
  fclose($FR);
  if ($wrote == -1) {
    $text = _("ERROR: could not write message to");
    return "$text $handle\n";
  }
  return null;
}
