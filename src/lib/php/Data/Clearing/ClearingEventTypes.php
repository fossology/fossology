<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\Types;

class ClearingEventTypes extends Types
{

  const USER = 1;
  const BULK = 2;
  const AGENT = 3;
  const IMPORT = 4;
  const AUTO = 5;

  public function __construct()
  {
    parent::__construct("license decision type");

    $this->map = array(
        self::USER => "User decision",
        self::BULK => "Bulk",
        self::AGENT => "User confirmed agent finding",
        self::IMPORT => "Imported decision",
        self::AUTO => "Auto Concluded"
    );
  }
}
