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
/**
 * @file
 * @brief User model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class User
 * @brief Model to hold user information
 */
class User
{
  /**
   * @var integer $id
   * Current user id
   */
  private $id;
  /**
   * @var string $name
   * Current user's name
   */
  private $name;
  /**
   * @var string $description
   * Current user description
   */
  private $description;
  /**
   * @var string $email
   * Current user email
   */
  private $email;
  /**
   * @var integer $accessLevel
   * Current user access level
   */
  private $accessLevel;
  /**
   * @var integer $rootFolderId
   * Current user's root folder id
   */
  private $rootFolderId;
  /**
   * @var boolean $emailNotification
   * Current user's email preference
   */
  private $emailNotification;
  /**
   * @var array $agents
   * Current user's agent preference
   */
  private $agents;

  /**
   * User constructor.
   * @param integer $id
   * @param string $name
   * @param string $description
   * @param string $email
   * @param integer $accessLevel
   * @param integer $root_folder_id
   * @param boolean $emailNotification
   * @param object $agents
   */
  public function __construct($id, $name, $description, $email, $accessLevel, $root_folder_id, $emailNotification, $agents)
  {
    $this->id = $id;
    $this->name = $name;
    $this->description = $description;
    $this->email = $email;
    $this->accessLevel = $accessLevel;
    $this->rootFolderId = $root_folder_id;
    $this->emailNotification = $emailNotification;
    $this->agents = $agents;
  }

  ////// Getters //////
  /**
   * @return integer
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
   * @return string
   */
  public function getEmail()
  {
    return $this->email;
  }

  /**
   * @return integer
   */
  public function getAccessLevel()
  {
    return $this->accessLevel;
  }

  /**
   * @return integer
   */
  public function getRootFolderId()
  {
    return $this->rootFolderId;
  }

  /**
   * @return boolean
   */
  public function getEmailNotification()
  {
    return $this->emailNotification;
  }

  /**
   * @return object
   */
  public function getAgents()
  {
    return $this->agents;
  }

  /**
   * Get current user in JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get user element as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "userId"       => $this->id,
      "description"  => $this->description,
      "email"        => $this->email,
      "accessLevel"  => $this->accessLevel,
      "rootFolderId" => $this->rootFolderId,
      "emailNotification" => $this->emailNotification,
      "agents"       => $this->agents
    ];
  }
}
