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

class StartUpTest extends \PHPUnit_Framework_TestCase
{
  /** @var string */
  private $pageContent;
  
  protected function setUp()
  {
    $this->pageContent = '';
    $p = popen('php5  '. dirname(__DIR__).'/ui/index.php 2>&1', 'r');
    while (!feof($p)) {
      $line = fgets($p, 1000);
      $this->pageContent .= $line;
    }
    pclose($p);
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
