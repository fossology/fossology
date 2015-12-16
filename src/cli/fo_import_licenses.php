<?php
/***********************************************************
 Copyright (C) 2015 Siemens AG

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

use Fossology\Lib\Dao\UserDao;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();
require_once("$MODDIR/lib/php/common-users.php");

error_reporting(E_ALL);

$usage = "Usage: " . basename($argv[0]) . " [options]
  --username  = admin/user with license-admin permissions
  --password  = password
  --delimiter = delimiter, default is ','
  --enclosure = enclosure, default is '\"'
  --csv       = csv file to import
";
$opts = getopt("h", array('username:', 'password:', 'delimiter:', 'enclosure:', "csv:"));

if(array_key_exists('h',$opts))
{
  print "$usage\n";
  return 0;
}

if(!array_key_exists('csv',$opts))
{
  print "no input file given\n";
  print "$usage\n";
  return 0;
}
else
{
  $filename = $opts['csv'];
}

$username = array_key_exists("username", $opts) ? $opts["username"] : null;
$passwd = array_key_exists("password", $opts) ? $opts["password"] : null;

$delimiter = array_key_exists("delimiter", $opts) ? $opts["delimiter"] : ',';
$enclosure = array_key_exists("enclosure", $opts) ? $opts["enclosure"] : '"';

if(!account_check($username, $passwd, $group))
{
  print "Fossology login failure\n";
  return 2;
}
else
{
  print "Logged in as user $username\n";
}

/** @var UserDao */
$userDao = $GLOBALS['container']->get("dao.user");
$adminRow = $userDao->getUserByName($username);
if ($adminRow["user_perm"] < PLUGIN_DB_ADMIN)
{
  print "You have no permission to admin the licenses\n";
  return 1;
}

print "importing\n";
/** @var LicenseCsvImport */
$licenseCsvImport = $GLOBALS['container']->get('app.license_csv_import');
$licenseCsvImport->setDelimiter($delimiter);
$licenseCsvImport->setEnclosure($enclosure);
$import = $licenseCsvImport->handleFile($filename);

if ($import !== null) {
  print $import;
  print "\n";
}

print "done\n";
