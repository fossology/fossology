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
 * \brief library of functions to help with data base creation and removal
 *
 * @version "$Id$"
 *
 * Created on Sep 15, 2011 by Mark Donohoe
 */

// get globals....
if(file_exists('/usr/share/fossology/php/pathinclude.php'))
{
  echo "DB: found pathinclude at pkg location\n";
  require_once('/usr/share/fossology/php/pathinclude.php');
}
else if(file_exists('/usr/local/share/fossology/php/pathinclude.php'))
{
  echo "DB: found pathinclude at upstream location\n";
  require_once('/usr/local/share/fossology/php/pathinclude.php');
}
else
{
  echo "DB: ERROR! could not find pathinclude!\n";
  exit(1);
}

global $WEBDIR;
global $SYSCONFDIR;
global $LIBEXECDIR;

require_once("$LIBEXECDIR/libschema.php");
require_once("$WEBDIR/common/common-db.php");
require_once("$WEBDIR/common/common-cache.php");


/**
 * \brief Create an empty database with the supplied name.  Create user fossy
 * with password fossy.  If no datbase name is supplied the default name of
 * fosstest will be used.
 *
 * @param string -n $name optional name of data base to create
 *
 * @return null on success error on failure.
 *
 * Created on Sep 14, 2011 by Mark Donohoe
 */

function CreateTestDB($name=NULL)
{
  if(empty($name))
  {
    $name = fosstest;
  }
  // figure out TESTROOT and export it to the environment
  $dirList = explode('/', __DIR__);
  // remove 1st entry which is empty
  unset($dirList[0]);
  $TESTROOT = NULL;
  foreach($dirList as $dir)
  {
    if($dir != 'testing')
    {
      $TESTROOT .= '/' .  $dir;
    }
    else if($dir == 'testing')
    {
      $TESTROOT .= '/' . $dir;
      break;
    }
  }
  $_ENV['TESTROOT'] = $TESTROOT;

  if(chdir($TESTROOT . '/db') === FALSE)
  {
    return("FATAL! could no cd to $TESTROOT/db\n");
  }
  $cmd = "sudo ./ftdbcreate.sh $name 2>&1";
  $last = exec($cmd, $cmdOut, $cmdRtn);
  if($cmdRtn != 0)
  {
    return("Error could not create Data Base $name\n");
  }
  return(NULL);
}

/**
 * \brief change the default repo location to one to use for testing.  If no path is supplied,
 * then the default path of /srv/fossology/testRepo will be used.  This will change the path in
 * the installed fossology.conf file.
 *
 * @param string $repoPath, the optional fully qualified path to the test repo.
 *
 * @return boolean
 */

function SetRepo($repoPath=NULL)
{
  global $SYSCONFDIR;

  if(empty($repoPath))
  {
    $repoPath = '/srv/fossology/testRepo';
  }
  if(file_exists("$SYSCONFDIR/fossology/fossology.conf"))
  {
    $pa = "$SYSCONFDIR/fossology/fossology.conf";
    $fossConf = file_get_contents("$SYSCONFDIR/fossology/fossology.conf");
    if($fossConf === FALSE)
    {
      echo "ERROR! could not read\n$SYSCONFDIR/fossology/fossology.conf\n";
      return(FALSE);
    }
    $pat = '!/srv/fossology/repository!';
    $replace = '/srv/fossology/testrepo';
    $testConf = preg_replace($pat, $replace, $fossConf);
    //echo "testConf is:$testConf\n";

    $stat = file_put_contents("$SYSCONFDIR/fossology/fossology.conf",$testConf);
    if($stat === FALSE)
    {
      echo "ERROR! could not write\n$SYSCONFDIR/fossology/fossology.conf\n";
      return(FALSE);
    }
  }
  else
  {
    echo "ERROR! can't find fossology.conf at:\n$SYSCONFDIR/fossology.conf\n";
    return(FALSE);
  }
  return(TRUE);
}

/**
 * \brief Load the schema into the db
 *
 * @param string $path Optional fully qualified path to the schema file to use
 * if no path is supplied, the standard schema will be used.
 *
 * @return boolean
 *
 * Created on Sep 15, 2011 by Mark Donohoe
 */



function TestDBInit($path=NULL)
{
  if(empty($path))
  {
    $path = __DIR__ . '/../../www/ui/core-schema.dat';
  }
  if (!file_exists($path))
  {
    print "FAILED: Schema data file ($path) not found.\n";
    return(FALSE);
  }

  // run schema-update
  $result = NULL;

  $schemaUp = __DIR__ . '/../../cli/schema-update.php';

  system("$schemaUp -f $path",$result);
  //echo "DB: result of schema update is:$result\n";

  if($result == 0)
  {
    return(TRUE);
  }
  else
  {
    return(FALSE);
  }
}
?>