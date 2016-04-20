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

use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

/** 
 * @todo remove this file and change Makefile
 */

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

class TestCLib extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;

  protected function setUp()
  {
    $this->testDb = new TestPgDb("testlibc".time());
  }

  protected function tearDown()
  {
    $this->testDb = null;
  }

  public function testIt()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $returnCode = 0;
    $lines = array();
    exec("./testlibs ".$sysConf."/Db.conf", $lines, $returnCode);

    $this->assertEquals($expected=0, $returnCode, "error: ".implode("\n", $lines));
  }


}