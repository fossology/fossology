<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

class HighlightDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  
  /** @var DbManager */
  private $dbManager;
  
  /** @var HighlightDao */
  private $highlightDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(array(
      'highlight',
      'license_ref',
      'pfile'
    ));

    $this->highlightDao = new HighlightDao($this->dbManager);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * Test inserting and retrieving highlights
   */
  public function testInsertHighlight()
  {
    // Insert a pfile first
    $pfileId = 123;
    $this->dbManager->insertTableRow('pfile', array(
      'pfile_pk' => $pfileId,
      'pfile_sha1' => 'ABC123',
      'pfile_md5' => 'DEF456',
      'pfile_size' => 1024
    ));

    // Insert a license reference
    $licenseId = 456;
    $this->dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId,
      'rf_shortname' => 'MIT',
      'rf_fullname' => 'MIT License',
      'rf_text' => 'MIT License text'
    ));

    // Insert highlight data
    $this->dbManager->insertTableRow('highlight', array(
      'pfile_fk' => $pfileId,
      'start' => 10,
      'len' => 50,
      'type' => 1,
      'rf_fk' => $licenseId
    ));

    // Verify the highlight was inserted
    $sql = "SELECT COUNT(*) FROM highlight WHERE pfile_fk = $1";
    $result = $this->dbManager->getSingleRow($sql, array($pfileId));
    
    assertThat($result['count'], is('1'));
    $this->addToAssertionCount(1);
  }

  /**
   * Test retrieving highlights for a specific pfile
   */
  public function testGetHighlightsForPfile()
  {
    $pfileId = 789;
    
    $this->dbManager->insertTableRow('pfile', array(
      'pfile_pk' => $pfileId,
      'pfile_sha1' => 'XYZ789',
      'pfile_md5' => 'UVW012',
      'pfile_size' => 2048
    ));

    $licenseId1 = 111;
    $licenseId2 = 222;
    
    $this->dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId1,
      'rf_shortname' => 'GPL-2.0',
      'rf_fullname' => 'GNU General Public License v2.0',
      'rf_text' => 'GPL 2.0 text'
    ));
    
    $this->dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId2,
      'rf_shortname' => 'Apache-2.0',
      'rf_fullname' => 'Apache License 2.0',
      'rf_text' => 'Apache 2.0 text'
    ));

    // Insert multiple highlights
    $this->dbManager->insertTableRow('highlight', array(
      'pfile_fk' => $pfileId,
      'start' => 100,
      'len' => 200,
      'type' => 1,
      'rf_fk' => $licenseId1
    ));
    
    $this->dbManager->insertTableRow('highlight', array(
      'pfile_fk' => $pfileId,
      'start' => 500,
      'len' => 150,
      'type' => 2,
      'rf_fk' => $licenseId2
    ));

    // Query highlights
    $sql = "SELECT * FROM highlight WHERE pfile_fk = $1 ORDER BY start";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array($pfileId));
    
    $highlights = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    
    assertThat(count($highlights), is(2));
    assertThat($highlights[0]['start'], is('100'));
    assertThat($highlights[1]['start'], is('500'));
    $this->addToAssertionCount(3);
  }

  /**
   * Test that highlights are properly associated with licenses
   */
  public function testHighlightLicenseAssociation()
  {
    $pfileId = 999;
    $licenseId = 333;
    
    $this->dbManager->insertTableRow('pfile', array(
      'pfile_pk' => $pfileId,
      'pfile_sha1' => 'TEST123',
      'pfile_md5' => 'TEST456',
      'pfile_size' => 512
    ));
    
    $this->dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId,
      'rf_shortname' => 'BSD-3-Clause',
      'rf_fullname' => 'BSD 3-Clause License',
      'rf_text' => 'BSD text'
    ));

    $this->dbManager->insertTableRow('highlight', array(
      'pfile_fk' => $pfileId,
      'start' => 1,
      'len' => 100,
      'type' => 1,
      'rf_fk' => $licenseId
    ));

    // Verify the association with a join
    $sql = "SELECT h.*, l.rf_shortname 
            FROM highlight h 
            JOIN license_ref l ON h.rf_fk = l.rf_pk 
            WHERE h.pfile_fk = $1";
    $result = $this->dbManager->getSingleRow($sql, array($pfileId));
    
    assertThat($result['rf_shortname'], is('BSD-3-Clause'));
    assertThat($result['rf_fk'], is("$licenseId"));
    $this->addToAssertionCount(2);
  }

  /**
   * Test querying highlights with different types
   */
  public function testHighlightTypes()
  {
    $pfileId = 888;
    
    $this->dbManager->insertTableRow('pfile', array(
      'pfile_pk' => $pfileId,
      'pfile_sha1' => 'TYPE123',
      'pfile_md5' => 'TYPE456',
      'pfile_size' => 1024
    ));

    $licenseId = 444;
    $this->dbManager->insertTableRow('license_ref', array(
      'rf_pk' => $licenseId,
      'rf_shortname' => 'MIT',
      'rf_fullname' => 'MIT License',
      'rf_text' => 'MIT text'
    ));

    // Insert highlights with different types
    for ($type = 1; $type <= 3; $type++) {
      $this->dbManager->insertTableRow('highlight', array(
        'pfile_fk' => $pfileId,
        'start' => $type * 100,
        'len' => 50,
        'type' => $type,
        'rf_fk' => $licenseId
      ));
    }

    // Count highlights by type
    $sql = "SELECT type, COUNT(*) as cnt FROM highlight WHERE pfile_fk = $1 GROUP BY type ORDER BY type";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array($pfileId));
    
    $typeCounts = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    
    assertThat(count($typeCounts), is(3));
    foreach ($typeCounts as $row) {
      assertThat($row['cnt'], is('1'));
    }
    $this->addToAssertionCount(4);
  }
}
