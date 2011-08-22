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
 * \brief common functions that a number of tests need.
 *
 * @version "$Id$"
 */

/**
 *  allFilePaths
 *
 *  given a directory, iterate through it and all subdirectories returning
 *  the absolute path to the files.
 *
 * created: May 22, 2009
 */

//ldir = '/home/markd/Eddy';
//$ldir = '/home/fosstester/regression/license/eddy/GPL';
/**
 * allFilePaths
 *
 * given a directory, iterate through it and all subdirectories returning
 * the absolute path to the files.
 *
 * @param string $dir the directory to start from either an absolute path or
 * a relative one.
 *
 * @return array $fileList a list of the absolute path to the files or empty
 * array on error.
 */
function allFilePaths($dir) {

  $fileList = array();
  if(empty($dir)) {
    return($fileList);  // nothing to process, return empty list.
  }
  try {
    foreach(new recursiveIteratorIterator(
    new recursiveDirectoryIterator($dir)) as $file) {
      $fileList[] = $file->getPathName($file);
    }
    return($fileList);
  }
  /*
   * if the directory does not exist or the directory or a sub directory
   * does not have sufficent permissions for reading return an empty list
   */
  catch(Exception $e) {
    print $e->getMessage();
    return(array());
  }
} //allFilePaths

/**
 * \brief chdir to the supplied path or exit with a FATAL message
 *
 * @param string $howFar the string to chdir to.
 *
 * @example backToParent('..'); backToParent('../../..');
 * backToParent('/home/somewhereElse/to/go');
 */
function backToParent($howFar)
{
  if(empty($howFar))
  {
    echo "FATAL! No input at line " . __LINE__ . " in " . __FILE__ . "\n";
    exit(1);
  }

  $here = getcwd();

  if(@chdir($howFar) == FALSE)
  {
    echo "FATAL! could not cd from:\n$here to:\n$howFar\n" .
      "at line " . __LINE__ . " in " . __FILE__ . "\n";
    exit(1);

  }
} // backToParent

/**
 * \brief Check if the test data files exist, if not downloads and installs them.
 *
 * Large test data files are kept outside of source control.  The data needs to
 * be installed in the sources before tests can be run.  The data is kept on
 * fossology.org in /var/www/fossology.og/testing/testFiles/
 *
 * @version "$Id$"
 *
 * Created on Jun 8, 2011 by Mark Donohoe
 */

function checkTestData()
{
  $WORKSPACE = NULL;

  if(array_key_exists('WORKSPACE', $_ENV))
  {
    $WORKSPACE = $_ENV['WORKSPACE'];
  }
  if(is_null($WORKSPACE))
  {
    // cd to ....fossology/src
    backToParent('..');
  }
  else
  {
    if(@chdir($WORKSPACE . "/fossology/src") === FALSE)
    {
      echo "FATAL! runRUnit could not cd to " . $WORKSPACE . "/fossology/src\n";
      exit(1);
    }
  }
  $home = getcwd();
  $dirs = explode('/',$home);
  $size = count($dirs);
  // are we being run by jenkins? if we are not in fossology/tests, cd there
  if($dirs[$size-1] == 'workspace' )
  {
    if(chdir('fossology/tests') === FALSE)
    {
      echo "FATAL! Cannot cd to fossology/tests from" . getcwd() . "\n";
      exit(1);
    }
    $home = getcwd();  // home should now be ...workspace/fossology/tests
  }

  $redHatPath = 'nomos/testdata';
  $unpackTestFile = '../ununpack/agent_tests/test-data/testdata4unpack/argmatch.c.gz';
  $unpackTests = '../ununpack/agent_tests';
  $redHatDataFile = 'RedHat.tar.gz';
  $unpackDataFile = 'unpack-test-data.tar.bz2';
  $wgetOptions = ' -a wget.log --tries=3 ';
  $proxy = 'export http_proxy=lart.usa.hp.com:3128;';
  $Url = 'http://fossology.org/testing/testFiles/';

  $errors = 0;
  // check/install RedHat.tar.gz

  /*
   if(!file_exists($redHatPath . "/" . $redHatDataFile))
   {
   if(chdir($redHatPath) === FALSE)
   {
   echo "ERROR! could not cd to $redHatPath, cannot download $redHatDataFile\n";
   $errors++;
   }
   $cmd = $proxy . "wget" . $wgetOptions . $Url . $redHatDataFile;
   $last = exec($cmd, $wgetOut, $wgetRtn);
   if($wgetRtn != 0)
   {
   echo "ERROR! Download of $Url$redHatDataFile failed\n";
   echo "Errors were:\n$last\n";print_r($wgetOut) . "\n";
   $errors++;
   }
   }
   else

   if(chdir($home) === FALSE)
   {
   echo "FATAL! could not cd to $home\n";
   exit(1);
   }
   */

  // check/install ununpack data
  echo "downloading unpack data.....\n";
  if(!file_exists($unpackTestFile))
  {
    echo "$unpackTestFile DOES NOT EXIST!, need to download data files...\n";
    if(chdir($unpackTests) === FALSE)
    {
      echo "FATAL! cannot cd to $unpackTests\n";
      exit(1);
    }
    $cmd = $proxy . "wget" . $wgetOptions . $Url . '/' . $unpackDataFile;
    $unpkLast = exec($cmd, $unpkOut, $unpkRtn);
    if($unpkRtn != 0)
    {
      echo "ERROR! Download of $Url$unpackDataFile failed\n";
      echo "Errors were:\n";print_r($unpkOut) . "\n";
      $errors++;
    }
    // unpack the tar file.
    $cmd = "tar -xf $unpackDataFile";
    $tarLast = exec($cmd, $tarOut, $tarRtn);
    if($tarRtn != 0)
    {
      echo "ERROR! un tar of $unpackDataFile failed\n";
      echo "Errors were:\n$tarLast\n";print_r($tarOut) . "\n";
      $errors++;
    }
  }

  if($errors)
  {
    exit(1);
  }
  exit(0);
} // checkTestData

/**
 * escapeDots($string)
 *
 * Escape '.' in a string by replacing '.' with '\.'
 * @param string $string the input string to escape.
 * @return string $estring the escaped string or False.
 */
function escapeDots($string)
{
  if (empty ($string))
  {
    return (FALSE);
  }
  $estring = preg_replace('/\./', '\\.', $string);
  //print  "ED: string is:$string, estring is:$estring\n";
  if ($estring === NULL)
  {
    return (FALSE);
  }
  return ($estring);
} //escapeDots

function lastDir($dirpath) {
  // can't have a tailing slash, remove it if there
  $dirpath = rtrim($dirpath, '/');
  $directories = explode('/',$dirpath);
  return(end($directories));
}

/**
 * \brief given a directory name, return a array of subdir paths and an array of
 * the files under the last subdir.
 *
 * @param string $dir
 * @return array ByDir, an array of arrays.
 *
 * array[dirpath]=>(array)list of files under leaf dir
 *
 * \todo test this routine with files other than the leaf dirs, does it work?
 *
 */

function filesByDir($dir) {

  $ByDir = array();
  $fileList = array();
  $subPath = '';

  if(empty($dir)) {
    return($fileList);  // nothing to process, return empty list.
  }

  try {
    $dirObject = new recursiveIteratorIterator(
    new recursiveDirectoryIterator($dir),RecursiveIteratorIterator::SELF_FIRST);
    // dirobjs is recusiveIteratorIterator object
    foreach($dirObject as $name) {

      $aSubPath = $dirObject->getSubPath();

      /*
       * if we changed subpaths, we are in a new sub-dir, reset the file list
       */
      if($aSubPath != $subPath) {
        //print "DB: fileByDir: asb != sb, Init fileList!\n";
        $fileList = array();
      }

      if(is_file($name)) {
        $subPath = $dirObject->getSubPath();
        $spn = $dirObject->getSubPathName();
        $subDir = dirname($spn);
        if($subDir == $aSubPath) {
          $fileName = $dirObject->getFilename();
          $fileList[] = $fileName;
        }
      }
      if (empty($subPath)){
        continue;
      }
      else {
        if(empty($fileList)){
          continue;
        }
        $ByDir[$subPath] = $fileList;
      }

      /* Debug
       *
       $subPath = $dirObject->getSubPath();
       print "DB: fileByDir: subpath is:$subPath\n";
       $sbn = $dirObject->getSubPathName();
       print "DB: fileByDir: subpathname is:$sbn\n";
       $dirpath = $dirObject->getPath();
       print "DB: fileByDir: dirpath is:$dirpath\n";

       */

    } // foreach
    //print "DB: fileByDir: ByDir is:\n ";print_r($ByDir) . "\n";
    return($ByDir);
  }

  /*
   if the directory does not exist or the directory or a sub directory
   does not have sufficent permissions for reading return an empty list
   */
  catch(Exception $e) {
    //print "in exception!\n$e\n";
    return(array());
  }
} // fileByDir
?>
