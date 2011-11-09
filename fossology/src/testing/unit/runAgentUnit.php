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
 * \brief Run the unit tests in module/agent_tests/Unit for all modules in
 * the input data file.
 *
 * The input is a php ini style file with each name as a section [modname].
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
      return($verFail);
      //print_r($verFail) . "\n";
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
      continue;
    }
    if(!tweakCUnit($fileName))
    {
      return("Error! could not save processed xml file, they may not display properly\n");
    }

    //echo "DB: after tweak, we are at:\n" . getcwd() . "\n";
    $errors = array();
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
      //backToParent('../../..');   // back to ..fossology/src
    }

    if(!genCunitRep($fileName))
    {
      //$failures++;
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
  //echo "DB: we are at:\n" . getcwd() . "\n";
  $rFile = file_get_contents($fileName);
  //echo "DB: rFile after read:\n$rFile\n";
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
  //echo "DB: file name to write is:$fileName\n";
  if(!file_put_contents($fileName, $rFile))
  {
    return(FALSE);
  }
  else
  {
    return(TRUE);
  }
} // tweakCUnit

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
}

$unit = TESTROOT . "/unit";

if(@chdir($unit) === FALSE)
{
  echo "FATAL!, could not cd to:\n$unit\n";
  exit(1);
}
require_once('../lib/common-Report.php');
require_once('../lib/common-Test.php');

$modules = array();
$unitList = array();

// get the list of unit tests to run and flatten the array
$modules = parse_ini_file('../dataFiles/unitTests.ini',1);
foreach($modules as $key => $value)
{
  $unitList[] = $key;
}

// @todo fix this, I don't think you need to check for workspace.
if(is_null($WORKSPACE))
{
  // back to fossology/src
  backToParent('../..');
}
else
{
  if(@chdir($WORKSPACE . "/fossology/src") === FALSE)
  {
    echo "FATAL! __FILE__ could not cd to " . $WORKSPACE . "/fossology/src\n";
    exit(1);
  }
}

$failures = 0;
foreach($unitList as $unitTest)
{
  echo "\n";
  //echo "DB: we are at:\n" . getcwd() . "\n";
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
  printResults($runResults);
  processCUnit($unitTest);
  if(MakeCover($unitTest) != NULL)
  {
    //echo "Error: there were errors for make coverage for $unitTest\n";
    $failures++;
  }
  // lib has phpunit tests
  if($other == 'lib')
  {
    // change lib/php to lib-php
    $chgUnit = preg_replace('#/#', '-', $unitTest);
    if(processXunit($chgUnit) === FALSE)
    {
      echo "Error: Could not process lib-php-Xunit.xml file\n";
    }
  }
  backToParent('../../..');
} // foreach

if($failures)
{
  exit(1);
}
exit(0);

?>