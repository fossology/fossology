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
    $this->dbManager = $this->testDb->getDbManager();

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
    $this->prepareUploadTree();

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetPreviousItemWithEmptyArchive()
  {
    $this->prepareUploadTree();

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithSingleFile()
  {
    $subentries = $this->getSubentriesForSingleFile();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem->getId(), is(6));
  }

  public function testGetPreviousItemWithSingleFile()
  {
    $subentries = $this->getSubentriesForSingleFile();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithNestedFile()
  {
    $subentries = $this->getSubentriesForNestedFile();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem->getId(), is(8));
  }

  public function testGetPreviousItemWithNestedFile()
  {
    $subentries = $this->getSubentriesForNestedFile();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithFileAfterEmptyDirectory()
  {
    $subentries = $this->getSubentriesForFileAfterEmptyDirectory();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 1);
    assertThat($nextItem->getId(), is(8));
  }

  public function testGetPreviousItemWithFileAfterEmptyDirectory()
  {
    $subentries = $this->getSubentriesForFileAfterEmptyDirectory();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 1);
    assertThat($nextItem, is(nullValue()));
  }

  public function testGetNextItemWithMultipleFiles()
  {
//    $this->markTestSkipped("not possible with sqlite");
    $subentries = $this->getSubentriesForMultipleFiles();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getNextItem(1, 6);
    assertThat($nextItem->getId(), is(7));
  }

  public function testGetPreviousItemWithMultipleFiles()
  {
//    $this->markTestSkipped("not possible with sqlite");
    $subentries = $this->getSubentriesForMultipleFiles();
    $this->prepareUploadTree($subentries);

    $nextItem = $this->uploadDao->getPreviousItem(1, 6);
    assertThat($nextItem->getId(), is(8));
  }

  /**
   * @param $subentries
   */
  protected function prepareUploadTree($subentries = array())
  {
    $right_base = 5 + count($subentries) * 2;
    $this->dbManager->prepare($stmt = 'insert.uploadtree',
        "INSERT INTO uploadtree (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)");
    $uploadTreeArray = array_merge(
        array(
            array(1, null, 1, 1, 536904704, 1, $right_base + 5, 'archive.tar.gz'),
            array(2, 1, 1, 0, 805323776, 2, $right_base + 4, 'artifact.dir'),
            array(3, 2, 1, 2, 536903680, 3, $right_base + 3, 'archive.tar'),
            array(4, 3, 1, 0, 805323776, 4, $right_base + 2, 'artifact.dir'),
            array(5, 4, 1, 0, 536888320, 5, $right_base + 1, 'archive')),
        $subentries);
    foreach ($uploadTreeArray as $uploadTreeEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadTreeEntry));
    }
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
}
