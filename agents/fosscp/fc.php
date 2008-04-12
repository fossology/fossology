#!/usr/bin/php
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
 * fosscp
 *
 * cp2foss agent, upload items from the ui (upload from server).
 * It is expected that this program will be called from a ui-plugin.
 * Scheduler should pass in the following parameters
 *
 * @param string $archive the path to the archive to upload
 * @param string $folder_pk the folder id to load under
 * @param (optional) string $description a short meaningful description
 * @param (optional) string $name the name to use for the upload
 * @param string $recurse recurse flag (0 | 1). 0 is only files, 1 is the
 * complete tree.
 * @parm int $upload_pk the upload associated with this request
 *
 * @return 0 for success, 1 for failure....
 *
 * @version "$Id: $"
 *
 */

/*
 * This agent should appear in the scheduler.conf as:
 * agent=fosscopy |
 * /usr/local/fossology/agents/engine-shell fosscp_agent \
 * '/usr/local/fossology/agents/fosscp_agent'
 *
 * engine-shell will convert all of the SQL columns into environment
 * variables.  E.G. The MSQ will return pfile=... and pfile_fk=...
 * These will become $ARG_pfile and $ARG_pfile_fk.
 *
 */
/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
$GlobalReady = 1;

require_once("pathinclude.h.php");
global $LIBDIR;
global $WEBDIR;
require_once("$WEBDIR/common/common-cli.php");

global $Plugins;
global $LIBEXECDIR;
$UI_CLI=1;
//cli_Init();

//echo "DEBUG: Starting fosscp agent\n";
//echo "LOG: Starting fosscp agent\n";


exit(0);
?>