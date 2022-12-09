<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Test;

use Fossology\Lib\Data\Types;

class EnumMapTestBase extends \PHPUnit\Framework\TestCase
{

  /** @var Types */
  private $types;

  /**
   * @param Types $types
   */
  protected function setTypes($types)
  {
    $this->types = $types;
  }

  /**
   * @param int $type
   * @param string $expectedTypeName
   * @throws \Exception
   */
  protected function checkMapping($type, $expectedTypeName)
  {
    $typeName = $this->types->getTypeName($type);

    assertThat($typeName, is($expectedTypeName));
    assertThat($this->types->getTypeByName($typeName), is($type));
  }
}
