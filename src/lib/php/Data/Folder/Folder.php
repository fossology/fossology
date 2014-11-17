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

namespace Fossology\Lib\Data\Folder;


class Folder {

  /** @var int */
  private $id;

  /** @var string */
  private $name;

  /** @var string */
  private $description;

  /** @var int */
  private $permissions;

  /**
   * @param int $id
   * @param string $name
   * @param string $description
   * @param int $permissions
   */
  public function __construct($id, $name, $description, $permissions) {
    $this->id = $id;
    $this->name = $name;
    $this->description = $description;
    $this->permissions = $permissions;
  }

  /**
   * @return string
   */
  public function getDescription()
  {
    return $this->description;
  }

  /**
   * @return int
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
   * @return int
   */
  public function getPermissions()
  {
    return $this->permissions;
  }


} 