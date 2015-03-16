<?php
/*
Copyright (C) 2014-2015, Siemens AG
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

use Fossology\Lib\Data\Tree\Item;
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

    $this->dbManager->prepare($stmt = 'insert.upload',
        "INSERT INTO upload (upload_pk, uploadtree_tablename) VALUES ($1, $2)");
    $uploadArray = array(array(1, 'uploadtree'), array(2, 'uploadtree_a'));
    foreach ($uploadArray as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }
    $logger = M::mock('Monolog\Logger'); // new Logger("UploadDaoTest");
    $logger->shouldReceive('debug');
    $this->uploadDao = new UploadDao($this->dbManager, $logger);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
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
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId);
    assertThat($itemTreeBounds, anInstanceOf('Fossology\Lib\Data\Tree\ItemTreeBounds'));

    assertThat($uploadId, equalTo($itemTreeBounds->getUploadId()));
    assertThat($left, equalTo($itemTreeBounds->getLeft()));
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
    $subentries = $this->getSubentriesForMultipleFiles();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 6);
    assertThat($nextItem->getId(), is(7));
  }

  public function testGetPreviousItemWithMultipleFiles()
  {
    $subentries = $this->getSubentriesForMultipleFiles();
    $this->prepareModularTable($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 6);
    assertThat($nextItem, anInstanceOf(Item::classname()));
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
  protected function prepareModularTable($subentries = array())
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
        array(6, 5, 1, 3, 33188, 9, 10, 'INSTALL'),
        array(7, 5, 1, 4, 33188, 11, 12, 'README'),
        array(8, 5, 1, 5, 33188, 7, 8, 'COPYING')
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

  private $entries = array(
      3653, 3668, 3683, 3685, 3671, 3665, 3676, 3675, 3681, 3677, 3673, 3658, 3660, 3686,
  );

  protected function getTestFileStructure()
  {
    $isFile = 33188;
    $isContainer = 536888320;
    return array(
        array(3650, NULL, 32, 3286, 536904704, 1, 76, 'uploadDaoTest.tar'),
        array(3651, 3650, 32, 0, 805323776, 2, 75, 'artifact.dir'),
        array(3652, 3651, 32, 0, 536888320, 3, 74, 'uploadDaoTest'),

        array(3653, 3652, 32, 3287, $isFile, 4, 5, 'A'),
        // * B_NoLic 6:7
        array(3668, 3652, 32, 3294, $isFile, 8, 9, 'C'),
        array(3682, 3652, 32, 0, $isContainer, 10, 16, 'D'),
        array(3683, 3682, 32, 3303, $isFile, 11, 12, 'E'),
        // * D/F_NoLic 13:14
        array(3685, 3682, 32, 3305, $isFile, 14, 15, 'G'),
        array(3669, 3652, 32, 0, $isContainer, 16, 23, 'H'),
        // * H/I_NoLic
        array(3671, 3669, 32, 3296, $isFile, 19, 20, 'J'),
        // * H/K_NoLic 21:22
        array(3661, 3652, 32, 0, $isContainer, 24, 37, 'L'),
        array(3666, 3661, 32, 0, $isContainer, 25, 28, 'L1'),
        // * L/L1/L1a_NoLic 26:27
        array(3664, 3661, 32, 0, $isContainer, 29, 32, 'L2'),
        array(3665, 3664, 32, 3292, $isFile, 30, 31, 'L2a'),
        array(3662, 3661, 32, 0, $isContainer, 33, 36, 'L3'),
        // * L/L3/L3a_NoLic 34:35
        array(3654, 3652, 32, 0, $isContainer, 38, 41, 'M'),
        array(3655, 3654, 32, 0, $isContainer, 39, 40, 'M1'),
        array(3674, 3652, 32, 0, $isContainer, 42, 57, 'N'),
        array(3676, 3674, 32, 3293, $isFile, 43, 44, 'N1'),
        array(3678, 3674, 32, 0, $isContainer, 45, 48, 'N2'),
        // * N/N2/N2a_NoLic 46:47
        array(3675, 3674, 32, 3299, $isFile, 49, 50, 'N3'),
        array(3680, 3674, 32, 0, $isContainer, 51, 54, 'N4'),
        array(3681, 3680, 32, 3302, $isFile, 52, 53, 'N4a'),
        array(3677, 3674, 32, 3300, $isFile, 55, 56, 'N5'),
        array(3673, 3652, 32, 3298, $isFile, 58, 59, 'O'),
        array(3656, 3652, 32, 0, $isContainer, 60, 69, 'P'),
        //   * P/P1_NoLic 61:62
        array(3657, 3656, 32, 0, $isContainer, 63, 66, 'P2'),
        array(3658, 3657, 32, 3288, $isFile, 64, 65, 'P2a'),
        array(3660, 3656, 32, 3290, 33188, 67, 68, 'P3'),
        array(3686, 3652, 32, 3306, 33188, 70, 71, 'R'),
        //   * S_NoLic 72:73
    );
  }

  public function testGetNextItemUsesRecursiveAndRegularSearchAsFallback()
  {
    $this->prepareUploadTree($this->getTestFileStructure());

    // L1 -> N1
    $nextItem = $this->uploadDao->getNextItem(32, 3666);
    assertThat($nextItem->getId(), is(3665));
  }

  public function testGetPrevItemUsesRecursiveAndRegularSearchAsFallback()
  {
    $this->prepareUploadTree($this->getTestFileStructure());

    $nextItem = $this->uploadDao->getPreviousItem(32, 3666);
    assertThat($nextItem->getId(), is(3671));
  }

  public function testGetNextItemUsesRecursiveOnly()
  {
    $this->prepareUploadTree($this->getTestFileStructure());

    $nextItem = $this->uploadDao->getNextItem(32, 3674);
    assertThat($nextItem->getId(), is(3676));
  }

  public function testGetPrevItemUsesRecursiveOnly()
  {
    $this->prepareUploadTree($this->getTestFileStructure());

    $nextItem = $this->uploadDao->getPreviousItem(32, 3674);
    assertThat($nextItem->getId(), is(3665));
  }

  public function testGetNextFull()
  {
    $this->prepareUploadTree($this->getTestFileStructure());

    $previousId = 3650;
    foreach ($this->entries as $entry) {
      $nextItem = $this->uploadDao->getNextItem(32, $previousId);
      assertThat($nextItem->getId(), is($entry));
      $previousId = $entry;
    }

    $nextItem = $this->uploadDao->getNextItem(32, $previousId);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetPreviousFull()
  {
    $this->prepareUploadTree($this->getTestFileStructure());

    $entries = array_reverse($this->entries);

    $previousId = $entries[0];
    foreach (array_slice($entries, 1) as $entry) {
      $previousItem = $this->uploadDao->getPreviousItem(32, $previousId);
      assertThat($previousItem->getId(), is($entry));
      $previousId = $entry;
    }

    $previousItem = $this->uploadDao->getPreviousItem(32, $previousId);
    assertThat($previousItem, is(nullValue()));
  }
  
  
  public function testGetNonArtifactDescendants()
  {
    $this->dbManager->queryOnce('ALTER TABLE uploadtree RENAME TO uploadtree_a');
    $this->testDb->insertData(array('uploadtree_a'));
    
    $artifact = new ItemTreeBounds(2,'uploadtree_a', 1, 2, 3);
    $artifactDescendants = $this->uploadDao->getNonArtifactDescendants($artifact);
    assertThat($artifactDescendants, emptyArray());
   
    $zip = new ItemTreeBounds(1,'uploadtree_a', 1, 1, 24);
    $zipDescendants = $this->uploadDao->getNonArtifactDescendants($zip);
    $zipMatcher = array_map(function($id){ return hasKeyValuePair('uploadtree_pk',$id);}, array(6,7,8,10,11,12));
    assertThat($zipDescendants, arrayContainingInAnyOrder( $zipMatcher ) );
  }
}
