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

class HighlightDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var integer */
  private $assertCountBefore;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();

    $this->highlightDao = new HighlightDao($this->dbManager);

    $this->testDb->createPlainTables(array('highlight', 'highlight_bulk', 'clearing_event', 'license_ref_bulk'));

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

  public function testGetHighlightRegion()
  {
    $this->dbManager->insertTableRow('highlight', array('fl_fk' => 1, 'start' => 10, 'len' => 5));
    $this->dbManager->insertTableRow('highlight', array('fl_fk' => 1, 'start' => 20, 'len' => 10));

    $region = $this->highlightDao->getHighlightRegion(1);
    assertThat($region, is(array(10, 30)));
  }

  public function testGetHighlightBulk()
  {
    $this->dbManager->insertTableRow('clearing_event', array('clearing_event_pk' => 1, 'uploadtree_fk' => 1, 'rf_fk' => 10));
    $this->dbManager->insertTableRow('license_ref_bulk', array('lrb_pk' => 1, 'rf_text' => 'test', 'uploadtree_fk' => 1));
    $this->dbManager->insertTableRow('highlight_bulk', array('clearing_event_fk' => 1, 'lrb_fk' => 1, 'start' => 5, 'len' => 10));

    $highlights = $this->highlightDao->getHighlightBulk(1);
    assertThat(count($highlights), is(1));
    assertThat($highlights[0]->getStart(), is(5));
    assertThat($highlights[0]->getEnd(), is(15));
  }
}
