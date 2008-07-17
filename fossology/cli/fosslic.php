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

/**************************************************************
 fosslic
 
 Perform a command-line one-shot license analysis.
 
 @return 0 for success, 1 for failure.
 *************************************************************/

/* Have to set this or else plugins will not load. */
$GlobalReady = 1;

/* Load all code */
require_once("pathinclude.h.php");
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once("$WEBDIR/common/common.php");
cli_Init();

$Usage = "Usage: " . basename($argv[0]) . " [options] file [file [file...]]
  Perform a license analysis on the specified files.
  Options:
    -h       = this help message
    -v       = enable verbose debugging
  ";

/* Load command-line options */
global $DB;
global $Plugins;
$Verbose=0;
$Test=0;

/************************************************************************/
/************************************************************************/
/************************************************************************/

$Lic = &$Plugins[plugin_find_id("agent_license_once")];
if (empty($Lic))
	{
	print "FATAL: Unable to find license plugin.\n";
	exit(1);
	}

for($i=1; $i < $argc; $i++)
  {
  switch($argv[$i])
    {
    case '-v':
	$Verbose++;
	break;
    case '-h':
    case '-?':
	print $Usage . "\n";
	return(0);
    default:
	if (substr($argv[$i],0,1)=='-')
	  {
	  print "Unknown parameter: '" . $argv[$i] . "'\n";
	  print $Usage . "\n";
	  return(1);
	  }
	$_FILES['licfile']['tmp_name'] = $argv[$i];
	$_FILES['licfile']['size'] = filesize($argv[$i]);
	$V = $Lic->AnalyzeOne(0);
	print $argv[$i] . ": ";
	if (empty($V)) { print "None"; }
	else { print $V; }
	print "\n";
	break;
    } /* switch */
  } /* for each parameter */

return(0);
?>
