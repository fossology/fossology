#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief Run the unit tests in module/agent_tests/Unit for all modules in
 * the input data file.
 *
 * The input is a php ini style file with each name as a section [modname].
 *
 * Note: this program relys on a utility call createRC.php found in testing/utils.
 * That program is run before this to set up SYSCONFDIR.
 *
 * @todo add parameter processing.  Add in -k option to keep xml files.  This
 * could be useful for debugging.
 *
 * @version "$Id$"
 * Created on Aug 24, 2011 by Mark Donohoe
 */

global $failures;

/**
 * \brief checkCUnit report for a failure
 *
 * @return mixed Null on success or an array of SimpleXMLElements or an array
 * with either an error or an exception message.
 *
 * NOTE: I don't really like these return values... fix later.
 */
function checkCUnit($fileName)
{

  if(empty($fileName))
  {
    return(array("Error! illegal input $fileName"));
  }
  try {
    $verFail = check4CUnitFail($fileName);
    if(!is_null($verFail))
    {
      //echo "DB: failues in check4CUnitFail\n";
      //print_r($verFail) . "\n";
      return($verFail);

      //$failures++;
    }
  }
  catch (Exception $e)
  {
    return(array("Error! $e\n"));
  }
} // checkCUnit

/**
 * \brief transform the xml to html for CUnit style reports
 *
 * @param string $fileName the xml file to transform
 *
 * @return boolean
 */
function genCunitRep($fileName)
{
  if(empty($fileName))
  {
    return(FALSE);
  }
  // get List or Run string so the correct xsl file is used.

  $xslFile = "CUnit-Run.xsl";   // default
  if(preg_grep("/Results/", array($fileName)))
  {
    $xslFile = "CUnit-Run.xsl";
  }
  else if(preg_grep("/Listing/", array($fileName)))
  {
    $xslFile = "CUnit-List.xsl";
  }
  // remove .xml from name
  $outFile = basename($fileName, '.xml');
  $outPath = TESTROOT . "/reports/unit/$outFile.html";
  $xslPath = "/usr/share/CUnit/$xslFile";
  // remove the old HTML file before creating a new one.
  $rmLast = exec("rm -rf $outPath", $rmOut, $rmRtn);
  //echo "DB: Starting to generate html report for $fileName\n";
  $report = genHtml($fileName, $outPath, $xslPath);
  if(!empty($report))
  {
    echo "Error: Could not generate a HTML Test report from $fileName.\n";
    return(FALSE);
  }
  //echo "DB: Generated html file:$outFile" . ".html\n";
  return(TRUE);
}

/**
 * \brief meta function to process cunit reports
 *
 * @param string $unitTest the unit test to process
 *
 * @return mixed NULL on success, newline terminated string on failure
 */
function processCUnit($unitTest)
{
  global $failures;
  global $other;

  $unitTest = preg_replace('#/#', '-', $unitTest);
  $libphp = "lib-php";
  /** ignore lib/php/tests */
  if ($libphp === $unitTest) return NULL;

  if(empty($unitTest))
  {
    return("Error! no valid input at line " . __FILE__ . " at line " .
    __LINE__ . "\n");
  }

  foreach(glob("$unitTest*.xml") as $fName)
  {
    $fileName = lcfirst($fName);
    // Skip Listing files
    if(preg_grep("/Listing/", array($fileName)))
    {
      $rmlast = exec("rm $fileName", $rmOut, $rmRtn);
      continue;
    }
    if(!tweakCUnit($fileName))
    {
      return("Error! could not save processed xml file, they may not display properly\n");
    }

    $errors = array();
    // defect: if the report is corrupt, checkCUnit will say everything is OK.
    $errors = checkCUnit($fileName);
    //echo "DB: after checkCUnit, errors for $unitTest are\n";print_r($errors) .  "\n";
    if(is_object($errors[0]))
    {
      $failures++;
      echo "There were Unit Test Failures in $unitTest\n";
      //print_r($errors) . "\n";
    }
    else if(!is_NULL($errors))
    {
      // if we can't even check the file, then skip making the report
      $failures++;
      return("Failure: Could not check file $fileName for failures is the file corrupt?\n");
    }

    if(!genCunitRep($fileName))
    {
      return("Error!, could not generate html report for $unitTest\n");
    }
  } // foreach

  return(NULL);
} // processCUnit

/**
 * \breif change the references to the dtd's for cunit reports so they can be
 * processed.
 *
 * @param string $fileName, the path to the filename to process....
 *
 * @return boolean
 */
function tweakCUnit($fileName)
{
  if(empty($fileName))
  {
    return(FALSE);
  }

  //echo "DB: tweaking xml file:$fileName\n";
  $rFile = file_get_contents($fileName);
  // fix the Ref to xsl file
  $pat = '#href="#';
  $replace = 'href="http://fossology.usa.hp.com/~fossology/dtds/';
  $rFile = preg_replace($pat, $replace, $rFile);
  //fix the Ref to the dtds for both run and list files.
  $runPat = '/CUnit-Run\.dtd/';
  $rReplace = 'http://fossology.usa.hp.com/~fossology/dtds/CUnit-Run.dtd';
  $listPat = '/CUnit-List\.dtd/';
  $lReplace = 'http://fossology.usa.hp.com/~fossology/dtds/CUnit-List.dtd';
  $rFile =  preg_replace($runPat, $rReplace, $rFile);
  $rFile =  preg_replace($listPat, $lReplace, $rFile);
  //echo "DB: rFile after preg_replace is:\n$rFile\n";
  if(!file_put_contents($fileName, $rFile))
  {
    return(FALSE);
  }
  else
  {
    return(TRUE);
  }
} // tweakCUnit


/* begin main program */

if(!defined('TESTROOT'))
{
  $path = __DIR__;
  $plenth = strlen($path);
  // remove /unit from the end.
  $TESTROOT = substr($path, 0, $plenth-5);
  $_ENV['TESTROOT'] = $TESTROOT;
  putenv("TESTROOT=$TESTROOT");
  define('TESTROOT',$TESTROOT);
}

$WORKSPACE = NULL;

if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
  define('WORKSPACE', $WORKSPACE);
}

$unit = TESTROOT . "/unit";

if(@chdir($unit) === FALSE)
{
  echo "FATAL!, could not cd to:\n$unit\n";
  exit(1);
}
require_once('../lib/bootstrap.php');
require_once('../lib/common-Report.php');
require_once('../lib/common-Test.php');
require_once('../lib/createRC.php');

createRC();
$sc = getenv('SYSCONFDIR');
echo "DB: runUnit: sysconf from getenv is:$sc\n";
$sysConf = array();
$sysConf = bootstrap();
//echo "sysConf after bootstrap is:\n";print_r($sysConf) . "\n";
// export for other tests to use

echo "DB: runUnit: globals:sysconfdir:{$GLOBALS['SYSCONFDIR']}\n";
putenv("SYSCONFDIR=$sc");
$_ENV['SYSCONFDIR'] = $sc;

echo "DB: runUnit: after putenv SYSCONFDIR from env is:" . getenv('SYSCONFDIR') . "\n";
echo "DB: runUnit: after _ENV set, SYSCONFDIR from _ENV is:{$_ENV['SYSCONFDIR']}\n";

$modules = array();
$unitList = array();

// get the list of unit tests to run and flatten the array
$modules = parse_ini_file('../dataFiles/unitTests.ini',1);
foreach($modules as $key => $value)
{
  $unitList[] = $key;
}

global $unitList;

// TODO:  Uncertain, relative paths are BAD.  Fix this
/* at this point we should be in the directory fossology/src/testing/unit/
   So let's change directories back to fossology/src/ 
   (i.e. one directory above fossology/src/testing/) */
backToParent('../..');

$failures = 0;
foreach($unitList as $unitTest)
{
  echo "\n";
  echo "$unitTest:\n";
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
  $Make = new RunTest($unitTest);
  $runResults = $Make->MakeTest();
  //debugprint($runResults, "run results for $unitTest\n");
  $Make->printResults($runResults);
  if(processCUnit($unitTest) != NULL)
  {
    echo "Error: could not process cunit results file for $unitTest\n";
  }
  if(MakeCover($unitTest) != NULL)
  {
    //echo "Error: there were errors for make coverage for $unitTest\n";
    $failures++;
  }
  backToParent('../../..');
} // foreach

// clean up xml files left behind.
//cleanXMLFiles();
if($failures)
{
  exit(1);
}
exit(0);
