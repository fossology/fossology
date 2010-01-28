#!/usr/bin/php
<?php
/*
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
 */

/**
 * fossinit-agent
 * \brief Initialize the FOSSology agent machines, things that need to be
 * done individually on each agent machine. This should be used after 
 * installing, and before starting up the scheduler.
 *
 * @param string $flags various options for the program, see usage.
 *
 * @return 0 for success, 1 for failure.
 */

/* Have to set this or else plugins will not load. */
$GlobalReady = 1;

/* Must run as group fossy! */
$GID = posix_getgrnam("fossy");
posix_setgid($GID['gid']);
$Group = `groups`;
if (!preg_match("/\sfossy\s/",$Group) && (posix_getgid() != $GID['gid']))
{
	print "ERROR: Unable to run as group 'fossy'\n";
	exit(1);
}

/* get globals */
require_once(dirname(__FILE__) . '/../share/fossology/php/pathinclude.php');

global $LIBEXECDIR;
global $BINDIR;
global $WEBDIR;

/* initialize the cli environment... (load the plugins) */
$UI_CLI = 1; /* this is a command-line program */
require_once("$WEBDIR/common/common.php");
//require_once("$WEBDIR/common/common-cli.php");
//require_once("$WEBDIR/common/common-plugin.php");
cli_Init();

require_once("$LIBEXECDIR/libschema.php");

$FailFlag = NULL;
$Debug = NULL;

function _all($Verbose)
{
	$res = NULL;
	$res = _bSamInit($Verbose,$Debug);
	return($res);
}

function _bSamInit($Verbose,$Debug)
{
	// create bsam license cache
	if($Verbose)
	{
		$res = NULL;
		print "  Initializing bSam License Cache\n";
		flush();
		$res = initBsamFiles($Debug);
	}
	else
	{
		$res = initBsamFiles($Debug);
	}
	return($res);
} // _bSamInit()


$usage = "Usage: " . basename($argv[0]) . " [options]
  -a  = perform all actions (b)
  -b  = crate bsam license cache
  -D  = enable debug 
  -h  = this help usage;
  -v  = enable verbose mode (lists each module being processed)";

/* Load command-line options */
/*** -v  = verbose ***/
$Options = getopt('abdivh');
if (array_key_exists('h',$Options))
{
	print "$usage\n";
	exit(0);
}
$Verbose = array_key_exists("v",$Options);
if ($Verbose == "") { $Verbose=0; }

// for debugging, keep verbose on
$Verbose=TRUE;


if (array_key_exists('a',$Options))
{
	$FailFlag = _all($Verbose);
}

if (array_key_exists('b',$Options))
{
	// run bsam licCache
	$FailFlag = _bSamInit($Verbose,$Debug);
}

if (array_key_exists('D',$Options))
{
	$Debug = TRUE;
}

exit(0);
?>
