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
 * schema-update
 * \brief apply the schema to the fossology db using the supplied data file
 *
 * @param string $filePath
 * @return 0 for success, 1 for failure
 *
 * @version "$Id: schema-update.php 2791 2010-02-10 21:27:48Z rrando $"
 */

/*
 Note: can't use the UI plugins, they may not be initialized.  On install, the are
 initialized AFTER this script is run.  Well we could init them, cheaper to just
 open the db.
 */
global $GlobalReady;
$GlobalReady = 1;

require_once (dirname(__FILE__)) . '/../../share/fossology/php/pathinclude.php';
//require_once '/usr/local/share/fossology/php/pathinclude.php';
global $LIBEXECDIR;
require_once("$LIBEXECDIR/libschema.php");
//require_once "./libschema.php";

global $PGCONN;

$usage = "Usage: " . basename($argv[0]) . " [options]
  -f <filepath> pathname to schema data file
  -h this help usage";

$Options = getopt('f:h');
if (empty($Options))
{
	print "$usage\n";
	exit(1);
}

if (array_key_exists('h',$Options))
{
	print "$usage\n";
	exit(0);
}

if (array_key_exists('f', $Options))
{
	$Filename = $Options['f'];
}
if((strlen($Filename)) == 0)
{
	print "Error, no filename supplied\n$usage\n";
	exit(1);
}

// get db params and open connection to db.

// Debug bebug stuff below
//$Filename = "./testcore-schema-noserver.dat";
//echo "connecting to db randodb\n";
//$dbOptions = 'dbname=randodb user=rando password=rando';
//$PGCONN = dbConnect($dbOptions);
$PGCONN = dbConnect(NULL);

ApplySchema($Filename, 0);

?>
