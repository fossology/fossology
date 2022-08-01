<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Test\EnumMapTestBase;

class DecisionTypesTest extends EnumMapTestBase
{

  protected function setUp() : void
  {
    $this->setTypes(new DecisionTypes());
  }

  public function testTypeToBeDiscussed()
  {
    $this->checkMapping(DecisionTypes::TO_BE_DISCUSSED, "To be discussed");
  }

  public function testTypeIrrelevant()
  {
    $this->checkMapping(DecisionTypes::IRRELEVANT, "Irrelevant");
  }

  public function testTypeIdentified()
  {
    $this->checkMapping(DecisionTypes::IDENTIFIED, "Identified");
  }
}
