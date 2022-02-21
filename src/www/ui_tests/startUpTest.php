<?php
/*
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

use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

class StartUpTest extends \PHPUnit\Framework\TestCase
{
  /** @var string */
  private $pageContent;
  /**
   * @var TestPgDb $testDb
   * Test helper
   */
  private $testDb;
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("uistart");
    $this->testDb->setupSysconfig();
    $this->setUpRepo();
    putenv("SYSCONFDIR=" . $this->testDb->getFossSysConf());
    $this->pageContent = '';
    $p = popen('php  '. dirname(__DIR__).'/ui/index.php 2>&1', 'r');
    while (!feof($p)) {
      $line = fgets($p, 1000);
      $this->pageContent .= $line;
    }
    pclose($p);
  }

  protected function tearDown() : void
  {
    $this->rmRepo();
    $this->testDb->fullDestruct();
    $this->testDb = null;
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
  }

  private function rmRepo()
  {
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  private function assertCriticalStringNotfound($critical) {
    $criticalPos = strpos($this->pageContent, $critical);
    $criticalEnd = $criticalPos===false ? $criticalPos : strpos($this->pageContent, "\n", $criticalPos);
    $this->assertTrue(false===$criticalPos, "There was a $critical at position $criticalPos:\n". substr($this->pageContent, $criticalPos, $criticalEnd-$criticalPos)."\n");
  }

  public function testIsHtmlAndNoWarningFound()
  {
    assertThat($this->pageContent, startsWith('<!DOCTYPE html>'));
    $this->assertCriticalStringNotfound('PHP Notice');
    $this->assertCriticalStringNotfound('PHP Fatal error');
    $this->assertCriticalStringNotfound('PHP Warning');
  }

}
