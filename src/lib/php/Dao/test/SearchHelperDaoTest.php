<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Monolog\Logger;

class SearchHelperDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var SearchHelperDao */
  private $searchHelperDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();

    $this->searchHelperDao = new SearchHelperDao($this->dbManager);

    $this->testDb->createPlainTables(array('uploadtree', 'pfile', 'tag', 'tag_file', 'tag_uploadtree'));

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    M::close();
  }

  public function testGetResultsByFilename()
  {
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $uploadDao->shouldReceive('isAccessible')->andReturn(true);

    $this->dbManager->insertTableRow('uploadtree', array('uploadtree_pk' => 1, 'ufile_name' => 'test_file.txt', 'upload_fk' => 1, 'pfile_fk' => 1));
    
    list($results, $count) = $this->searchHelperDao->GetResults(null, 'test_file.txt', 0, null, 0, 10, null, null, 'allfiles', null, null, $uploadDao, 1);
    
    assertThat($count, is(1));
    assertThat($results[0]['ufile_name'], is('test_file.txt'));
  }

  public function testGetResultsBySize()
  {
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $uploadDao->shouldReceive('isAccessible')->andReturn(true);

    $this->dbManager->insertTableRow('pfile', array('pfile_pk' => 1, 'pfile_size' => 100));
    $this->dbManager->insertTableRow('uploadtree', array('uploadtree_pk' => 1, 'ufile_name' => 'small.txt', 'upload_fk' => 1, 'pfile_fk' => 1));
    
    list($results, $count) = $this->searchHelperDao->GetResults(null, null, 0, null, 0, 10, 50, 150, 'allfiles', null, null, $uploadDao, 1);
    
    assertThat($count, is(1));
    assertThat($results[0]['ufile_name'], is('small.txt'));
  }
}
