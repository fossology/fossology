<?php
/*
Copyright (C) 2014, Siemens AG
Author: Steffen Weber, Johannes Najjar

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

use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

class UploadDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;

  public function setUp()
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(
        array(
            'upload',
            'uploadtree',
        ));

    $this->testDb->insertData(
        array());

    $this->dbManager->prepare($stmt = 'insert.upload',
        "INSERT INTO upload (upload_pk, uploadtree_tablename) VALUES ($1, $2)");
    $uploadArray = array(array(1, 'uploadtree'), array(2, 'uploadtree_a'));
    foreach ($uploadArray as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }
    echo "P";
    $this->uploadDao = new UploadDao($this->dbManager);
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testGetFileTreeBounds()
  {
    $uploadTreeId = 103;
    $left = 1;
    $uploadId = 101;
    $this->dbManager->queryOnce("INSERT INTO uploadtree (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name)"
        . " VALUES ($uploadTreeId, NULL, $uploadId, 1, 33792, $left, 2, 'WXwindows.txt');",
        __METHOD__ . '.insert.data');
    /** @var ItemTreeBounds $itemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);
    $this->assertInstanceOf('Fossology\Lib\Data\Tree\ItemTreeBounds', $itemTreeBounds);

    $this->assertEquals($expected = $uploadId, $itemTreeBounds->getUploadId());
    $this->assertEquals($expected = $left, $itemTreeBounds->getLeft());
  }

  public function testGetNextItemWithEmptyArchive()
  {
    $this->prepareModularTable();

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetPreviousItemWithEmptyArchive()
  {
    $this->prepareModularTable();

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithSingleFile()
  {
    $subentries = $this->getSubentriesForSingleFile();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem->getId(), is(6));
  }

  public function testGetPreviousItemWithSingleFile()
  {
    $subentries = $this->getSubentriesForSingleFile();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithNestedFile()
  {
    $subentries = $this->getSubentriesForNestedFile();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem->getId(), is(8));
  }

  public function testGetPreviousItemWithNestedFile()
  {
    $subentries = $this->getSubentriesForNestedFile();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithFileAfterEmptyDirectory()
  {
    $subentries = $this->getSubentriesForFileAfterEmptyDirectory();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem->getId(), is(8));
  }

  public function testGetPreviousItemWithFileAfterEmptyDirectory()
  {
    $subentries = $this->getSubentriesForFileAfterEmptyDirectory();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithMultipleFiles()
  {
//    $this->markTestSkipped("not possible with sqlite");
    $subentries = $this->getSubentriesForMultipleFiles();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 6);
    assertThat($nextItem->getId(), is(7));
  }

  public function testGetPreviousItemWithMultipleFiles()
  {
//    $this->markTestSkipped("not possible with sqlite");
    $subentries = $this->getSubentriesForMultipleFiles();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 6);
    assertThat($nextItem->getId(), is(8));
  }

  /**
   * @param array $uploadTreeArray
   * @throws \Exception
   */
  protected function prepareUploadTree($uploadTreeArray = array())
  {
    $this->dbManager->prepare($stmt = 'insert.uploadtree',
        "INSERT INTO uploadtree (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)");
    foreach ($uploadTreeArray as $uploadTreeEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadTreeEntry));
    }
  }


  /**
   * @param $subentries
   * @return array
   */
  protected function prepareModularTable($subentries=array())
  {
    $right_base = 5 + count($subentries) * 2;

    $uploadTreeArray = array_merge(
        array(
            array(1, null, 1, 1, 536904704, 1, $right_base + 5, 'archive.tar.gz'),
            array(2, 1, 1, 0, 805323776, 2, $right_base + 4, 'artifact.dir'),
            array(3, 2, 1, 2, 536903680, 3, $right_base + 3, 'archive.tar'),
            array(4, 3, 1, 0, 805323776, 4, $right_base + 2, 'artifact.dir'),
            array(5, 4, 1, 0, 536888320, 5, $right_base + 1, 'archive')),
        $subentries);
    $this->prepareUploadTree($uploadTreeArray);
  }

  /**
   * @return array
   */
  protected function getSubentriesForSingleFile()
  {
    return array(array(6, 5, 1, 3, 33188, 6, 10, 'README'));
  }

  /**
   * @return array
   */
  protected function getSubentriesForNestedFile()
  {
    return array(
        array(6, 5, 1, 0, 536888320, 7, 12, 'docs'),
        array(7, 6, 1, 0, 536888320, 8, 11, 'txt'),
        array(8, 7, 1, 3, 33188, 9, 10, 'README')
    );
  }

  /**
   * @return array
   */
  protected function getSubentriesForFileAfterEmptyDirectory()
  {
    /**
     * docs      <-dir
     * docs/txt  <-dir
     * README    <-file
     */

    return array(
        array(6, 5, 1, 0, 536888320, 7, 10, 'docs'),
        array(7, 6, 1, 0, 536888320, 8, 9, 'txt'),
        array(8, 5, 1, 3, 33188, 11, 12, 'README')
    );
  }

  /**
   * @return array
   */
  protected function getSubentriesForMultipleFiles()
  {
    return array(
        array(6, 5, 1, 3, 33188, 7, 8, 'INSTALL'),
        array(7, 5, 1, 4, 33188, 8, 9, 'README'),
        array(8, 5, 1, 5, 33188, 9, 10, 'COPYING')
    );
  }

  /**
   *
   * Filestructure ( NoLic files are removed)
   * NR             uploadtree_pk
   * A                                                   1               3653
   * B_NoLic
   * C                                                   2               3668
   * D
   * D/E                                                 3               3683
   * D/F_NoLic
   * D/G                                                 4               3685
   * H
   * H/I_NoLic
   * H/J                                                 5               3671
   * H/K_NoLic
   * L
   * L/L1
   * L/L1/L1a_NoLic
   * L/L2
   * L/L2/L2a                                            6               3665
   * L/L3
   * L/L3/L3a_NoLic
   * M
   * M/M1
   * N
   * N/N1                                                7               3676
   * N/N2
   * N/N2/N2a_NoLic
   * N/N3                                                8               3675
   * N/N4
   * N/N4/N4a                                            9               3681
   * N/N5                                               10               3677
   * O                                                  11               3673
   * P
   * P/P1_NoLic
   * P/P2
   * P/P2/P2a                                           12               3658
   * P/P3                                               13               3660
   * R                                                  14               3686

   */
  protected function getTestFileStructure()
  {
    return array(
        array(3675, 3674, 32, 3299, 33188, 23, 24, 'N3'),
        array(3674, 3652, 32, 0, 536888320, 22, 37, 'N'),
        array(3673, 3652, 32, 3298, 33188, 38, 39, 'O'),
        array(3671, 3669, 32, 3296, 33188, 9, 10, 'J'),
        array(3669, 3652, 32, 0, 536888320, 4, 11, 'H'),
        array(3668, 3652, 32, 3294, 33188, 42, 43, 'C'),
        array(3662, 3661, 32, 0, 536888320, 45, 48, 'L3'),
        array(3666, 3661, 32, 0, 536888320, 49, 52, 'L1'),
        array(3665, 3664, 32, 3292, 33188, 54, 55, 'L2a'),
        array(3664, 3661, 32, 0, 536888320, 53, 56, 'L2'),
        array(3661, 3652, 32, 0, 536888320, 44, 57, 'L'),
        array(3658, 3657, 32, 3288, 33188, 60, 61, 'P2a'),
        array(3657, 3656, 32, 0, 536888320, 59, 62, 'P2'),
        array(3660, 3656, 32, 3290, 33188, 65, 66, 'P3'),
        array(3656, 3652, 32, 0, 536888320, 58, 67, 'P'),
        array(3655, 3654, 32, 0, 536888320, 69, 70, 'M1'),
        array(3654, 3652, 32, 0, 536888320, 68, 71, 'M'),
        array(3653, 3652, 32, 3287, 33188, 72, 73, 'A'),
        array(3652, 3651, 32, 0, 536888320, 3, 74, 'uploadDaoTest'),
        array(3651, 3650, 32, 0, 805323776, 2, 75, 'artifact.dir'),
        array(3650, NULL, 32, 3286, 536904704, 1, 76, 'uploadDaoTest.tar'),
        array(3686, 3652, 32, 3306, 33188, 12, 13, 'R'),
        array(3683, 3682, 32, 3303, 33188, 15, 16, 'E'),
        array(3685, 3682, 32, 3305, 33188, 17, 18, 'G'),
        array(3682, 3652, 32, 0, 536888320, 14, 21, 'D'),
        array(3677, 3674, 32, 3300, 33188, 25, 26, 'N5'),
        array(3676, 3674, 32, 3293, 33188, 27, 28, 'N1'),
        array(3678, 3674, 32, 0, 536888320, 29, 32, 'N2'),
        array(3681, 3680, 32, 3302, 33188, 34, 35, 'N4a'),
        array(3680, 3674, 32, 0, 536888320, 33, 36, 'N4'),
    );
  }

  public function testBla() {
    $this->prepareUploadTree($this->getTestFileStructure());

    $nextItem = $this->uploadDao->getNextItem(32, 3666);
    assertThat($nextItem->getId(), is(3665));
  }
}
