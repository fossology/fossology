<?php
/*
 SPDX-FileCopyrightText: © 2025 ayxsh_shxrma <ayushmaan.sharma911@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Reuser;

// Use relative paths to ensure the test works in any FOSSology environment (Local or CI)
$autoloadPath = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/../../agent/ReuserAgent.php';

use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Data\Tree\ItemTreeBounds;

/**
 * @class ReuserAgentTest
 * @brief Unit test for ReuserAgent matching logic
 */
class ReuserAgentTest extends \PHPUnit\Framework\TestCase
{
  private $dbManager;
  private $uploadDao;
  private $copyrightDao;

  protected function setUp(): void
  {
    $this->dbManager = $this->getMockBuilder(DbManager::class)->disableOriginalConstructor()->getMock();
    $this->uploadDao = $this->getMockBuilder(UploadDao::class)->disableOriginalConstructor()->getMock();
    $this->copyrightDao = $this->getMockBuilder(CopyrightDao::class)->disableOriginalConstructor()->getMock();
  }

  /**
   * @test
   * Verify that reuseCopyrights correctly matches copyrights by hash using the optimized logic.
   */
  public function testReuseCopyrights()
  {
    $uploadId = 1;
    $reusedUploadId = 2;
    $agentId = 10;
    $tableName = "uploadtree";

    $this->uploadDao->method('getUploadtreeTableName')->willReturn($tableName);

    $allCopyrights = [
      'c1' => ['hash' => 'hash1', 'uploadtree_pk' => 101, 'content' => '© Meta'],
      'c2' => ['hash' => 'hash2', 'uploadtree_pk' => 102, 'content' => '© Google'],
    ];

    $reusedCopyrights = [
      ['hash' => 'hash1', 'is_enabled' => 't', 'contentedited' => '© Meta Edited'],
      ['hash' => 'hash2', 'is_enabled' => 'f', 'contentedited' => ''],
    ];

    $this->copyrightDao->method('getScannerEntries')->willReturn($allCopyrights);
    $this->copyrightDao->method('getAllEventEntriesForUpload')->willReturn($reusedCopyrights);
    $this->dbManager->method('booleanFromDb')->willReturnCallback(function($val) {
        return $val === 't';
    });

    $item1 = $this->createMock(ItemTreeBounds::class);
    $item2 = $this->createMock(ItemTreeBounds::class);
    $this->uploadDao->method('getItemTreeBounds')->willReturnMap([
        [101, $tableName, $item1],
        [102, $tableName, $item2],
    ]);

    $this->copyrightDao->expects($this->exactly(2))->method('updateTable');

    $mockReuserAgent = $this->getMockBuilder(ReuserAgent::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['getAgentId', 'heartbeat'])
        ->getMock();

    $reflection = new \ReflectionClass(ReuserAgent::class);
    foreach (['uploadDao', 'copyrightDao', 'dbManager', 'userId'] as $propName) {
        $prop = $reflection->getProperty($propName);
        $prop->setAccessible(true);
        if ($propName === 'userId') {
            $prop->setValue($mockReuserAgent, 1);
        } else {
            $prop->setValue($mockReuserAgent, $this->$propName);
        }
    }
    
    $mockReuserAgent->method('getAgentId')->willReturn($agentId);
    $mockReuserAgent->method('heartbeat')->willReturn(null);

    $method = $reflection->getMethod('reuseCopyrights');
    $method->setAccessible(true);
    $method->invoke($mockReuserAgent, $uploadId, $reusedUploadId);
    
    $this->assertTrue(true);
  }

  /**
   * @test
   * Verify duplicate hash handling
   */
  public function testReuseCopyrightsWithDuplicateHashes()
  {
    $uploadId = 1;
    $reusedUploadId = 2;
    $tableName = "uploadtree";

    $allCopyrights = [
      'c1' => ['hash' => 'shared', 'uploadtree_pk' => 101],
      'c2' => ['hash' => 'shared', 'uploadtree_pk' => 102],
    ];

    $reusedCopyrights = [
      ['hash' => 'shared', 'is_enabled' => 't', 'contentedited' => 'Edit 1'],
      ['hash' => 'shared', 'is_enabled' => 't', 'contentedited' => 'Edit 2'],
    ];

    $this->uploadDao->method('getUploadtreeTableName')->willReturn($tableName);
    $this->copyrightDao->method('getScannerEntries')->willReturn($allCopyrights);
    $this->copyrightDao->method('getAllEventEntriesForUpload')->willReturn($reusedCopyrights);
    $this->dbManager->method('booleanFromDb')->willReturn(true);

    $this->uploadDao->method('getItemTreeBounds')->willReturn($this->createMock(ItemTreeBounds::class));

    $mockReuserAgent = $this->getMockBuilder(ReuserAgent::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['getAgentId', 'heartbeat'])
        ->getMock();

    $reflection = new \ReflectionClass(ReuserAgent::class);
    foreach (['uploadDao', 'copyrightDao', 'dbManager', 'userId'] as $propName) {
        $prop = $reflection->getProperty($propName);
        $prop->setAccessible(true);
        if ($propName === 'userId') {
            $prop->setValue($mockReuserAgent, 1);
        } else {
            $prop->setValue($mockReuserAgent, $this->$propName);
        }
    }

    $mockReuserAgent->method('getAgentId')->willReturn(10);
    $mockReuserAgent->method('heartbeat')->willReturn(null);

    $this->copyrightDao->expects($this->exactly(2))->method('updateTable');

    $method = $reflection->getMethod('reuseCopyrights');
    $method->setAccessible(true);
    $method->invoke($mockReuserAgent, $uploadId, $reusedUploadId);

    $this->assertTrue(true);
  }
}
