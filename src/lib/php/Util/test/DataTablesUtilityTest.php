<?php
/*
 SPDX-FileCopyrightText: ©FossologyContributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use PHPUnit\Framework\TestCase;

class DataTablesUtilityTest extends TestCase
{
  public function testSortingIncludesOnlySortableColumns()
  {
    $utility = new DataTablesUtility();

    $columnNamesInDatabase = [
      'col_0',
      'col_1',
      'UNUSED',
      'UNUSED',
      'col_4',
      'col_5',
      'col_6'
    ];

    // Simulated DataTables server-side request
    $inputArray = [
      'iSortingCols' => 3,

      'iSortCol_0' => 5,
      'iSortCol_1' => 0,
      'iSortCol_2' => 6,

      'sSortDir_0' => 'desc',
      'sSortDir_1' => 'asc',
      'sSortDir_2' => 'desc',

      'bSortable_0' => 'true',
      'bSortable_1' => 'false',
      'bSortable_2' => 'false',
      'bSortable_3' => 'false',
      'bSortable_4' => 'false',
      'bSortable_5' => 'true',
      'bSortable_6' => 'true',
    ];

    $orderBy = $utility->getSortingString($inputArray, $columnNamesInDatabase);

    $this->assertEquals(
      'ORDER BY col_5 desc, col_0 asc, col_6 desc',
      $orderBy
    );
  }
}
