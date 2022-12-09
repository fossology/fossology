<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: J.Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Exception;

class Types
{
  /** @var array */
  protected $map;
  /** @var string */
  protected $name;

  public function __construct($name)
  {
    $this->name = $name;
  }

  /**
   * @param int $type
   * @throws Exception
   * @return string
   */
  function getTypeName($type)
  {
    if (array_key_exists($type, $this->map)) {
      return $this->map[$type];
    }
    throw new \Exception("unknown " . $this->name . " id " . $type);
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
