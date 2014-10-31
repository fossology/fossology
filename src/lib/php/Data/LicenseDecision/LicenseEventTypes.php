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

namespace Fossology\Lib\Data\LicenseDecision;


use Fossology\Lib\Data\Types;
use Fossology\Lib\Db\DbManager;

class LicenseEventTypes extends Types {
  const USER = 1;
  const BULK = 2;

  /** @var array */
  private $values = array(self::USER, self::BULK);

  public function __construct(DbManager $dbManager)
  {
    parent::__construct("license decision type");
    $this->map = $dbManager->createMap('license_decision_type', 'type_pk', 'meaning');

    assert($this->map[self::USER] == "User decision");
    assert($this->map[self::BULK] == "Bulk");
    assert(count($this->map) == count($this->values));
  }

} 