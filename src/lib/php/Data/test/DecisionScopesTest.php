<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;


use Fossology\Lib\Test\EnumMapTestBase;

class DecisionScopesTest extends EnumMapTestBase
{

  protected function setUp() : void
  {
    $this->setTypes(new DecisionScopes());
  }

  public function testTypeItem()
  {
    $this->checkMapping(DecisionScopes::ITEM, "local");
  }

  public function testTypePackage()
  {
    $this->checkMapping(DecisionScopes::PACKAGE, "package");
  }

  public function testTypeRepository()
  {
    $this->checkMapping(DecisionScopes::REPO, "global");
  }
}
