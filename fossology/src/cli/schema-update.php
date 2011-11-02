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
 * Note: this program is meant to be run from the source at this point.  It
 * is not installed as part of fossology.  It is an internal team tool used
 * to update the offical fossology schema.
 *
 * @param string $filePath
 * @return 0 for success, 1 for failure
 *
 * @version "$Id: schema-update.php 2791 2010-02-10 21:27:48Z rrando $"
 */

require_once(__DIR__ . '/../lib/php/bootstrap.php');

global $GlobalReady;
$GlobalReady = 1;

$usage = "Usage: " . basename($argv[0]) . " -c path-to-fossology-config [options]
  -C <catalog>  the optional database catalog to use, e.g. fossology
  -c the path-to-fossology-config, e.g. /etc/fossology
  -f <filepath> pathname to schema data file
  -h this help usage";

$sysConfig = NULL;

$Options = getopt('C:c:f:h');
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

if (array_key_exists('C', $Options))
{
  $Catalog = $Options['C'];
}
if(empty($Catalog))
{
  $Catalog = 'fossology';
}
if (array_key_exists('c', $Options))
{
  $sysConfig = $Options['c'];
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
// No sysconfig path passed in? try the environment
if(empty($sysConfig))
{
  $sysConfig = getenv('SYSCONFDIR');
  if(empty($sysConfig))
  {
    echo "FATAL!, no SYSCONFDIR defined\n";
    echo "either export SYSCONFDIR path and rerun or use -c <sysconfdirpath>\n";
    flush();
    exit(1);
  }
}
// get global vars:
putenv("SYSCONFDIR=$sysConfig");
$configVars = array();
$configVars = bootstrap();

global $PG_CONN;

require_once("$LIBEXECDIR/libschema.php");
require_once ("$MODDIR/lib/php/common-db.php");
require_once ("$MODDIR/lib/php/common-cache.php");

$PG_CONN = DBconnect($sysConfig);

//ApplySchema($Filename, 1, 1, $Catalog);
// no debug below
$worked = ApplySchema($Filename, 0, 1, $Catalog);
if($worked != 0)
{
  exit(1);
}
else
{
  exit(0);
}
?>
