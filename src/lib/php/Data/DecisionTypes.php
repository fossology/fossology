<?php
/*
 SPDX-FileCopyrightText: © 2014, 2019-2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class DecisionTypes extends Types
{
  const WIP = 0;
  const TO_BE_DISCUSSED = 3;
  const IRRELEVANT = 4;
  const IDENTIFIED = 5;
  const DO_NOT_USE = 6;
  const NON_FUNCTIONAL = 7;

  public function __construct()
  {
    parent::__construct("decision type");

    $this->map = array(
        self::TO_BE_DISCUSSED => "To be discussed",
        self::IRRELEVANT => "Irrelevant",
        self::IDENTIFIED => "Identified",
        self::DO_NOT_USE => "Do not use",
        self::NON_FUNCTIONAL => "Non functional"
    );
  }

  public function getConstantNameFromKey($key)
  {
    return array(
        self::TO_BE_DISCUSSED => "TO_BE_DISCUSSED",
        self::IRRELEVANT => "IRRELEVANT",
        self::IDENTIFIED => "IDENTIFIED",
        self::DO_NOT_USE => "DO_NOT_USE",
        self::NON_FUNCTIONAL => "NON_FUNCTIONAL"
    )[$key];
  }

  public function getExtendedMap()
  {
    $map = $this->map;
    $map[self::WIP] = 'Temporary';
    return $map;
  }
}
