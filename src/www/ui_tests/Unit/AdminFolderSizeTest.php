<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AdminFolderSizeTest extends \PHPUnit\Framework\TestCase
{
  private $testDb;
  private $dbManager;
  private $dashboard;
  private $uploadDao;

  protected function setUp(): void
  {
    $this->testDb = new TestPgDb('foldersizedashboard');
    $this->dbManager = &$this->testDb->getDbManager();
    $this->testDb->createPlainTables([
      'foldercontents', 'pfile', 'upload', 'upload_clearing'
    ]);
    $this->testDb->createConstraints([
      'foldercontents_pkey', 'pfile_pkey', 'upload_pkey_idx',
      'upload_clearing_pkey'
    ]);

    if (!class_exists('size_dashboard', false)) {
      $GLOBALS['SysConf'] = ['auth' => ['GroupId' => 1]];
      $GLOBALS['Plugins'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };

      $srcDir = dirname(dirname(dirname(__DIR__)));
      require_once($srcDir . '/lib/php/Plugin/FO_Plugin.php');
      require_once($srcDir . '/lib/php/common-plugin.php');
      require_once($srcDir . '/lib/php/common-ui.php');
      require_once($srcDir . '/www/ui/admin-folder-size.php');
    }

    $this->dashboard = (new \ReflectionClass('size_dashboard'))
      ->newInstanceWithoutConstructor();
    $this->setDashboardProperty('dbManager', $this->dbManager);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->uploadDao->shouldReceive('getClearingDurationsBatch')
      ->once()
      ->andReturn([]);
    $this->setDashboardProperty('uploadDao', $this->uploadDao);
  }

  protected function tearDown(): void
  {
    M::close();
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }

  public function testFolderSizeCountsEachUploadOnceAcrossJoinDuplicates()
  {
    $folderId = 900;
    $this->dbManager->insertTableRow('pfile', [
      'pfile_pk' => 11, 'pfile_size' => 100
    ]);
    $this->dbManager->insertTableRow('pfile', [
      'pfile_pk' => 12, 'pfile_size' => 40
    ]);
    $this->dbManager->insertTableRow('pfile', [
      'pfile_pk' => 13, 'pfile_size' => 50
    ]);

    $uploads = [
      [1, 'upload-a', 11],
      [2, 'upload-b', 11],
      [3, 'upload-c', 12],
      [4, 'upload-d', 13]
    ];
    foreach ($uploads as [$uploadId, $name, $pfileId]) {
      $this->dbManager->insertTableRow('upload', [
        'upload_pk' => $uploadId,
        'upload_filename' => $name,
        'pfile_fk' => $pfileId
      ]);
    }

    $folderContents = [
      [1, 1], [2, 2], [3, 3], [4, 3], [5, 4]
    ];
    foreach ($folderContents as [$rowId, $uploadId]) {
      $this->dbManager->insertTableRow('foldercontents', [
        'foldercontents_pk' => $rowId,
        'parent_fk' => $folderId,
        'foldercontents_mode' => 2,
        'child_id' => $uploadId
      ]);
    }

    $clearingRows = [[1, 10], [1, 20], [2, 10], [3, 10], [3, 20]];
    foreach ($clearingRows as [$uploadId, $groupId]) {
      $this->dbManager->insertTableRow('upload_clearing', [
        'upload_fk' => $uploadId,
        'group_fk' => $groupId
      ]);
    }

    [$uploadRows, $folderSize] =
      $this->dashboard->getFolderAndUploadSize($folderId);

    // A and B share a 100-byte pfile but are separate uploads. C has two
    // folder rows and two clearing rows, so the old join sums it four times.
    // The old total is 460 bytes; D has no clearing row and stays excluded.
    $this->assertSame(HumanSize(240), $folderSize);
    preg_match_all("~<tr><td align='left'>([0-9]+)</td>~", $uploadRows, $matches);
    $this->assertSame(['1', '2', '3'], $matches[1]);
  }

  private function setDashboardProperty($propertyName, $value): void
  {
    $property = new \ReflectionProperty($this->dashboard, $propertyName);
    $property->setAccessible(true);
    $property->setValue($this->dashboard, $value);
  }
}
