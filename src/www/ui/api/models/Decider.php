<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

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
 ***************************************************************/

namespace www\ui\api\models;


class Decider
{
  private $nomosMonk;
  private $bulkReused;
  private $newScanner;

  /**
   * Decider constructor.
   * @param $nomosMonk boolean
   * @param $bulkReused boolean
   * @param $newScanner boolean
   */
  public function __construct($nomosMonk = false, $bulkReused = false, $newScanner = false)
  {
    $this->nomosMonk = $nomosMonk;
    $this->bulkReused = $bulkReused;
    $this->newScanner = $newScanner;
  }

  /**
   * Get decider as an array
   * @return array
   */
  public function getArray()
  {
    return [
      "nomosMonk"  => $this->nomosMonk,
      "bulkReused" => $this->bulkReused,
      "newScanner" => $this->newScanner
    ];
  }
}
