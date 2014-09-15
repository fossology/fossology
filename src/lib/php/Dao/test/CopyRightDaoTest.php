<?php
/*
Copyright (C) 2014, Siemens AG
Author: Steffen Weber

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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

class CopyRightDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  public function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;

    M::close();
  }

  public function testGetCopyrightHighlights()
  {
    $this->testDb->createPlainTables(array(),TRUE); //array('copyright'));
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $uploadDao->shouldReceive('getUploadEntry')->andReturn(array('pfile_fk'=>8));
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);
    $highlights = $copyrightDao->getCopyrightHighlights($uploadTreeId=1);
    $this->assertSame(array(), $highlights);
    
    $this->testDb->insertData(array('copyright'));
/*    $this->dbManager->queryOnce("INSERT INTO copyright (ct_pk, agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte) VALUES (15, 8, 8, 'written permission.

you agree to indemnify, hold harmless and defend adobe systems incorporated from and against any loss, damage, claims or lawsuits, including attorney''''s fees that arise or result ', '0x32c91329da4f38ae', 'statement', 698, 899)
",
            __METHOD__.'.insert.data');*/
    $highlight0 = reset($copyrightDao->getCopyrightHighlights($uploadTreeId=1));
    $this->assertInstanceOf('Fossology\Lib\Data\Highlight', $highlight0);
    $this->assertEquals($expected=899, $highlight0->getEnd());    
  }

}
 