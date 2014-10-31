<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Data;



use Fossology\Lib\Exception;
use Fossology\Lib\Util\Object;

class Types  extends Object {
  /** @var array */
  protected  $map;
  protected $name;

  public function __construct( $name) {
    $this->name = $name;
  }

  /**
   * @param int $type
   * @throws Exception
   * @return string
   */
  function getTypeName($type)
  {
    if (array_key_exists($type, $this->map))
    {
      return $this->map[$type];
    }
    throw new Exception("unknown " . $this->name . " id " . $type);
  }

  /**
   * @return array
   */
  public function getMap()
  {
    return $this->map;
  }

  /**
   * @return int
   */
  public function getTypeByName($name)
  {
    return array_search($name, $this->map);
  }

} 