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
/**
 * General purpose classes used by fossology cli programs.
 *
 * @package common-cli
 * 
 * @version "$Id:$"
 *
 */

/**
 * function: cli_Init
 * 
 * Initalize the fossology environment for cli use.  This routine loads 
 * the plugins so they can be use by cli programs.  In the process of doing 
 * that it disables the core-auth plugin.
 *
 * @return true
 */

function cli_Init()
{
  // every cli must perform these steps
  global $WEBDIR; 
  /* Load the plugins */
  plugin_load("$WEBDIR/plugins",0); /* load but do not initialize */

  /* Turn off authentication */
  /** The auth module hijacks and disables plugins, so turn it off. **/
  $P = &$Plugins[plugin_find_any_id("auth")];
  if (!empty($P)) { $P->State = PLUGIN_STATE_FAIL; }

  /* Initialize plugins */
  /** This registers plugins with the menu structure and start the DB
   connection. **/
  plugin_init(); /* this registers plugins with menus */
  // Load the plugins
  plugin_load("$WEBDIR/plugins");

  return(true);
}

/**
 * function pdbg
 *
 * print a debug message and optionally dump a structure
 *
 * prints the message prepended with a DBG-> as the prefix.   The string
 * will have a new-line added to the end so that the caller does not have
 * to supply it.
 *
 * @param string $message the debug message to display
 * @param mixed  $dump    if not null, will be printed using print_r.
 *
 * @return Null
 *
 */
function cli_PrintDebugMessage($message, $dump=''){

  $dbg_msg = 'DBG->' . $message . "\n";

  echo $dbg_msg;

  if(isset($dump)){
    //    echo "\$dump is:\n";
    print_r($dump);
    echo "\n";
  }
  return;
}
?>
