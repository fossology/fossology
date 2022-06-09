<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 SPDX-FileCopyrightText: © 2017 TNG Technology Consulting GmbH
 Author: Steffen Weber, Johannes Najjar, Maximilian Huber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;

class TreeDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var TreeDao */
  private $treeDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(array('upload','uploadtree'));

    $this->dbManager->prepare($stmt = 'insert.upload',
        "INSERT INTO upload (upload_pk, uploadtree_tablename) VALUES ($1, $2)");
    $uploadArray = array(array(1, 'uploadtree'), array(32, 'uploadtree'));
    foreach ($uploadArray as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }
    $this->treeDao = new TreeDao($this->dbManager);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);

    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testGetMinimalCoveringItem()
  {
    $this->prepareModularTable(array());
    $coverContainer = $this->treeDao->getMinimalCoveringItem(1, "uploadtree");
    assertThat($coverContainer,equalTo(5));

    $this->prepareUploadTree(array(array($item=99,null,$upload=32,88,0x0,1,2,'plainFile',null)));
    $coverSelf = $this->treeDao->getMinimalCoveringItem($upload, "uploadtree");
    assertThat($coverSelf,equalTo($item));
  }

  public function testGetFullPathFromSingleFolderUpload()
  {
    $this->prepareModularTable(array(array(6,5,1,0,0,5,6,$fName="file",5)));
    $cover = $this->treeDao->getMinimalCoveringItem(1, "uploadtree");
    assertThat($cover,equalTo(5));

    $path = $this->treeDao->getFullPath(6, "uploadtree", $cover);
    assertThat($path,equalTo($fName));

    $path = $this->treeDao->getFullPath(6, "uploadtree", $cover, true);
    assertThat($path,equalTo($fName));
  }

  public function testGetFullPathFromSingleFolderUploadWithDropArtifact()
  {
    $this->prepareModularTable(array(array(6,5,1,0,0,5,6,$fName="file",5)));
    $cover = $this->treeDao->getMinimalCoveringItem(1, "uploadtree", true);
    assertThat($cover,equalTo(5));
    $path = $this->treeDao->getFullPath(6, "uploadtree", $cover);
    assertThat($path,equalTo($fName));
  }

  public function testGetFullPathFromSingleFolderUploadWithAFileOutside()
  {
    $this->prepareModularTable(array(
      array(6,5,1,0,0,5,6,"file",5),
      array(11,4,1,0,0,11,12,"file2",3)
    ));
    $cover = $this->treeDao->getMinimalCoveringItem(1, "uploadtree");
    assertThat($cover,equalTo(4));

    $pathInsideArchive = $this->treeDao->getFullPath(6, "uploadtree", $cover);
    assertThat($pathInsideArchive,equalTo("archive/file"));

    $pathInsideArchive = $this->treeDao->getFullPath(6, "uploadtree", $cover, true);
    assertThat($pathInsideArchive,equalTo("archive/file"));

    $pathOutsideArchive = $this->treeDao->getFullPath(11, "uploadtree", $cover);
    assertThat($pathOutsideArchive,equalTo("file2"));

    $pathOutsideArchive = $this->treeDao->getFullPath(11, "uploadtree", $cover, true);
    assertThat($pathOutsideArchive,equalTo("file2"));
  }

  /**
   * @param array $uploadTreeArray
   * @throws \Exception
   */
  protected function prepareUploadTree($uploadTreeArray = array())
  {
    $this->dbManager->prepare($stmt = 'insert.uploadtree',
        "INSERT INTO uploadtree (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name, realparent) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)");
    foreach ($uploadTreeArray as $uploadTreeEntry) {
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
            array(1, null, 1, 1, 0x20008400, 1, $right_base + 5, 'archive.tar.gz', null),
            array(2,    1, 1, 0, 0x30004400, 2, $right_base + 4, 'artifact.dir', 1),
            array(3,    2, 1, 2, 0x20008000, 3, $right_base + 3, 'archive.tar', 1),
            array(4,    3, 1, 0, 0x30004400, 4, $right_base + 2, 'artifact.dir', 3),
            array(5,    4, 1, 0, 0x20004400, 5, $right_base + 1, 'archive', 3)),
        $subentries);
    $this->prepareUploadTree($uploadTreeArray);
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
        array(3675, 3674, 32, 3299, 33188, 23, 24, 'N3',3674),
        array(3674, 3652, 32, 0, 0x20004400, 22, 37, 'N',3652),
        array(3673, 3652, 32, 3298, 33188, 38, 39, 'O',3652),
        array(3671, 3669, 32, 3296, 33188, 9, 10, 'J',3669),
        array(3669, 3652, 32, 0, 0x20004400, 4, 11, 'H',3652),
        array(3668, 3652, 32, 3294, 33188, 42, 43, 'C',3652),
        array(3662, 3661, 32, 0, 0x20004400, 45, 48, 'L3',3661),
        array(3666, 3661, 32, 0, 0x20004400, 49, 52, 'L1',3661),
        array(3665, 3664, 32, 3292, 33188, 54, 55, 'L2a',3664),
        array(3664, 3661, 32, 0, 0x20004400, 53, 56, 'L2',3661),
        array(3661, 3652, 32, 0, 0x20004400, 44, 57, 'L',3652),
        array(3658, 3657, 32, 3288, 33188, 60, 61, 'P2a',3657),
        array(3657, 3656, 32, 0, 0x20004400, 59, 62, 'P2',3656),
        array(3660, 3656, 32, 3290, 33188, 65, 66, 'P3',6356),
        array(3656, 3652, 32, 0, 0x20004400, 58, 67, 'P',3652),
        array(3655, 3654, 32, 0, 0x20004400, 69, 70, 'M1',3654),
        array(3654, 3652, 32, 0, 0x20004400, 68, 71, 'M',3652),
        array(3653, 3652, 32, 3287, 33188, 72, 73, 'A',3652),
        array(3652, 3651, 32, 0, 0x20004400, 3, 74, 'uploadDaoTest',3650),
        array(3651, 3650, 32, 0, 0x30004400, 2, 75, 'artifact.dir',3650),
        array(3650, NULL, 32, 3286, 0x20008400, 1, 76, 'uploadDaoTest.tar',null),
        array(3686, 3652, 32, 3306, 33188, 12, 13, 'R',3652),
        array(3683, 3682, 32, 3303, 33188, 15, 16, 'E',3682),
        array(3685, 3682, 32, 3305, 33188, 17, 18, 'G',3682),
        array(3682, 3652, 32, 0, 0x20004400, 14, 21, 'D',3652),
        array(3677, 3674, 32, 3300, 33188, 25, 26, 'N5',3674),
        array(3676, 3674, 32, 3293, 33188, 27, 28, 'N1',3674),
        array(3678, 3674, 32, 0, 0x20004400, 29, 32, 'N2',3674),
        array(3681, 3680, 32, 3302, 33188, 34, 35, 'N4a',3680),
        array(3680, 3674, 32, 0, 0x20004400, 33, 36, 'N4',3674),
    );
  }

  public function testGetFullPathWithComlpexStructureFromFolder()
  {
    $this->prepareUploadTree($this->getTestFileStructure());
    $cover = $this->treeDao->getMinimalCoveringItem(32, "uploadtree");
    assertThat($cover,equalTo(3652));
    assertThat($this->treeDao->getFullPath(3666, "uploadtree", $cover),      equalTo("L/L1"));
    assertThat($this->treeDao->getFullPath(3666, "uploadtree", $cover,true), equalTo("L/L1"));
  }

  public function testGetFullPathWithComlpexStructureFromFile()
  {
    $this->prepareUploadTree($this->getTestFileStructure());
    $cover = $this->treeDao->getMinimalCoveringItem(32, "uploadtree");
    assertThat($cover,equalTo(3652));

    assertThat($this->treeDao->getFullPath(3665, "uploadtree", $cover),       equalTo("L/L2/L2a"));
    assertThat($this->treeDao->getFullPath(3665, "uploadtree", $cover, true), equalTo("L/L2/L2a"));

    assertThat($this->treeDao->getFullPath(3665, "uploadtree"),        equalTo("uploadDaoTest.tar/uploadDaoTest/L/L2/L2a"));
    assertThat($this->treeDao->getFullPath(3665, "uploadtree",0,true), equalTo(                  "uploadDaoTest/L/L2/L2a"));
  }

  public function testGetFullPathWithComlpexStructureFromFileAndOtherUpload()
  {
    $this->prepareUploadTree($this->getTestFileStructure());
    $this->prepareModularTable(array(array(6,5,1,0,0,5,6,"file",6)));
    $cover = $this->treeDao->getMinimalCoveringItem(32, "uploadtree");
    assertThat($cover,equalTo(3652));
    assertThat($this->treeDao->getFullPath(3665, "uploadtree", $cover),      equalTo("L/L2/L2a"));
    assertThat($this->treeDao->getFullPath(3665, "uploadtree", $cover,true), equalTo("L/L2/L2a"));
    assertThat($this->treeDao->getFullPath(3665, "uploadtree"),        equalTo("uploadDaoTest.tar/uploadDaoTest/L/L2/L2a"));
    assertThat($this->treeDao->getFullPath(3665, "uploadtree",0,true), equalTo(                  "uploadDaoTest/L/L2/L2a"));
  }

  public function testGetUploadHashes()
  {
    $this->testDb->createPlainTables(array('pfile'));
    $this->dbManager->queryOnce('ALTER TABLE uploadtree RENAME TO uploadtree_a');
    $this->testDb->insertData(array('uploadtree_a','pfile'));
    // (pfile_pk, pfile_md5, pfile_sha1, pfile_sha256, pfile_size) := (762, 'C655F0AD3E4D3F90D8EE0541DD636E2E', 'D62DCD25DC68180758FD1C064ADC91AB70A78CB1', '8F39AC8ADD8CD0C0C2BF8924CE3B24F4B2760B8723BFD4205A49FB142490A355', 34);
    $hashes = $this->treeDao->getItemHashes(463,'uploadtree_a');
    assertThat($hashes,equalTo(array('md5'=>'C655F0AD3E4D3F90D8EE0541DD636E2E','sha1'=>'D62DCD25DC68180758FD1C064ADC91AB70A78CB1','sha256'=>'8F39AC8ADD8CD0C0C2BF8924CE3B24F4B2760B8723BFD4205A49FB142490A355')));
  }

  protected function getNestedTestFileStructure()
  {
    /*
     * example.zip
     *   |
     *   \- file
     *   \~ subExample.tar.gz
     *        |
     *        \- subEample.tar
     *             |
     *             \- innerFile
     *             \~ someFolder
     *                  |
     *                  \- someFileInFolder
     */
    return array(
        array(14, 13,   2, 8, 32768,     5,  6,  'someFileInFolder',  13,   ),
        array(13, 5,    2, 0, 536888320, 4,  7,  'someFolder',        5,    ),
        array(12, 11,   2, 7, 33188,     13, 14, 'innerFile',         11,   ),
        array(11, 10,   2, 0, 536888320, 12, 15, 'subExample',        9,    ),
        array(10, 9,    2, 0, 805323776, 11, 16, 'artifact.dir',      9,    ),
        array(9,  8,    2, 6, 536903680, 10, 17, 'subExample.tar',    7,    ),
        array(8,  7,    2, 0, 805323776, 9,  18, 'artifact.dir',      7,    ),
        array(7,  5,    2, 5, 536903680, 8,  19, 'subExample.tar.gz', 5,    ),
        array(6,  5,    2, 4, 32768,     20, 21, 'file',              5,    ),
        array(5,  4,    2, 0, 536888320, 3,  22, 'example',           2,    ),
        array(4,  2,    2, 0, 805323776, 2,  23, 'artifact.dir',      2,    ),
        array(3,  2,    2, 3, 268469248, 24, 25, 'artifact.meta',     2,    ),
        array(2,  NULL, 2, 2, 536904704, 1,  26, 'example.zip',       NULL, ),
    );
  }

  public function testGetFullPathWithNestedStructure()
  {
    $this->prepareUploadTree($this->getNestedTestFileStructure());
    assertThat($this->treeDao->getFullPath(2, "uploadtree", 0),      equalTo('example.zip'));
    assertThat($this->treeDao->getFullPath(2, "uploadtree", 0,true), equalTo('example.zip'));

    assertThat($this->treeDao->getFullPath(6, "uploadtree", 0),      equalTo('example.zip/example/file'));
    assertThat($this->treeDao->getFullPath(6, "uploadtree", 0,true), equalTo(            'example/file'));

    assertThat($this->treeDao->getFullPath(12, "uploadtree", 0),      equalTo('example.zip/example/subExample.tar.gz/subExample.tar/subExample/innerFile'));
    assertThat($this->treeDao->getFullPath(12, "uploadtree", 0,true), equalTo(            'example/subExample.tar.gz/subExample.tar/subExample/innerFile'));

    assertThat($this->treeDao->getFullPath(12, "uploadtree", 5),      equalTo('subExample.tar.gz/subExample.tar/subExample/innerFile'));
    assertThat($this->treeDao->getFullPath(12, "uploadtree", 5,true), equalTo(                                 'subExample/innerFile'));

    assertThat($this->treeDao->getFullPath(12, "uploadtree", 7),      equalTo('subExample.tar/subExample/innerFile'));
    assertThat($this->treeDao->getFullPath(12, "uploadtree", 7,true), equalTo(               'subExample/innerFile'));
  }
}
