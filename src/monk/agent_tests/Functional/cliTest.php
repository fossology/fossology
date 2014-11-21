<?php
/*
Copyright (C) 2014, Siemens AG

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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;


class MonkCliTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var string */
  private $testDataDir;

  public function setUp()
  {
    $this->testDataDir = dirname(__DIR__)."/testlicenses";
    $this->testDb = new TestPgDb("monkCli".time());
    $this->dbManager = $this->testDb->getDbManager();
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function runMonk($args="", $files=array())
  {
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf."/fossology.conf";
    system("touch ".$confFile);
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n";
    file_put_contents($confFile, $config);

    $agentName = "monk";

    $agentDir = dirname(dirname(__DIR__));
    $execDir = __DIR__;
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    foreach ($files as $file) {
      $args .= " ".escapeshellarg($file);
    }

    $pipeFd = popen("$execDir/$agentName -c $sysConf $args", "r");
    $this->assertTrue($pipeFd !== false, 'running monk failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);


    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");
    unlink($confFile);

    return array($output,$retCode);
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array('license_ref','agent'),false);
    $this->testDb->createSequences(array('license_ref_rf_pk_seq','agent_agent_pk_seq'),false);
    $this->testDb->alterTables(array('agent'),false);

    $this->testDb->insertData_license_ref(1<<10);
  }

  public function testRunMonkScan()
  {
    $this->setUpTables();

    list($output,$retCode) = $this->runMonk("", array($this->testDataDir."/expectedFull/Apache-2.0"));

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    $this->assertRegExp("/found full match between \".*expectedFull\\/Apache-2.0\" and \"Apache-2\\.0\" \\(rf_pk=[0-9]*\\); matched: 0\\+10272\n/", $output);
  }

  private function extractSortedLines($output) {
    $lines = explode("\n", $output);

    sort($lines, SORT_STRING);
    foreach($lines as $key => $val) {
      if (empty($val))
        unset($lines[$key]);
    }
    $lines = array_values($lines);

    return $lines;
  }

  private function assertLinesRegex($regexFmt, $lines, $testFiles) {
    for ($i = 0; $i < count($lines); $i++)
    {
      $line = $lines[$i];

      $file = $testFiles[$i];
      $licenseName = preg_quote(preg_replace('/.*\/([^,]*),?[^,]*/','${1}', $file), "/");

      $fileName = preg_quote($file, "/");

      $regex = $regexFmt;
      $regex = preg_replace("/\\\$fileName/", $fileName, $regex);
      $regex = preg_replace("/\\\$licenseName/", $licenseName, $regex);

      $this->assertRegExp($regex, $line);
    }
  }

  public function testRunMultipleMonkScansFulls()
  {
    $this->setUpTables();

    $testFiles = glob($this->testDataDir."/expectedFull/*");

    list($output,$retCode) = $this->runMonk("", $testFiles);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    sort($testFiles, SORT_STRING);
    $lines = $this->extractSortedLines($output);

    $this->assertEquals(count($testFiles), count($lines),
                        "scanned\n".implode("\n",$testFiles)."\n---\noutput\n".implode("\n",$lines)."\n---\n");


    $this->assertLinesRegex('/found full match between "$fileName" and "$licenseName" \(rf_pk=[0-9]+\); matched: [0-9]+\+[0-9]+/',
    $lines, $testFiles);
  }

  public function testRunMultipleMonkScansDiff()
  {
    $this->setUpTables();

    $testFiles = glob($this->testDataDir."/expectedDiff/*");

    list($output,$retCode) = $this->runMonk("", $testFiles);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    sort($testFiles, SORT_STRING);
    $lines = $this->extractSortedLines($output);

    $this->assertEquals(count($testFiles), count($lines),
                        "scanned\n".implode("\n",$testFiles)."\n---\noutput\n".implode("\n",$lines)."\n---\n");

    $this->assertLinesRegex('/found diff match between "$fileName" and "$licenseName" \(rf_pk=[0-9]+\); rank [0-9]{1,3}; diffs: \{[stMR+\[\]0-9, -]+\}/',
    $lines, $testFiles);
  }

  public function testRunMonkHelpMode()
  {
    $this->setUpTables();

    list($output,$retCode) = $this->runMonk("-h", array());

    $this->assertEquals(3, $retCode, 'monk failed: '.$output);

    $expectedOutputRgx =
"/Usage: .*\/monk \[options\] -- \[file \[file \[\.\.\.\]\]
  -h   :: help \(print this message\), then exit\.
  -c   :: specify the directory for the system configuration\.
  -v   :: verbose output\.
  file :: scan file and print licenses detected within it\.
  no file :: process data from the scheduler\.
  -V   :: print the version info, then exit\./";

    $this->assertRegExp($expectedOutputRgx,$output);
  }

  public function testRunMonkScansWithNegativeMatch()
  {
    $this->setUpTables();

    $fileName = tempnam(".", "monkCli");

    list($output,$retCode) = $this->runMonk("", array($fileName));

    unlink($fileName);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    $this->assertEquals("",$output);
  }

  public function testRunMonkScansWithNegativeMatchVerbose()
  {
    $this->setUpTables();

    $fileName = tempnam(".", "monkCli");
    $testFiles = array($fileName);

    list($output,$retCode) = $this->runMonk("-v", $testFiles);

    unlink($fileName);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    sort($testFiles, SORT_STRING);
    $lines = $this->extractSortedLines($output);

    $this->assertEquals(count($testFiles), count($lines),
                        "scanned\n".implode("\n",$testFiles)."\n---\noutput\n".implode("\n",$lines)."\n---\n");

    $this->assertLinesRegex('/$fileName contains license\(s\) No_license_found/',
    $lines, $testFiles);
  }
}
