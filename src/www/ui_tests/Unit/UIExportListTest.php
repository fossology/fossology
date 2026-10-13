<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class UIExportListTest extends \PHPUnit\Framework\TestCase
{
  private $exportList;

  protected function setUp(): void
  {
    if (!class_exists('UIExportList', false)) {
      $GLOBALS['SysConf'] = ['auth' => ['GroupId' => 1]];
      $GLOBALS['Plugins'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };

      require_once(__DIR__ . '/../../ui/ui-export-list.php');
    }

    $GLOBALS['SysConf']['auth']['GroupId'] = 1;
    $this->exportList =
      (new \ReflectionClass('UIExportList'))->newInstanceWithoutConstructor();
  }

  protected function tearDown(): void
  {
    M::close();
  }

  public function testNestedFolderFilterRemovesOnlyTheLicensedFile()
  {
    $bounds = new ItemTreeBounds(30, 'uploadtree', 1, 10, 20);

    $treeDao = M::mock(TreeDao::class);
    $treeDao->shouldReceive('getFullPath')
      ->with(41, 'uploadtree')->once()
      ->andReturn('archive/nested/ModernDbManager.php');
    $treeDao->shouldReceive('getFullPath')
      ->with(42, 'uploadtree')->once()
      ->andReturn('archive/nested/DbManager.php');
    Reflectory::setObjectsProperty($this->exportList, 'treeDao', $treeDao);

    $clearingDao = M::mock(ClearingDao::class);
    $clearingDao->shouldReceive('getFileClearingsFolder')->once()->andReturn([]);
    Reflectory::setObjectsProperty($this->exportList, 'clearingDao', $clearingDao);
    Reflectory::setObjectsProperty(
      $this->exportList,
      'clearingFilter',
      new ClearingDecisionFilter()
    );

    $licenseDao = M::mock(LicenseDao::class);
    $licenseDao->shouldReceive('getLicensesPerFileNameForAgentId')
      ->once()
      ->withArgs(function ($actualBounds, $agents, $includeSubfolders, $exclude,
        $ignore, $decisions, $includeTreeId) use ($bounds) {
        return $actualBounds === $bounds
          && $agents === []
          && $includeSubfolders === true
          && $exclude === ''
          && $ignore === true
          && $decisions === []
          && $includeTreeId === true;
      })
      ->andReturn([
        // The DAO path is relative to the selected nested folder.
        'DbManager.php' => [
          'scanResults' => ['MIT'],
          'uploadtree_pk' => [42],
        ],
      ]);
    Reflectory::setObjectsProperty($this->exportList, 'licenseDao', $licenseDao);

    $lines = [];
    $uploadtreePkToFilePath = [];
    // Keep the prefix-matching sibling first, as the old substring search did.
    $copyrights = [
      ['uploadtree_pk' => 41, 'content' => 'Modern file copyright'],
      ['uploadtree_pk' => 42, 'content' => 'Licensed file copyright'],
    ];

    Reflectory::invokeObjectsMethodnameWith(
      $this->exportList,
      'updateCopyrightList',
      [
        &$lines,
        &$uploadtreePkToFilePath,
        $copyrights,
        -1,
        'uploadtree',
        'content',
      ]
    );

    Reflectory::invokeObjectsMethodnameWith(
      $this->exportList,
      'removeCopyrightWithLicense',
      [
        &$lines,
        $uploadtreePkToFilePath,
        $bounds,
        [],
        '',
      ]
    );

    $this->assertArrayNotHasKey('archive/nested/DbManager.php', $lines);
    $this->assertArrayHasKey('archive/nested/ModernDbManager.php', $lines);
    $this->assertSame(
      'Modern file copyright',
      $lines['archive/nested/ModernDbManager.php'][0]['content']
    );
  }
}
