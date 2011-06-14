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
 * @version "$Id$"
 *
 * Created on Jun 10, 2011 by Mark Donohoe
 */

require_once 'common-Report.php';

$unitList = array('ununpack', 'copyright');

$home = getcwd();

$WORKSPACE = NULL;

if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
}
//echo "DB: runUnit: home is:$home\nwksp is:$WORKSPACE\n";


$lastTD = exec($WORKSPACE . '/fossology/tests/checkTestData.php 2>&1',
$tdOut, $tdRtn);
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
  if(chdir("../agents") == FALSE)
  {
    echo "FATAL! runRUnit could not cd to ../agents";
    exit(1);
  }
}
else {
  if(chdir($WORKSPACE . "/fossology/agents") === FALSE)
  {
    echo "FATAL! runRUnit could not cd to" . $WORKSPACE . "fossology/agents\n";
    exit(1);
  }
}

$failures = 0;

foreach($unitList as $unitTest)
{
  if(chdir($unitTest) === FALSE)
  {
    echo "Error! cannot cd to $unitTest, skipping test\n";
    continue;
  }
  $lastMake = exec('make test 2>&1', $makeOut, $makeRtn);
  if($makeRtn != 0)
  {
    echo "Error! tests did not make for $unitTest\n";
    continue;
  }
  else
  {
    // cd to tests and fix things up.
    if(chdir('tests') === FALSE)
    {
      echo "Error! cannot cd to" . $unitTest . "/tests, skipping postprocessing\n";
      continue;
    }
    foreach(glob("$unitTest*.xml") as $fileName)
    {
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
          //echo "There were errors in the $fileName\n";
          //print_r($verFail) . "\n";
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
      echo "for report: filename is:$fileName\n";
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
      $outPath = $WORKSPACE . "/fossology/tests/Reports/$outFile.html";
      $xslPath = "/home/jenkins/public_html/CUnit/$xslFile";
      $report = genHtml($fileName, $outPath, $xslPath);
      if(!empty($report))
      {
        echo "Error: Could not generate a HTML Test report from $fileName.\n";
        echo "DB: report is:\n$report\n";
      }
    } //foreach(glob....
  }
  if(chdir('../..') === FALSE)
  {
    echo "FATAL! could not cd to ../..\n";
    exit(1);
  }
} // for
if($failures)
{
  exit(1);
}
exit(0);
?>