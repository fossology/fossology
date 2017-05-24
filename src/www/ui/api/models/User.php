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


class User
{
  private $id;
  private $name;
  private $description;
  private $email;
  private $accessLevel;
  private $rootFolderId;
  private $emailNotification;
  private $agents;

  /**
   * User constructor.
   * @param $id integer
   * @param $name string
   * @param $description string
   * @param $email string
   * @param $accessLevel integer
   * @param $root_folder_id integer
   * @param $emailNotification boolean
   * @param $agents object
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
   * @return AccessLevel
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
   * @return array
   */
  public function getJSON()
  {
    return array(
      'userId' => $this->id,
      'description' => $this->description,
      'email' => $this->email,
      "accessLevel" => $this->accessLevel,
      "rootFolderId" => $this->rootFolderId,
      "emailNotification" => $this->emailNotification,
      "agents" => $this->agents
    );
  }




}
