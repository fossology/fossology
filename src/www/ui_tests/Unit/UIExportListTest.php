<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
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

  /**
   * @dataProvider copyrightExclusions
   */
  public function testCopyrightExclusion($exclude, $expectedIds, $limit = -1)
  {
    $agentDao = M::mock(AgentDao::class);
    $agentDao->shouldReceive('arsTableExists')->with('copyright')->andReturn(true);
    $agentDao->shouldReceive('arsTableExists')->with('reso')->andReturn(false);
    $agentDao->shouldReceive('getSuccessfulAgentEntries')->with('copyright', 1)
      ->andReturn([['agent_id' => 5, 'agent_rev' => 'test']]);
    $agentDao->shouldReceive('getCurrentAgentRef')->with('copyright')
      ->andReturn(new AgentRef(5, 'copyright', 'test'));
    $GLOBALS['container'] = new class($agentDao) {
      private $agentDao;

      public function __construct($agentDao)
      {
        $this->agentDao = $agentDao;
      }

      public function get($name)
      {
        return $this->agentDao;
      }
    };

    $uploadDao = M::mock(UploadDao::class);
    $uploadDao->shouldReceive('getItemTreeBounds')->with(30, 'uploadtree')->once()
      ->andReturn(new ItemTreeBounds(30, 'uploadtree', 1, 1, 20));
    Reflectory::setObjectsProperty($this->exportList, 'uploadDao', $uploadDao);

    $paths = [
      41 => 'archive/vendor/library.c',
      42 => 'archive/src/main.c',
      43 => 'archive/src/vendor-helper.c',
      44 => 'archive/vendor/reviewed.c',
      45 => 'archive/src/reviewed.c',
    ];
    $scannerEntries = [];
    $editedEntries = [];
    foreach ($paths as $id => $path) {
      if ($id <= 43) {
        $scannerEntries[] = ['uploadtree_pk' => $id, 'content' => "Copyright $id"];
      } else {
        $editedEntries[] = ['uploadtree_pk' => $id, 'textfinding' => "Copyright $id"];
      }
    }
    $copyrightDao = M::mock(CopyrightDao::class);
    $copyrightDao->shouldReceive('getScannerEntries')->once()
      ->with('copyright', 'uploadtree', 1, null,
        'UT.lft BETWEEN 1 AND 20 AND C.agent_fk IN (5)')
      ->andReturn($scannerEntries);
    $copyrightDao->shouldReceive('getEditedEntries')->once()
      ->with('copyright_decision', 'uploadtree', 1, [], 'UT.lft BETWEEN 1 AND 20')
      ->andReturn($editedEntries);
    Reflectory::setObjectsProperty($this->exportList, 'copyrightDao', $copyrightDao);

    $treeDao = M::mock(TreeDao::class);
    foreach ($paths as $id => $path) {
      $treeDao->shouldReceive('getFullPath')->with($id, 'uploadtree')
        ->andReturn($path);
    }
    Reflectory::setObjectsProperty($this->exportList, 'treeDao', $treeDao);

    $expected = [];
    foreach ($expectedIds as $id) {
      $expected[] = ['content' => "Copyright $id", 'filePath' => $paths[$id]];
    }
    $this->assertSame($expected,
      $this->exportList->getCopyrights(1, 30, 'uploadtree', $limit, $exclude));
  }

  public static function copyrightExclusions()
  {
    return [
      'directory and filename' => ['vendor', [42, 45]],
      'path segment' => ['vendor/', [42, 43, 45]],
      'filename' => ['library.c', [42, 43, 44, 45]],
      'edited findings' => ['reviewed.c', [41, 42, 43]],
      'no exclusion' => ['', [41, 42, 43, 44, 45]],
      'no match' => ['missing', [41, 42, 43, 44, 45]],
      'exclude before applying limit' => ['vendor', [42, 45], 2],
    ];
  }

  /**
   * @dataProvider literalCopyrightExclusions
   */
  public function testCopyrightExclusionIsLiteral($exclude, $excludedPath)
  {
    $treeDao = M::mock(TreeDao::class);
    $treeDao->shouldReceive('getFullPath')->with(41, 'uploadtree')->once()
      ->andReturn($excludedPath);
    $treeDao->shouldReceive('getFullPath')->with(42, 'uploadtree')->once()
      ->andReturn('archive/src/main.c');
    Reflectory::setObjectsProperty($this->exportList, 'treeDao', $treeDao);

    $lines = [];
    $paths = [];
    Reflectory::invokeObjectsMethodnameWith($this->exportList,
      'updateCopyrightList', [
        &$lines, &$paths,
        [
          ['uploadtree_pk' => 41, 'content' => 'Excluded'],
          ['uploadtree_pk' => 42, 'content' => 'Kept'],
        ],
        -1, 'uploadtree', 'content', $exclude,
      ]);

    $this->assertSame(['archive/src/main.c' => [
      ['content' => 'Kept', 'filePath' => 'archive/src/main.c'],
    ]], $lines);
    $this->assertSame([
      41 => $excludedPath,
      42 => 'archive/src/main.c',
    ], $paths);
  }

  public static function literalCopyrightExclusions()
  {
    return [
      'zero' => ['0', 'archive/vendor0/library.c'],
      'apostrophe' => ["vendor's", "archive/vendor's/library.c"],
      'percent' => ['%', 'archive/vendor%/library.c'],
      'underscore' => ['_', 'archive/vendor_/library.c'],
    ];
  }

  /**
   * @dataProvider repeatedCopyrightExclusions
   */
  public function testCopyrightPathsAreReusedAcrossFindings($exclude)
  {
    $filePath = 'archive/vendor/library.c';
    $treeDao = M::mock(TreeDao::class);
    $treeDao->shouldReceive('getFullPath')->with(41, 'uploadtree')->once()
      ->andReturn($filePath);
    Reflectory::setObjectsProperty($this->exportList, 'treeDao', $treeDao);

    $lines = [];
    $paths = [];
    Reflectory::invokeObjectsMethodnameWith($this->exportList,
      'updateCopyrightList', [
        &$lines, &$paths,
        [
          ['uploadtree_pk' => 41, 'content' => 'First finding'],
          ['uploadtree_pk' => 41, 'content' => 'Second finding'],
        ],
        -1, 'uploadtree', 'content', $exclude,
      ]);
    Reflectory::invokeObjectsMethodnameWith($this->exportList,
      'updateCopyrightList', [
        &$lines, &$paths,
        [['uploadtree_pk' => 41, 'textfinding' => 'Edited finding']],
        -1, 'uploadtree', 'textfinding', $exclude,
      ]);

    $expected = $exclude === '' ? [$filePath => [
      ['content' => 'First finding', 'filePath' => $filePath],
      ['content' => 'Second finding', 'filePath' => $filePath],
      ['content' => 'Edited finding', 'filePath' => $filePath],
    ]] : [];
    $this->assertSame($expected, $lines);
    $this->assertSame([41 => $filePath], $paths);
  }

  public static function repeatedCopyrightExclusions()
  {
    return [
      'included file' => [''],
      'excluded file' => ['vendor/'],
    ];
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
