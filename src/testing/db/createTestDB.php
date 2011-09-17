#!/usr/bin/php
<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * \brief create an empty database with the supplied name.  Create user fossy
 * with password fossy.
 *
 * If no datbase name is supplied the default name of fosstest will
 * be used.  If no repo path is supplied the path will be
 * /srv/fossology/testrepo.
 *
 * @param string -n $name optional name of data base to create
 * @param string -r $repoPath, optional path to the test repo.
 *
 * @return boolean
 *
 * @version "$Id$"
 * Created on Sep 14, 2011 by Mark Donohoe
 */

require_once(__DIR__ . '/../lib/libTestDB.php');

$usage = __FILE__ . ": [-h] [-d] [-n name] [-r repoPath]\n" .
  "-d: drop the named data base, if no name given data base fosstest will be dropped\n" .
  "name: optional name of data base, default name is fosstest.\n" .
  "repoPath: optional path to the test repository, default path is " .
  "/srv/fossology/testrepo\n";

$name = 'fosstest';           // default db name
$repoPath = NULL;

$Options = getopt('dhn:r:');
if(array_key_exists('h',$Options))
{
  print "$usage\n";
  exit(0);
}
if(array_key_exists('n',$Options))
{
  $name = $Options['n'];
  if(empty($name))
  {
    echo "Error! Valid data base name not supplied\n";
    exit(1);
  }
}
if(array_key_exists('r', $Options))
{
  $repoPath = $Options['r'];
  // check to see if path exists?
}
if(array_key_exists('d', $Options))
{
  echo "droping db....\n";
  $last = exec("dropdb $name -U fossy -W", $out, $rtn);
  echo "DB: results of dropdb are:\n";print_r($out) . "\n";
  if($rtn != 0)
  {
    echo "ERROR! could not drop database $name, drop by hand.\n";
    exit(1);
  }
  exit(0);
}

// create the db
echo "DB: creating db\n";
if($newDB = CreateTestDB($name) != NULL)
{
  echo "newdb is:$newDB\n";
  exit(1);
}
// load the schema
echo "DB: loading schema\n";
if(TestDBInit() === FALSE)
{
  echo "ERROR, could not load schema\n";
  exit(1);
}

// change repo location
echo "DB: changing repo\n";
if(empty($repoPath))
{
  if(!SetRepo())
  {
    echo "ERROR!, could not change fossology.conf, please change by hand before running tests\n";
    exit(1);
  }
}
else
{
  if(!SetRepo($repoPath))
  {
    echo "ERROR!, could not change fossology.conf, please change by hand before running tests\n";
    exit(1);
  }
}
exit(0);
?>