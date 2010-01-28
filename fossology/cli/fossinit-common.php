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
 * fossinit-common
 * \brief Initialize the FOSSology system common resources, things that
 * only need to be done once no matter how many agent machines there are.
 * This should be used on the scheduler machine after install and after
 * the db is created, and before starting up the scheduler.
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
	$res = _aSchema($Verbose);
/*	$res = _bSamInit($Verbose,$Debug); FIXME */
	$res = _initPA($Verbose,$Debug);
	return($res);
}

/**
 * FIXME: eventually we'll just generate the license.bsam cache once
 * for all agents and store it in the db or on an NFS mount that all
 * agents can get to, but until then it needs to be done on all agents
 * so it's still in fossinit-agents.php.
 *function _bSamInit($Verbose,$Debug)
 *{
 *	// create bsam license cache
 *	if($Verbose)
 *	{
 *		$res = NULL;
 *		print "  Initializing bSam License Cache\n";
 *		flush();
 *		$res = initBsamFiles($Debug);
 *	}
 *	else
 *	{
 *		$res = initBsamFiles($Debug);
 *	}
 *	return($res);
 *} // _bSamInit()
 *.

/**
 * _initPA
 * \brief initialize plugins and agents
 *
 * @param string $Verbose
 * @return php default
 */
function _initPA($Verbose,$Debug)
{
	$res = NULL;

	if($Verbose)
	{
		print "  Initializing plugins and agents\n";
		flush();
		$res = initPlugins($Verbose,$Debug);
		$res = initAgents($Verbose,$Debug);
	}
	else
	{
		$res = initPlugins($Verbose,$Debug);
		$res = initAgents($Verbose,$Debug);
	}
	return($res);
}

/**
 * _aSchema
 * \brief initialize the database schema
 *
 * @param string $Verbose
 * @return php default
 */
function _aSchema($Verbose)
{

	global $LIBEXECDIR;
	global $WEBDIR;

	$datFile = "$LIBEXECDIR/core-schema.dat";
	if (!file_exists($datFile))
	{
		print "FAILED: Schema data file ($datFile) not found.\n";
		exit(1);
	}

	// run schema-update
	$result = NULL;

	if($Verbose)
	{
		print "  Initializing Data Base Schema\n";
		flush();
		system("$LIBEXECDIR/schema-update -f $datFile",$result);
	}
	else
	{
		system("$LIBEXECDIR/schema-update -f $datFile",$result);
	}

	/* Make sure every upload has left and right indexes set. */
	// run adj2nest
	if($Verbose)
	{
		print "  Initializing new tables and columns\n";
		flush();
		system("$LIBEXECDIR/agents/adj2nest -a", $result);
	}
	else
	{
		system("$LIBEXECDIR/agents/adj2nest -a",$result);
	}

	return($result);
} // _aSchema()


$usage = "Usage: " . basename($argv[0]) . " [options]
  -a  = perform all actions (b,d,i)
/*  -b  = crate bsam license cache FIXME: not yet here */
  -d  = create the database schema
  -D  = enable debug 
  -h  = this help usage;
  -i  = initialize plugins and agents
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
if (array_key_exists('d',$Options))
{
	// apply schema
	// run adj2nest
	$FailFlag = _aSchema($Verbose);
}

/* FIXME: this is still in fossinit-agent.php
 *if (array_key_exists('b',$Options))
 *{
 *	// run bsam licCache
 *	$FailFlag = _bSamInit($Verbose,$Debug);
 *}
 */

if (array_key_exists('i',$Options))
{
	// init plugins and agents
	$FailFlag = _initPA($Verbose,$Debug);
}

if (array_key_exists('D',$Options))
{
	$Debug = TRUE;
}

/* Remove the "Need to initialize" flag */
if (!$FailFlag)
{
	$Filename = "$WEBDIR/init.ui";
	$State = 1;
	if (file_exists($Filename))
	{
		if ($Verbose) { print "Removing flag '$Filename'\n"; }
		if (is_writable($WEBDIR)) { $State = unlink($Filename); }
		else { $State = 0; }
	}
	if (!$State)
	{
		print "Failed to remove $Filename\n";
		print "Remove this file to complete the initialization.\n";
	}
	else
	{
		print "Initialization completed successfully.\n";
	}
}
else
{
	print "Initialization had errors.\n";
}

exit(0);
?>
