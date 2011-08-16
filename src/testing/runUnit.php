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
 * \brief run and process the unit test results
 *
 * The results files have to be post processed to change where the xsl and dtd
 * files are located.  The xml files are also processed into html.
 *
 * This script assumes that CUnit is installed on the system.
 *
 * @version "$Id: runUnit.php 4458 2011-07-07 00:36:41Z rrando $"
 *
 * Created on Jun 10, 2011 by Mark Donohoe
 */

require_once 'lib/common-Report.php';

/**
 * \brief chdir to the supplied path or exit with a FATAL message
 *
 * @param string $howFar the string to chdir to.
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

$modules = array();
$unitList = array();

// get the list of unit tests to run
$modules = parse_ini_file('unitTests.ini',1);
foreach($modules as $key => $value)
{
  $unitList[] = $key;
}
//echo "the list of modules is:\n";
//print_r($unitList) . "\n";

$home = getcwd();
//echo "home is:$home\n";

$WORKSPACE = NULL;

if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
}
//echo "DB: runUnit: home is:$home\nwksp is:$WORKSPACE\n";
//echo "DB contents of ENV is:\n";print_r($_ENV) . "\n";

//$ctdPath = $WORKSPACE . '/fossology2.0/fossology/src/testing/checkTestData.php 2>&1';
$ctdPath = $home . '/checkTestData.php 2>&1';
$lastTD = exec($ctdPath,$tdOut, $tdRtn);
if($tdRtn != 0)
{
  echo "FATAL! runUnit could not check or downlown load test data, stopping tests";
  echo "Output was:";
  print_r($tdOut) . "\n";
  exit(1);
}
//echo "DB: output from check TD is:\n";print_r($tdOut) . "\n";
if(is_null($WORKSPACE))
{
  backToParent('..');
}
else {
  if(@chdir($WORKSPACE . "/fossology/src") === FALSE)
  {
    echo "FATAL! runRUnit could not cd to " . $WORKSPACE . "/fossology/src\n";
    exit(1);
  }
}

$failures = 0;

// NOTE LIB does not have the standard dir structure....special case?
// Same goes for www, there is only a ui_tests, so can the code be flexible?
// cli too:
// That is, just note it didn't find a dir and continue on?
foreach($unitList as $unitTest)
{
  $makeOut = array();
  $makeRtn = -777;
  $makeCover = array();
  $makeRtn = -777;

  //echo "DB: unit test is:$unitTest\n";
  $here = getcwd();
  //echo "We are now at:$here\n";

  if(@chdir($unitTest . '/agent_tests/Unit') === FALSE)
  {
    echo "Error! cannot cd to " . $unitTest . "/agent_tests/Unit, skipping test\n";
    $failures++;
    continue;
  }
  $here = getcwd();
  //echo "Before unit test make we are at:$here\n";
  $lastMake = exec('make test 2>&1', $makeOut, $makeRtn);
  //echo "results of make test are:\n";print_r($makeOut) . "\n";
  if($makeRtn != 0)
  {
    $found = array();
    $found = preg_grep('/No rule to make target/', $makeOut);
    if($found)
    {
      echo "No Unit Tests for module $unitTest\n";
    }
    else
    {
      echo "Error! tests did not make for $unitTest\n";
      $failures++;
    }
    backToParent('../../..');
    continue;
  }
  else
  {
    foreach(glob("$unitTest*.xml") as $fileName)
    {
      echo "DB: processing xml file:$fileName\n";
      $foo =  getcwd();
      echo "DB: we are at:$foo\n";
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
      if(!file_put_contents($fileName, $rFile))
      {
        echo "Error! could not save processed xml file, they may not display properly\n";
        continue;
      }
      try {
        $verFail = check4CUnitFail($fileName);
        if(!is_null($verFail))
        {
          echo "There were Unit Test Failures in $unitTest\n";
          print_r($verFail) . "\n";
          $failures++;
        }
      }
      catch (Exception $e)
      {
        echo "Failure: Could not check file $fileName for failures\n";
      }
      // create html report for this file.... get List or Run string so the
      // correct xsl file is used.
      $type = "Run";
      if(preg_grep("/Run/", array($fileName)))
      {
        $type = "Run";
      }
      else if(preg_grep("/List/", array($fileName)))
      {
        $type = "List";
      }
      $xslFile = "CUnit-$type.xsl";
      // remove .xml from name
      $outFile = basename($fileName, '.xml');
      $outPath = $WORKSPACE . "/fossology/src/testing/reports/$outFile.html";
      $xslPath = "/usr/share/CUnit/$xslFile";
      $report = genHtml($fileName, $outPath, $xslPath);
      if(!empty($report))
      {
        echo "Error: Could not generate a HTML Test report from $fileName.\n";
        echo "DB: report is:\n$report\n";
      }
      echo "DB: Generated html file:$outFile" . ".html\n";
    } //foreach(glob....
    // generate coverage report
    $lastCover = exec('make coverage 2>&1', $makeCover, $coverRtn);
    if($coverRtn != 0)
    {
      echo "Error! Coverage did not make for $unitTest\n";
      $failures++;
      backToParent('../../../');
      continue;
    }
  }
  backToParent('../../..');
} // for
if($failures)
{
  exit(1);
}
exit(0);
?>