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

function _ModFossConf($sysConfPath, $repoPath)
{

  if(file_exists($sysConfPath . '/fossology.conf'))
  {
    $fossConf = file_get_contents($sysConfPath . '/fossology.conf');
    if($fossConf === FALSE)
    {
      echo "ERROR! could not read\n$sysConfPath/fossology.conf\n";
      return(FALSE);
    }
    $pat = '!/srv/fossology/repository!';
    $testConf = preg_replace($pat, $repoPath, $fossConf);
    //echo "DB: testConf is:$testConf\n";

    $stat = file_put_contents("$sysConfPath/fossology.conf",$testConf);
    if($stat === FALSE)
    {
      echo "ERROR! could not write\n$sysConfPath/fossology.conf\n";
      return(FALSE);
    }
  }
  else
  {
    echo "ERROR! can't find fossology.conf at:\n$sysConfPath/fossology.conf\n";
    return(FALSE);
  }
  return(TRUE);
}

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

function CreateTestDB($name)
{
  if(empty($name))
  {
    return("Error, no DB name supplied\n");
  }
  // figure out TESTROOT and export it to the environment so the shell scripts
  // can use it.  We live in testing/lib, so just remove /lib.

  $path = __DIR__;
  $plenth = strlen($path);
  $TESTROOT = substr($path, 0, $plenth-4);
  //echo "DB TR is:$TESTROOT\n";
  $_ENV['TESTROOT'] = $TESTROOT;
  putenv("TESTROOT=$TESTROOT");

  if(chdir($TESTROOT . '/db') === FALSE)
  {
    return("FATAL! could no cd to $TESTROOT/db\n");
  }
  $cmd = "sudo ./ftdbcreate.sh $name 2>&1";
  $last = exec($cmd, $cmdOut, $cmdRtn);
  echo "results of dbcreate are:\n"; print_r($cmdOut) . "\n";
  if($cmdRtn != 0)
  {
    $err = "Error could not create Data Base $name\n";
    //    echo "DB: returning Error, ftdbcreate.sh did not succeed\n";
    return($err);
  }
  return(NULL);
} // CreateTestDB

/**
 * \brief restore either Db.conf or fossology.conf files by copying orig.<file>
 *
 * @param string $filename the file to restore, e.g. Db.conf or fossology.conf
 *
 * @return boolean
 *
 * @todo complete this routine and remove other restore functions
 */
function RestoreFile($filename)
{
  global $SYSCONFDIR;


  if(empty($filename))
  {
    return(FALSE);
  }
  // cp is used instead of copy so the caller doesn't have to run as sudo
  $lastCp = system("sudo cp $SYSCONFDIR/orig.$filename " .
      "$SYSCONFDIR/$filename", $rtn);
  if($lastCp === FALSE)
  {
    return(FALSE);
  }
  // clean up the orig file
  $lasRm = exec("sudo rm $SYSCONFDIR/orig.$filename", $rmOut, $rmRtn);
  if($rmRtn != 0)
  {
    echo "Trouble removing $SYSCONFDIR/orig.$filename, please " .
      "investigate and remove by hand\n";
    return(FALSE);
  }
  return(TRUE);
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

function SetRepo($sysConfPath,$repoPath)
{

  if(empty($repoPath))
  {
    return(FALSE);
  }
  if(empty($sysConfPath))
  {
    return(FALSE);
  }
  return(_modFossConf($sysConfPath,$repoPath));
}

/**
 * \brief Load the schema into the db
 *
 * @param string $path Optional fully qualified path to the schema file to use
 * if no path is supplied, the standard schema will be used.
 *
 * @return Null on success, string on error.
 *
 * Created on Sep 15, 2011 by Mark Donohoe
 */

function TestDBInit($path=NULL, $dbName)
{
  if(empty($path))
  {
    $path = __DIR__ . '/../../www/ui/core-schema.dat';
  }
  if (!file_exists($path))
  {
    return("FAILED: Schema data file ($path) not found.\n");
  }
  if(empty($dbName))
  {
    return("Error!, no catalog supplied\n");
  }
  
  // run ApplySchema via fossinit
  $result = NULL;
  $lastUp = NULL;
  $sysc = getenv('SYSCONFDIR');
  $fossInit = __DIR__ . '/../../../install/fossinit.php';
  $upOut = array();
  $last = exec("$fossInit -d $dbName -f $path", $upOut, $upRtn);
  //echo "DB: schema up output is:\n" . implode("\n",$upOut) . "\n";

  if($upRtn != 0)
  {
    return(implode("\n", $upOut) . "\n");
  }
  else
  {
    return(NULL);
  }
}
?>