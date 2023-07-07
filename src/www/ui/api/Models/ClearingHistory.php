<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Info model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Info
 * @brief Info model to contain general error and return values
 */
class ClearingHistory
{
  /**
   * @var string $date
   * HTTP response code
   */
  private $date;
    /**
     * @var string $username
     * Reponse message
     */
  private $username;
    /**
     * @var string $scope
     * Response type
     */
  private $scope;
    /**
     * @var string $type
     * Response type
     */
  private $type;

  /**
   * @var array $addedLicenses
   * Response type
   */
  private $addedLicenses;
  /**
   * @var array $removedLicenses
   * Response type
   */
  private $removedLicenses;

  /**
   * @param string $date
   * @param string $username
   * @param string $scope
   * @param string $type
   * @param array $addedLicenses
   * @param array $removedLicenses
   */
  public function __construct($date, $username, $scope, $type, $addedLicenses, $removedLicenses)
  {
    $this->date = $date;
    $this->username = $username;
    $this->scope = $scope;
    $this->type = $type;
    $this->addedLicenses = $addedLicenses;
    $this->removedLicenses = $removedLicenses;
  }

  ////// Getters /////
  /**
   * Get info as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'date' => $this->date,
      'username' => $this->username,
      'scope' => $this->scope,
      'type' => $this->type,
      'addedLicenses' => $this->addedLicenses,
      'removedLicenses' => $this->removedLicenses
    ];
  }

  /**
   * @return string
   */
  public function getDate()
  {
    return $this->date;
  }

  /**
   * @param string $date
   */
  public function setDate(string $date)
  {
    $this->date = $date;
  }

  /**
   * @return string
   */
  public function getUsername()
  {
    return $this->username;
  }

  /**
   * @param string $username
   */
  public function setUsername(string $username)
  {
    $this->username = $username;
  }

  /**
   * @return string
   */
  public function getScope()
  {
    return $this->scope;
  }

  /**
   * @param string $scope
   */
  public function setScope($scope)
  {
    $this->scope = $scope;
  }

  /**
   * @return string
   */
  public function getType()
  {
    return $this->type;
  }

  /**
   * @param string $type
   */
  public function setType($type)
  {
    $this->type = $type;
  }

  /**
   * @return array
   */
  public function getAddedLicenses()
  {
    return $this->addedLicenses;
  }

  /**
   * @param array $addedLicenses
   */
  public function setAddedLicenses($addedLicenses)
  {
    $this->addedLicenses = $addedLicenses;
  }

  /**
   * @return array
   */
  public function getRemovedLicenses()
  {
    return $this->removedLicenses;
  }

  /**
   * @param array $removedLicenses
   */
  public function setRemovedLicenses(array $removedLicenses)
  {
    $this->removedLicenses = $removedLicenses;
  }
}
