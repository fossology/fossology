<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class DecisionScopes extends Types
{
  const ITEM = 0;
  const REPO = 1;
  const UPLOAD = 2;
  const PACKAGE = 3;

  public function __construct()
  {
    parent::__construct("decision scope");

    $this->map = array(
        self::ITEM => "local",
        self::PACKAGE => "package",
        self::REPO => "global");
  }
}
