<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

  public static function providerWhetherToUseStandalone()
  {
    return array(
      array(FALSE), // not standalone
      array(TRUE)   // standalone
    );
  }

  protected function setUp()
  {
    $this->testDataDir = dirname(__DIR__)."/testlicenses";
    $this->testDb = new TestPgDb("monkCli".time());
    $this->dbManager = $this->testDb->getDbManager();
  }

  protected function tearDown()
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function runMonk($args="", $files=array(), $standalone=FALSE)
  {
    if($standalone) {
      $temporaryKB = tempnam("/tmp", "monk.knowledgebase");
      list($output,$retCode) = $this->runMonk("-s $temporaryKB");
      $this->assertEquals(0, $retCode, "monk failed to save the knowledgebase to $temporaryKB: ".$output);
      $result = $this->runMonk("-k $temporaryKB $args", $files);
      unlink($temporaryKB);
      return $result;
    }
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf."/fossology.conf";
    system("touch ".$confFile);
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n";
    file_put_contents($confFile, $config);

    $agentName = "monk";

    $agentDir = dirname(dirname(__DIR__));
    $execDir = __DIR__;
    system("install -D $agentDir/VERSION-monk $sysConf/mods-enabled/$agentName/VERSION");

    foreach ($files as $file) {
      $args .= " ".escapeshellarg($file);
    }

    $cmd = "$execDir/$agentName -c $sysConf $args";
    echo "run: $cmd\n";
    $pipeFd = popen($cmd, "r");
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
    $this->testDb->createPlainTables(array('license_ref'),false);
    $this->testDb->createSequences(array('license_ref_rf_pk_seq'),false);

    $this->testDb->insertData_license_ref(1<<10);
  }

  /**
   * @dataProvider providerWhetherToUseStandalone
   */
  public function testRunMonkScan($standalone)
  {
    $this->setUpTables();

    list($output,$retCode) = $this->runMonk("", array($this->testDataDir."/expectedFull/Apache-2.0"), $standalone);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    $this->assertRegExp("/found full match between \".*expectedFull\\/Apache-2.0\" and \"Apache-2\\.0\" \\(rf_pk=[0-9]*\\); matched: 0\\+10272\n/", $output);
  }

  private function extractSortedLines($output) {
    $lines = explode("\n", $output);

    sort($lines, SORT_STRING);
    foreach($lines as $key => $val) {
      if (empty($val))
      {
        unset($lines[$key]);
      }
    }
    return array_values($lines);
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

  /**
   * @dataProvider providerWhetherToUseStandalone
   */
  public function testRunMultipleMonkScansFulls($standalone)
  {
    $this->setUpTables();

    $testFiles = glob($this->testDataDir."/expectedFull/*");

    list($output,$retCode) = $this->runMonk("", $testFiles, $standalone);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    sort($testFiles, SORT_STRING);
    $lines = $this->extractSortedLines($output);

    $this->assertEquals(count($testFiles), count($lines),
                        "scanned\n".implode("\n",$testFiles)."\n---\noutput\n".implode("\n",$lines)."\n---\n");


    $this->assertLinesRegex('/found full match between "$fileName" and "$licenseName" \(rf_pk=[0-9]+\); matched: [0-9]+\+[0-9]+/',
    $lines, $testFiles);
  }

  /**
   * @dataProvider providerWhetherToUseStandalone
   */
  public function testRunMultipleMonkScansDiff($standalone)
  {
    $this->setUpTables();

    $testFiles = glob($this->testDataDir."/expectedDiff/*");

    list($output,$retCode) = $this->runMonk("", $testFiles, $standalone);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    sort($testFiles, SORT_STRING);
    $lines = $this->extractSortedLines($output);

    $this->assertEquals(count($testFiles), count($lines),
                        "scanned\n".implode("\n",$testFiles)."\n---\noutput\n".implode("\n",$lines)."\n---\n");

    $this->assertLinesRegex('/found diff match between "$fileName" and "$licenseName" \(rf_pk=[0-9]+\); rank [0-9]{1,3}; diffs: \{[stMR+\[\]0-9, -]+\}/',
    $lines, $testFiles);
  }

  /**
   * @dataProvider providerWhetherToUseStandalone
   */
  public function testRunMonkHelpMode($standalone)
  {
    $this->setUpTables();

    list($output,$retCode) = $this->runMonk("-h", array(), $standalone);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    $expectedOutputRgx = '/Usage:.*/';

    $this->assertRegExp($expectedOutputRgx,$output);
  }

  /**
   * @dataProvider providerWhetherToUseStandalone
   */
  public function testRunMonkScansWithNegativeMatch($standalone)
  {
    $this->setUpTables();

    $fileName = tempnam(".", "monkCli");

    list($output,$retCode) = $this->runMonk("", array($fileName), $standalone);

    unlink($fileName);

    $this->assertEquals(0, $retCode, 'monk failed: '.$output);

    $this->assertEquals("",$output);
  }

  /**
   * @dataProvider providerWhetherToUseStandalone
   */
  public function testRunMonkScansWithNegativeMatchVerbose($standalone)
  {
    $this->setUpTables();

    $fileName = tempnam(".", "monkCli");
    $testFiles = array($fileName);

    list($output,$retCode) = $this->runMonk("-v", $testFiles, $standalone);

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
