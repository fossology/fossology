<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;


use Fossology\Lib\Test\EnumMapTestBase;

class ClearingEventTypesTest extends EnumMapTestBase
{

  protected function setUp() : void
  {
    $this->setTypes(new ClearingEventTypes());
  }

  public function testTypeUser()
  {
    $this->checkMapping(ClearingEventTypes::USER, "User decision");
  }

  public function testTypeBulk()
  {
    $this->checkMapping(ClearingEventTypes::BULK, "Bulk");
  }
}
