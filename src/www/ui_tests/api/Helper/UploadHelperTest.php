<?php
/*
 SPDX-FileCopyrightText: © 2026 Shubham Padkonde

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Test\Helper;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\UploadHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Mockery as M;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @brief Test copyright-only upload license listings without a database.
 * @backupGlobals disabled
 */
class UploadHelperTest extends \PHPUnit\Framework\TestCase
{
  private $containerBackup;

  protected function setUp(): void
  {
    $this->containerBackup = $GLOBALS['container'] ?? null;
  }

  protected function tearDown(): void
  {
    $GLOBALS['container'] = $this->containerBackup;
    M::close();
  }

  /**
   * @dataProvider copyrightPages
   */
  public function testCopyrightOnlyPagination($apiVersion, $page, $limit,
    $expectedPaths, $rows = null)
  {
    if ($rows === null) {
      $rows = [
        ['filePath' => 'first.c', 'content' => 'Copyright First'],
        ['filePath' => 'second.c', 'content' => 'Copyright Second'],
        ['filePath' => 'first.c', 'content' => 'Copyright Third'],
        ['filePath' => '123', 'content' => 'Copyright Numeric Path'],
        'warn' => 'ignored export warning'
      ];
      $total = 3;
    } else {
      $total = 0;
    }
    $uploadDao = M::mock(UploadDao::class);
    $uploadDao->shouldReceive('getUploadtreeTableName')->with(1)
      ->andReturn('uploadtree_a');
    $uploadDao->shouldReceive('getParentItemBounds')->with(1, 'uploadtree_a')
      ->andReturn(new ItemTreeBounds(2, 'uploadtree_a', 1, 1, 10));
    $exportList = M::mock();
    $exportList->shouldReceive('getCopyrights')
      ->with(1, 2, 'uploadtree_a', -1, '')->andReturn($rows);
    $restHelper = M::mock(RestHelper::class);
    $restHelper->shouldReceive('getUploadDao')->andReturn($uploadDao);
    $restHelper->shouldReceive('getGroupId')->andReturn(1);
    $restHelper->shouldReceive('getPlugin')->with('export-list')
      ->andReturn($exportList);
    $agentDao = M::mock(AgentDao::class);
    $agentDao->shouldReceive('arsTableExists')->with('nomos')->andReturn(false);
    $container = new ContainerBuilder();
    $container->set('helper.restHelper', $restHelper);
    $container->set('dao.agent', $agentDao);
    $container->set('dao.clearing', M::mock(ClearingDao::class));
    $GLOBALS['container'] = $container;

    // Upload-page construction is unrelated to reading an existing upload.
    $helper = (new \ReflectionClass(UploadHelper::class))
      ->newInstanceWithoutConstructor();
    list($results, $count) = $helper->getUploadLicenseList(1, ['nomos'],
      false, false, true, $page, $limit, $apiVersion);

    $this->assertSame($total, $count);
    $this->assertSame($expectedPaths, array_column($results, 'filePath'));
    $copyrights = [
      'first.c' => ['Copyright First', 'Copyright Third'],
      'second.c' => ['Copyright Second'],
      '123' => ['Copyright Numeric Path']
    ];
    $clearingKey = $apiVersion == ApiVersion::V2
      ? 'clearingStatus' : 'clearing_status';
    foreach ($results as $result) {
      $this->assertSame([
        'filePath' => $result['filePath'],
        'findings' => [
          'scanner' => null,
          'conclusion' => null,
          'copyright' => $copyrights[$result['filePath']]
        ],
        $clearingKey => null
      ], $result);
    }
  }

  public function copyrightPages()
  {
    $cases = [];
    foreach ([ApiVersion::V1, ApiVersion::V2] as $version) {
      $cases[] = [$version, 0, 50, ['first.c', 'second.c', '123']];
      $cases[] = [$version, 0, 1, ['first.c']];
      $cases[] = [$version, 1, 1, ['second.c']];
      $cases[] = [$version, 2, 1, ['123']];
      $cases[] = [$version, 3, 1, []];
      $cases[] = [$version, 0, 50, [], []];
      $cases[] = [$version, 0, 50, [], ['warn' => 'no copyrights']];
    }
    return $cases;
  }
}
