<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief common functions that a number of tests need.
 *
 * @version "$Id$"
 */
/**
 * \file addProxy
 * \brief Add a proxy to fossology.conf
 *
 * @parma string $sysConfPath the path the fossology configuration directory
 * @param string $proxy the proxy to set, or use default
 *
 * @return boolean
 */

function addProxy($sysConfPath, $proxy='lart.usa.hp.com:3128')
{
  if(file_exists($sysConfPath . '/fossology.conf'))
  {
    $fossConf = file_get_contents($sysConfPath . '/fossology.conf');
    if($fossConf === FALSE)
    {
      echo "ERROR! could not read\n$sysConfPath/fossology.conf\n";
      return(FALSE);
    }
    $count = 0;
    $pat = ';no_proxy = localhost,10.1.2.3';
    $replacepat = $pat . "\n" . 'http_proxy = ' . $proxy . "\n";
    //echo "replacepat is:$replacepat\n";
    $testConf = preg_replace("/.*?no_proxy = .*/", $replacepat, $fossConf,-1,$count);
    //echo "DB: matched pats is:$count\n";
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
 * \file allFilePaths
 *
 * \brief given a directory, iterate through it and all subdirectories returning
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
 * DEPRICATED: now done in makefiles with rsync.
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
 * \brief remove xml files from the test run, so jenkins doesn't try to process them
 *
 * @return void
 */
function cleanXMLFiles()
{
  global $unitList;
  reset($unitList);
  foreach($unitList as $unitTest)
  {
    $other = substr($unitTest, 0, 3);
    if($other == 'lib' || $other == 'cli')
    {
      if(@chdir($unitTest . '/tests') === FALSE)
      {
        echo "Error! cannot cd to " . $unitTest . "/tests, skipping test\n";
        $failures++;
        continue;
      }
    }
    else
    {
      if(@chdir($unitTest . '/agent_tests/Unit') === FALSE)
      {
        echo "Error! cannot cd to " . $unitTest . "/agent_tests/Unit, skipping test\n";
        $failures++;
        continue;
      }
    }
    foreach(glob("$unitTest*.xml") as $fName)
    {
      $lsOut = array();
      $rmOut = array();
      $fileName = lcfirst($fName);
      $last = exec("rm $fileName", $rmOut, $rmRtn);
      if($rmRtn != 0)
      {
        echo "Notice: could not remove $filename, please remove by hand\n";
      }
    } // foreach
    backToParent('../../..');
  } // foreach
  return;
}

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

/**
 * \brief class for making an agent unit or functional test
 *
 * @author markd
 *
 */
class RunTest
{
  public $makeOutput = array();
  protected $unitTest;
  protected $makeErrors;
  protected $cunitErrors;
  protected $phpunitErrors;

  function __construct($unitTest)
  {
    $this->unitTest = $unitTest;
    $this->makeErrors = FALSE;
    $this->cunitErrors = FALSE;
    $this->phpunitErrors = FALSE;
  }

  /**
   * \brief make tests in a directory and check output for errors and no
   * tests.
   *
   * This function assumes that the caller is cd'ed into the appropriate
   * directory before being called.
   *
   * @return array
   * The array has the folowing format:
   * 'name'=> $unitTest
   * 'make' => boolean, true for make errors false for none
   * 'cunit' => boolean, true for cunit failures false for none
   * 'phpunit' => boolean, true for phpunit failures false for none
   * 'notest' => boolean, false for no tests for that module, true for tests
   * 'nomosfunc' => None-0 for nomos functional failures
   */
  function MakeTest()
  {

    $results = array(
      'name'=> $this->unitTest,
      'make' => FALSE,
      'cunit' => FALSE,
      'phpunit' => FALSE,
      'notest' => FALSE,
      'other' => NULL,
      'nomosfunc' => NULL,
    );

    $cleanMake = exec('make clean 2>&1', $cleanOut, $cleanRtn);
    if($cleanRtn != 0)
    {
      echo "Make clean of $this->unitTest did not succeed, return code:$cleanRtn\n";
      // right now this is not reported as an error
      // @todo figure out how to handle this.  Make clean failures should not
      // cause make to not be done.
    }
    //echo "ComTest: sysconfdir from globals is:{$GLOBALS['SYSCONFDIR']}\n";
    $s = getenv('SYSCONFDIR');
    //echo "DB: sysconfdir from env before make of $this->unitTest is:$s\n";
    $lastMake = exec('make test 2>&1', $this->makeOutput, $makeRtn);
    //echo "DB: Exit status of 'make test' of $this->unitTest is:$makeRtn\n";
    //debugprint($this->makeOutput, "make output\n");

    if($makeRtn != 0)
    {
      $found = array();
      $found = preg_grep('/No rule to make target/', $this->makeOutput);
      if($found)
      {
        $results['notest'] = TRUE;
        //echo "No Unit Tests for module $unitTest\n";
      }
      else
      {
        // check for real make errors and test errors.
        if($this->CheckMakeErrors(implode("\n", $this->makeOutput)))
        {
          //echo "Error! There were make errors for unit test $unitTest\n";
          $results['make'] = TRUE;
        }
        if($this->checkCunitTestErrors(implode("\n", $this->makeOutput)))
        {
          $results['cunit'] = TRUE;
          /*
           $pattern = $this->unitTest . "_Tests summary:";
           $found = array_search($pattern, $this->makeOutput);
           if($found !== FALSE)
           {
           echo implode("\n", array_slice($this->makeOutput, -7)) . "\n";
           }
           */
        }
        if($this->checkPHPTestErrors(implode("\n", $this->makeOutput)))
        {
          $results['phpunit'] = TRUE;
        }
        $other = $this->CheckOtherErrors(implode("\n", $this->makeOutput));
        if($other)
        {
          $results['other'] = $other;
        }
      }
      return($results);
    }
    // Make returned zero
    else
    {
      // no tests for is module?  Skip report processing
      $nothing = array();
      $nothing= preg_grep("/Nothing to be done for \`test\'/", $this->makeOutput);
      //echo "grep for Nothing to... is:\n";print_r($nothing) . "\n";
      $noTests = array();
      $noTests= preg_grep('/NO.*TESTS/', $this->makeOutput);
      //echo "grep for NoTests to... is:\n";print_r($noTests) . "\n";
      if($nothing or $noTests)
      {
        //echo "No Unit Tests for module $unitTest\n";
        $results['notest'] = TRUE;
      }
      // There can be Cunit failures, check for them
      if($this->checkCunitTestErrors(implode("\n", $this->makeOutput)))
      {
        $results['cunit'] = TRUE;
        /*
         $pattern = $this->unitTest . "_Tests summary:";
         $found = array_search($pattern, $this->makeOutput);
         if($found !== FALSE)
         {
         echo implode("\n", array_slice($this->makeOutput, -7)) . "\n";
         }
         */
      }
      return($results);
    }
  }// MakeTest

  /*
   * @todo see if checkMake and check Test Error functions can be combined into
   * a single routine, checkPattern($string, $pat), returns boolean.
   */
  /**
   * \brief check the output of make for errors
   *
   * @return boolean
   */
  function CheckMakeErrors($makeString)
  {
    $matched = 0;
    $matches = array();

    $pat = '/make.*?Error\s[0-9]+/';
    $matched = preg_match($pat, $makeString, $matches);

    //echo "DB: matched is:$matched\n";
    //echo "DB: this->makeOutput is:$this->makeOutput\n";
    return($matched);
  } // checkMakeErrors

  /**
   * \brief check the test output for cunit style failures
   *
   * @param string $makeString $this->makeOutput the test output
   *
   * @return boolean
   */
  function CheckCunitTestErrors($makeString)
  {
    $matched = 0;
    $matches = array();

    $pat = '/Number of failures:.*/';
    $matched = preg_match($pat, $makeString, $matches);
    if($matched == 0)
    {
      return(FALSE);
    }
    $number = explode(':', $matches[0]);
    $value = trim($number[1]);
    if($value > 0)
    {
      return(TRUE);
    }
    return(FALSE);
  } // CheckCunitTestErrors

  /**
   * \brief check for the word FAILURES in the output, this is what PHPUnit
   * prints when there are any failures in the tests run.
   *
   * @param string $makeString, the make output
   * @return boolean
   */
  function CheckPHPTestErrors($makeString)
  {
    $matched = 0;
    $matches = array();

    $pat = '/FAILURES/';
    $matched = preg_match($pat, $makeString, $matches);
    return($matched);

  } // CheckPHPTestErrors

  /**
   * \brief Check for other common error strings
   *
   * This is an attempt to give the developer more information about what failed
   * rather than the generic message 'some other error occured'  Only the first
   * pattern found will be reported.
   *
   * @param string $makeString
   */
  function CheckOtherErrors($makeString)
  {
    $matched = 0;
    $matches = array();

    $patterns = array('/.*\serror\s.*/', '/.*\sfault.*/');
    foreach ($patterns as $pattern)
    {
      $matched = 0;
      $matches = array();
      $matched = preg_match($pattern, $makeString, $matches);
      if($matched)
      {
        //debugprint($matches, "Found one: matches is:\n");
        return($matches[0]);
      }
    }
    return(implode("\n",$matches));
  }

  /**
   * \brief print the result array.  This will print any informative messages
   * including erorrs that may have occured.
   *
   * @param array $runResults
   * @return volid
   *
   * The array has the format has described in  MakeTest method.
   */
  function printResults($runResults)
  {
    global $failures;

    $failures = 0;
    $cunitMake = FALSE;
    $phpunitMake = FALSE;

    $test = $runResults['name'];
    unset($runResults['name']);

    if($runResults['make'] === TRUE && $runResults['cunit'] === TRUE)
    {
      $cunitMake = TRUE;
      //echo "DB: both make and cunit are true, both has been set\n";
    }
    if($runResults['make'] === TRUE && $runResults['phpunit'] === TRUE)
    {
      $phpunitMake = TRUE;
      //echo "DB: both make and phpunit are true, both has been set\n";
    }

    foreach($runResults as $key => $value)
    {
      switch($key)
      {
        case 'make':
          if($value === TRUE)
          {
            if($cunitMake || $phpunitMake)
            {
              break;
            }
            echo "Error: there were $key errors for $test\n";
            echo implode("\n", array_slice($this->makeOutput, -7)) . "\n";
            $failures++;
            break;
          }
        case 'cunit':
          if($value === TRUE)
          {
            echo "There were $key errors for $test\n";
            $pattern = $this->unitTest . "_Tests summary:";
            $found = array_search($pattern, $this->makeOutput);
            if($found !== FALSE)
            {
              echo implode("\n", array_slice($this->makeOutput, -7)) . "\n";
            }
            $failures++;
            break;
          }
        case 'phpunit':
          if($value === TRUE)
          {
            echo "Error: there were $key errors for $test\n";
            echo implode("\n", array_slice($this->makeOutput, -7)) . "\n";
            $failures++;
            break;
          }
        case 'notest':
          if($value === TRUE)
          {
            echo "No tests for $test\n";
            $failures++;
            break;
          }
        case 'other':
          if(empty($value))
          {
            break;
          }
          echo "Other errors for $test:\n";
          echo $value . "\n";
          $failures++;
          break;
        case 'nomosfunc':
          if(empty($value))
          {
            break;
          }
          echo "Nomos diff errors for $test:\n";
          echo $value . " diffs.\n";
          $failures++;
          break;
      }
    } //foreach $runResults
    if($failures == 0)
    {
      echo "All tests passed for $test\n";
    }
    return ;
  }
} // class RunTest

function debugprint($val, $title)
{
  echo $title . "\n";
  print_r($val);
  echo "\n";
}

/**
 * \brief make coverage for a test
 *
 * @return null on success, string on failure.
 */
function MakeCover($unitTest)
{
  if(empty($unitTest))
  {
    return(NULL);
  }

  // make coverage
  $lastCovr = exec('make coverage 2>&1', $covrOut, $covrRtn);
  //echo "DB: Exit status of 'make coverage' of $unitTest is:$covrRtn\n";
  $Cover = new RunTest($unitTest);
  if($covrRtn != 0)
  {
    if($Cover->checkMakeErrors(implode("\n", $covrOut)))
    {
      echo "Error: 'make coverage' of $unitTest did not succeed, " .
          "return code:$covrRtn\n";
      $covrOut = array();
      return($unitTest);
    }
  }
  return(NULL);
}
?>
