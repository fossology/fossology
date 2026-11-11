<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for UploadHelper
 */

namespace Fossology\UI\Api\Test\Helper;

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\UploadHelper;
use Mockery as M;
use Psr\Container\ContainerInterface;

/**
 * @class UploadHelperTest
 * @brief Test cases for UploadHelper
 */
class UploadHelperTest extends \PHPUnit\Framework\TestCase
{
  private $originalContainer;

  private $hadContainer;

  private $restHelper;

  private $uploadDao;

  private $exportList;

  private $uploadHelper;

  private $uploadId = 10;

  private $uploadTreeTableName = 'uploadtree_a';

  private $parentItemId = 42;

  protected function setUp() : void
  {
    $this->hadContainer = array_key_exists('container', $GLOBALS);
    if ($this->hadContainer) {
      $this->originalContainer = $GLOBALS['container'];
    }

    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->exportList = M::mock();
    $container = M::mock(ContainerInterface::class);
    $GLOBALS['container'] = $container;

    $container->shouldReceive('get')
      ->with('helper.restHelper')->once()->andReturn($this->restHelper);
    $this->restHelper->shouldReceive('getUploadDao')
      ->once()->andReturn($this->uploadDao);
    $this->uploadDao->shouldReceive('getUploadtreeTableName')
      ->with($this->uploadId)->once()->andReturn($this->uploadTreeTableName);
    $this->uploadDao->shouldReceive('getParentItemBounds')
      ->with($this->uploadId, $this->uploadTreeTableName)->once()
      ->andReturn(new ItemTreeBounds($this->parentItemId,
        $this->uploadTreeTableName, $this->uploadId, 1, 2));
    $this->restHelper->shouldReceive('getPlugin')
      ->with('export-list')->once()->andReturn($this->exportList);

    $this->uploadHelper = (new \ReflectionClass(UploadHelper::class))
      ->newInstanceWithoutConstructor();
  }

  protected function tearDown() : void
  {
    if ($this->hadContainer) {
      $GLOBALS['container'] = $this->originalContainer;
    } else {
      unset($GLOBALS['container']);
    }
    M::close();
  }

  private function getCopyrightList(array $copyrightRows)
  {
    $this->exportList->shouldReceive('getCopyrights')
      ->with($this->uploadId, $this->parentItemId,
        $this->uploadTreeTableName, -1, '')
      ->once()->andReturn($copyrightRows);

    return $this->uploadHelper->getUploadCopyrightList($this->uploadId);
  }

  /** @test */
  public function testGetUploadCopyrightListWithEmptyResults()
  {
    $this->assertSame([], $this->getCopyrightList([]));
  }

  /** @test */
  public function testGetUploadCopyrightListWithOnlyWarning()
  {
    $this->assertSame([], $this->getCopyrightList([
      'warn' => 'Only the first lines are displayed.'
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListKeepsNullAndEmptyContentSeparate()
  {
    $this->assertSame([
      [
        'copyright' => null,
        'filePath' => ['null.c', 'empty.c', 'null.c']
      ],
      [
        'copyright' => '',
        'filePath' => ['null.c', 'empty.c', 'null.c']
      ]
    ], $this->getCopyrightList([
      ['content' => null, 'filePath' => 'null.c'],
      ['content' => '', 'filePath' => 'empty.c'],
      ['content' => null, 'filePath' => 'null.c']
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListGroupsRepeatedIdenticalContents()
  {
    $this->assertSame([
      [
        'copyright' => 'Copyright 2024 Example Corp.',
        'filePath' => ['first.c', 'second.c', 'first.c']
      ]
    ], $this->getCopyrightList([
      ['content' => 'Copyright 2024 Example Corp.', 'filePath' => 'first.c'],
      ['content' => 'Copyright 2024 Example Corp.', 'filePath' => 'second.c'],
      ['content' => 'Copyright 2024 Example Corp.', 'filePath' => 'first.c']
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListCaseVariantsShareMatchingPaths()
  {
    $this->assertSame([
      [
        'copyright' => 'Copyright (c) Example Corp.',
        'filePath' => ['upper.c', 'lower.c', 'again.c']
      ],
      [
        'copyright' => 'copyright (c) example corp.',
        'filePath' => ['upper.c', 'lower.c', 'again.c']
      ]
    ], $this->getCopyrightList([
      ['content' => 'Copyright (c) Example Corp.', 'filePath' => 'upper.c'],
      ['content' => 'copyright (c) example corp.', 'filePath' => 'lower.c'],
      ['content' => 'Copyright (c) Example Corp.', 'filePath' => 'again.c']
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListPreservesFirstOccurrenceOrderAndText()
  {
    $this->assertSame([
      [
        'copyright' => 'Copyright (c) Zulu Corp.',
        'filePath' => ['z-first.c', 'z-last.c']
      ],
      [
        'copyright' => 'Copyright (c) Alpha Corp.',
        'filePath' => ['a.c']
      ]
    ], $this->getCopyrightList([
      ['content' => 'Copyright (c) Zulu Corp.', 'filePath' => 'z-first.c'],
      ['content' => 'Copyright (c) Alpha Corp.', 'filePath' => 'a.c'],
      ['content' => 'Copyright (c) Zulu Corp.', 'filePath' => 'z-last.c']
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListPreservesDuplicateFilePaths()
  {
    $this->assertSame([
      [
        'copyright' => 'Copyright (c) Repeated Path Corp.',
        'filePath' => ['same.c', 'same.c']
      ]
    ], $this->getCopyrightList([
      ['content' => 'Copyright (c) Repeated Path Corp.', 'filePath' => 'same.c'],
      ['content' => 'Copyright (c) Repeated Path Corp.', 'filePath' => 'same.c']
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListPreservesNonAsciiContent()
  {
    $this->assertSame([
      [
        'copyright' => 'Copyright © 2024 Société Exemple',
        'filePath' => ['unicode.c', 'other.c']
      ],
      [
        'copyright' => 'Copyright © 2024 SociÉtÉ Exemple',
        'filePath' => ['case-variant.c']
      ]
    ], $this->getCopyrightList([
      ['content' => 'Copyright © 2024 Société Exemple', 'filePath' => 'unicode.c'],
      ['content' => 'Copyright © 2024 Société Exemple', 'filePath' => 'other.c'],
      ['content' => 'Copyright © 2024 SociÉtÉ Exemple', 'filePath' => 'case-variant.c']
    ]));
  }

  /** @test */
  public function testGetUploadCopyrightListUsesStrictEqualityForNumericLookingContent()
  {
    $this->assertSame([
      [
        'copyright' => '2024',
        'filePath' => ['year-only.txt']
      ],
      [
        'copyright' => '02024',
        'filePath' => ['zero-padded-year.txt']
      ],
      [
        'copyright' => 'Copyright 2024 Example Corp.',
        'filePath' => ['statement.txt']
      ]
    ], $this->getCopyrightList([
      ['content' => '2024', 'filePath' => 'year-only.txt'],
      ['content' => '02024', 'filePath' => 'zero-padded-year.txt'],
      ['content' => 'Copyright 2024 Example Corp.', 'filePath' => 'statement.txt']
    ]));
  }
}
