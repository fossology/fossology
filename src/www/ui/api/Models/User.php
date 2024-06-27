<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief User model
 */
namespace Fossology\UI\Api\Models;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';

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
   * @var string $accessLevel
   * Current user access level
   */
  private $accessLevel;
  /**
   * @var integer $rootFolderId
   * Current user's root folder id
   */
  private $rootFolderId;
  /**
   * @var integer $defaultGroup
   * Current user's default group id
   */
  private $defaultGroup;
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
   * @var Analysis $analysis
   * Current user's analysis from $agents
   */
  private $analysis;
  /**
   * @var int $defaultBucketPool
   * Default bucket pool of the user
   */
  private $defaultBucketPool;

  /**
   * User constructor.
   * @param integer $id
   * @param string $name
   * @param string $description
   * @param string $email
   * @param integer $accessLevel
   * @param integer $root_folder_id
   * @param integer $default_group_fk
   * @param boolean $emailNotification
   * @param object $agents
   * @param integer $defaultBucketPool
   */
  public function __construct($id, $name, $description, $email, $accessLevel, $root_folder_id, $emailNotification,
                              $agents, $default_group_fk=null, $defaultBucketPool=null)
  {
    $this->id = intval($id);
    $this->name = $name;
    $this->description = $description;
    $this->email = $email;
    switch ($accessLevel) {
      case PLUGIN_DB_READ:
        $this->accessLevel = "read_only";
        break;
      case PLUGIN_DB_WRITE:
        $this->accessLevel = "read_write";
        break;
      case PLUGIN_DB_CADMIN:
        $this->accessLevel = "clearing_admin";
        break;
      case PLUGIN_DB_ADMIN:
        $this->accessLevel = "admin";
        break;
      default:
        $this->accessLevel = "none";
    }
    $this->rootFolderId = intval($root_folder_id);
    $this->defaultGroup = is_null($default_group_fk) ? null : intval($default_group_fk);
    $this->emailNotification = $emailNotification;
    $this->agents = $agents;
    $this->analysis = new Analysis();
    $this->analysis->setUsingString($this->agents);
    if ($defaultBucketPool != null) {
      $this->defaultBucketPool = intval($defaultBucketPool);
    } else {
      $this->defaultBucketPool = null;
    }
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
   * @return string
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
   * @return integer
   */
  public function getDefaultGroupId()
  {
    return $this->defaultGroup;
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
   * @return int
   */
  public function getDefaultBucketPool()
  {
    return $this->defaultBucketPool;
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
  public function getArray($version = ApiVersion::V1)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $returnUser = array();
    $returnUser["id"] = $this->id;
    $returnUser["name"] = $this->name;
    $returnUser["description"] = $this->description;
    if ($this->email !== null) {
      $returnUser["email"] = $this->email;
      $returnUser["accessLevel"] = $this->accessLevel;
    }
    if ($this->rootFolderId !== null && $this->rootFolderId != 0) {
      $returnUser["rootFolderId"] = $this->rootFolderId;
    }
    if ($this->defaultGroup !== null) {
      $returnUser["defaultGroup"] = $version == ApiVersion::V2 ? $restHelper->getUserDao()->getGroupNameById($this->defaultGroup) : $this->defaultGroup;
    }
    if ($this->emailNotification !== null) {
      $returnUser["emailNotification"] = $this->emailNotification;
    }
    if ($this->agents !== null) {
      $returnUser["agents"] = $this->analysis->getArray($version);
    }
    if ($this->defaultBucketPool !== null) {
      $returnUser["defaultBucketpool"] = $this->defaultBucketPool;
    }
    return $returnUser;
  }
}
