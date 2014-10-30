<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Data;


use Fossology\Lib\Db\DbManager;

class DecisionTypes extends Types
{
  const TO_BE_DISCUSSED = 3;
  const IRRELEVANT = 4;
  const IDENTIFIED = 5;

  /** @var array */
  private $values = array(self::TO_BE_DISCUSSED, self::IRRELEVANT, self::IDENTIFIED);

  public function __construct(DbManager $dbManager)
  {
    parent::__construct("clearing decision type");

    $this->map = $dbManager->createMap('clearing_decision_type', 'type_pk', 'meaning');

    assert($this->map[self::TO_BE_DISCUSSED] == "To be discussed");
    assert($this->map[self::IRRELEVANT] == "Irrelevant");
    assert($this->map[self::IDENTIFIED] == "Identified");
    assert(count($this->map) == count($this->values));
  }

}