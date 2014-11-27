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

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\Types;

class ClearingEventTypes extends Types {
  const USER = 1;
  const BULK = 2;
  const AGENT = 3;
  const REUSED_BIT = 0b100;

  public function __construct()
  {
    parent::__construct("license decision type");

    $this->map = array(
        self::USER => "User decision",
        self::BULK => "Bulk",
        self::AGENT => "Agent finding"
    );

    foreach(array_keys($this->map) as $key) {
      $newKey = $key | slef::REUSED_BIT;
      $this->map[$newKey] = $this->getTypeName($newKey);
    }
  }

  public function getTypeName($typeId)
  {
    return $this->map[$typeId & ~self::REUSED_BIT ] . (($typeId & self::REUSED_BIT)!=0 ? ", reused" :"");
  }

} 