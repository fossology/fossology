<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once(dirname(__FILE__) . '/../common-dir.php');
require_once(dirname(__FILE__) . '/../common-multicompare.php');

class test_common_multicompare extends \PHPUnit\Framework\TestCase
{
  public function testNormalizesSoleDirectoryChild(): void
  {
    $children = [[
      'uploadtree_pk' => 42,
      'ufile_mode' => (1 << 18) | (1 << 29),
    ]];

    $this->assertSame(42, NormalizeMultiCompareRoot(10, $children));
  }

  public function testKeepsRootWithMultipleChildren(): void
  {
    $children = [
      ['uploadtree_pk' => 42, 'ufile_mode' => (1 << 18) | (1 << 29)],
      ['uploadtree_pk' => 43, 'ufile_mode' => 0100644],
    ];

    $this->assertSame(10, NormalizeMultiCompareRoot(10, $children));
  }

  public function testKeepsRootWithSoleFileChild(): void
  {
    $children = [[
      'uploadtree_pk' => 42,
      'ufile_mode' => 0100644,
    ]];

    $this->assertSame(10, NormalizeMultiCompareRoot(10, $children));
  }

  public function testKeepsRootWithoutChildren(): void
  {
    $this->assertSame(10, NormalizeMultiCompareRoot(10, []));
  }
}
