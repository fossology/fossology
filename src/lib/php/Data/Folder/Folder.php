<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Folder;

class Folder
{

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
  public function __construct($id, $name, $description, $permissions)
  {
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
