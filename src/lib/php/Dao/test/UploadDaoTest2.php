<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

class UploadDaoTest2 extends \PHPUnit_Framework_TestCase
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
            'uploadtree_a'
        ));

//    $this->dbManager->queryOnce(
//
//    "    INSERT INTO uploadtree_a VALUES (3675, 3674, 32, 3299, 33188, 23, 24, 'N3');
//INSERT INTO uploadtree_a VALUES (3674, 3652, 32, 0, 536888320, 22, 37, 'N');
//INSERT INTO uploadtree_a VALUES (3673, 3652, 32, 3298, 33188, 38, 39, 'O');
//INSERT INTO uploadtree_a VALUES (3670, 3669, 32, 3295, 33188, 7, 8, 'K_NoLic');
//INSERT INTO uploadtree_a VALUES (3671, 3669, 32, 3296, 33188, 9, 10, 'J');
//INSERT INTO uploadtree_a VALUES (3669, 3652, 32, 0, 536888320, 4, 11, 'H');
//INSERT INTO uploadtree_a VALUES (3668, 3652, 32, 3294, 33188, 42, 43, 'C');
//INSERT INTO uploadtree_a VALUES (3663, 3662, 32, 3291, 33188, 46, 47, 'L3a_NoLic');
//INSERT INTO uploadtree_a VALUES (3662, 3661, 32, 0, 536888320, 45, 48, 'L3');
//INSERT INTO uploadtree_a VALUES (3667, 3666, 32, 3293, 33188, 50, 51, 'L1a_NoLic');
//INSERT INTO uploadtree_a VALUES (3666, 3661, 32, 0, 536888320, 49, 52, 'L1');
//INSERT INTO uploadtree_a VALUES (3665, 3664, 32, 3292, 33188, 54, 55, 'L2a');
//INSERT INTO uploadtree_a VALUES (3664, 3661, 32, 0, 536888320, 53, 56, 'L2');
//INSERT INTO uploadtree_a VALUES (3661, 3652, 32, 0, 536888320, 44, 57, 'L');
//INSERT INTO uploadtree_a VALUES (3658, 3657, 32, 3288, 33188, 60, 61, 'P2a');
//INSERT INTO uploadtree_a VALUES (3657, 3656, 32, 0, 536888320, 59, 62, 'P2');
//INSERT INTO uploadtree_a VALUES (3659, 3656, 32, 3289, 33188, 63, 64, 'P1_NoLic');
//INSERT INTO uploadtree_a VALUES (3660, 3656, 32, 3290, 33188, 65, 66, 'P3');
//INSERT INTO uploadtree_a VALUES (3656, 3652, 32, 0, 536888320, 58, 67, 'P');
//INSERT INTO uploadtree_a VALUES (3655, 3654, 32, 0, 536888320, 69, 70, 'M1');
//INSERT INTO uploadtree_a VALUES (3654, 3652, 32, 0, 536888320, 68, 71, 'M');
//INSERT INTO uploadtree_a VALUES (3653, 3652, 32, 3287, 33188, 72, 73, 'A');
//INSERT INTO uploadtree_a VALUES (3652, 3651, 32, 0, 536888320, 3, 74, 'uploadDaoTest');
//INSERT INTO uploadtree_a VALUES (3651, 3650, 32, 0, 805323776, 2, 75, 'artifact.dir');
//INSERT INTO uploadtree_a VALUES (3650, NULL, 32, 3286, 536904704, 1, 76, 'uploadDaoTest.tar');
//INSERT INTO uploadtree_a VALUES (3672, 3669, 32, 3297, 33188, 5, 6, 'I_NoLic');
//INSERT INTO uploadtree_a VALUES (3686, 3652, 32, 3306, 33188, 12, 13, 'R');
//INSERT INTO uploadtree_a VALUES (3683, 3682, 32, 3303, 33188, 15, 16, 'E');
//INSERT INTO uploadtree_a VALUES (3685, 3682, 32, 3305, 33188, 17, 18, 'G');
//INSERT INTO uploadtree_a VALUES (3684, 3682, 32, 3304, 33188, 19, 20, 'F_NoLic');
//INSERT INTO uploadtree_a VALUES (3682, 3652, 32, 0, 536888320, 14, 21, 'D');
//INSERT INTO uploadtree_a VALUES (3677, 3674, 32, 3300, 33188, 25, 26, 'N5');
//INSERT INTO uploadtree_a VALUES (3676, 3674, 32, 3293, 33188, 27, 28, 'N1');
//INSERT INTO uploadtree_a VALUES (3679, 3678, 32, 3301, 33188, 30, 31, 'N2a_NoLic');
//INSERT INTO uploadtree_a VALUES (3678, 3674, 32, 0, 536888320, 29, 32, 'N2');
//INSERT INTO uploadtree_a VALUES (3681, 3680, 32, 3302, 33188, 34, 35, 'N4a');
//INSERT INTO uploadtree_a VALUES (3680, 3674, 32, 0, 536888320, 33, 36, 'N4');
//INSERT INTO uploadtree_a VALUES (3687, 3652, 32, 3307, 33188, 40, 41, 'B_NoLic'); ", 'insert.uploadtree_a'
//    );

    $this->dbManager->queryOnce(
        "    INSERT INTO uploadtree_a VALUES (3675, 3674, 32, 3299, 33188, 23, 24, 'N3');
INSERT INTO uploadtree_a VALUES (3674, 3652, 32, 0, 536888320, 22, 37, 'N');
INSERT INTO uploadtree_a VALUES (3673, 3652, 32, 3298, 33188, 38, 39, 'O');
INSERT INTO uploadtree_a VALUES (3671, 3669, 32, 3296, 33188, 9, 10, 'J');
INSERT INTO uploadtree_a VALUES (3669, 3652, 32, 0, 536888320, 4, 11, 'H');
INSERT INTO uploadtree_a VALUES (3668, 3652, 32, 3294, 33188, 42, 43, 'C');
INSERT INTO uploadtree_a VALUES (3662, 3661, 32, 0, 536888320, 45, 48, 'L3');
INSERT INTO uploadtree_a VALUES (3666, 3661, 32, 0, 536888320, 49, 52, 'L1');
INSERT INTO uploadtree_a VALUES (3665, 3664, 32, 3292, 33188, 54, 55, 'L2a');
INSERT INTO uploadtree_a VALUES (3664, 3661, 32, 0, 536888320, 53, 56, 'L2');
INSERT INTO uploadtree_a VALUES (3661, 3652, 32, 0, 536888320, 44, 57, 'L');
INSERT INTO uploadtree_a VALUES (3658, 3657, 32, 3288, 33188, 60, 61, 'P2a');
INSERT INTO uploadtree_a VALUES (3657, 3656, 32, 0, 536888320, 59, 62, 'P2');
INSERT INTO uploadtree_a VALUES (3660, 3656, 32, 3290, 33188, 65, 66, 'P3');
INSERT INTO uploadtree_a VALUES (3656, 3652, 32, 0, 536888320, 58, 67, 'P');
INSERT INTO uploadtree_a VALUES (3655, 3654, 32, 0, 536888320, 69, 70, 'M1');
INSERT INTO uploadtree_a VALUES (3654, 3652, 32, 0, 536888320, 68, 71, 'M');
INSERT INTO uploadtree_a VALUES (3653, 3652, 32, 3287, 33188, 72, 73, 'A');
INSERT INTO uploadtree_a VALUES (3652, 3651, 32, 0, 536888320, 3, 74, 'uploadDaoTest');
INSERT INTO uploadtree_a VALUES (3651, 3650, 32, 0, 805323776, 2, 75, 'artifact.dir');
INSERT INTO uploadtree_a VALUES (3650, NULL, 32, 3286, 536904704, 1, 76, 'uploadDaoTest.tar');
INSERT INTO uploadtree_a VALUES (3686, 3652, 32, 3306, 33188, 12, 13, 'R');
INSERT INTO uploadtree_a VALUES (3683, 3682, 32, 3303, 33188, 15, 16, 'E');
INSERT INTO uploadtree_a VALUES (3685, 3682, 32, 3305, 33188, 17, 18, 'G');
INSERT INTO uploadtree_a VALUES (3682, 3652, 32, 0, 536888320, 14, 21, 'D');
INSERT INTO uploadtree_a VALUES (3677, 3674, 32, 3300, 33188, 25, 26, 'N5');
INSERT INTO uploadtree_a VALUES (3676, 3674, 32, 3293, 33188, 27, 28, 'N1');
INSERT INTO uploadtree_a VALUES (3678, 3674, 32, 0, 536888320, 29, 32, 'N2');
INSERT INTO uploadtree_a VALUES (3681, 3680, 32, 3302, 33188, 34, 35, 'N4a');
INSERT INTO uploadtree_a VALUES (3680, 3674, 32, 0, 536888320, 33, 36, 'N4');
 ", 'insert.uploadtree_a'
    );


    $this->uploadDao = new UploadDao($this->dbManager);
  }

  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
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




}