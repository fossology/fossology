<?php
/*
 SPDX-FileCopyrightText: © 2021 Orange
Author: Piotr Pszczola <piotr.pszczola@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
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