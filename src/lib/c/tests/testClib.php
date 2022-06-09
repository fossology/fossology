<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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

class TestCLib extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("testlibc".time());
  }

  protected function tearDown() : void
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
