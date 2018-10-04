<?php
/***************************************************************
Copyright (C) 2018 Siemens AG

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
 * @brief Folder model
 */

namespace Fossology\UI\Api\Models;

class Folder
{

  /**
   * @var int $id ID of the folder
   */
  private $id;

  /**
   * @var string $name Name of the folder
   */
  private $name;

  /**
   * @var string $description Description of the folder
   */
  private $description;

  /**
   * Folder constructor.
   *
   * @param int $id
   * @param string $name
   * @param string $description
   */
  public function __construct($id, $name, $description)
  {
    $this->id = intval($id);
    $this->name = $name;
    $this->description = $description;
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
   * @return string
   */
  public function getDescription()
  {
    return $this->description;
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
   * @param string $description
   */
  public function setDescription($description)
  {
    $this->description = $description;
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
      'name' => $this->name,
      'description' => $this->description
    ];
  }
}
