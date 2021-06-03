<?php
/***************************************************************
 Copyright (C) 2021 Orange
 Author: Piotr Pszczola <piotr.pszczola@orange.com>

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
/**
 * @file
 * @brief Group model
 */

namespace Fossology\UI\Api\Models;

class Group
{

  /**
   * @var int $id ID of the group
   */
  private $id;

  /**
   * @var string $name Name of the group
   */
  private $name;

  /**
   * Group constructor.
   *
   * @param int $id
   * @param string $name
   */
  public function __construct($id, $name)
  {
    $this->id = intval($id);
    $this->name = $name;
  }

  ////// Getters //////

  /**
   * @return number
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @return string json
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  ////// Setters //////

  /**
   * @param number $id
   */
  public function setId($id)
  {
    $this->id = intval($id);
  }

  /**
   * @param string $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * Get the file element as associative array
   *
   * @return array
   */
  public function getArray()
  {
    return [
      'id' => intval($this->id),
      'name' => $this->name
    ];
  }
}