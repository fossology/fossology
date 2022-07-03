<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

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
class Info
{
  /**
   * @var integer $code
   * HTTP response code
   */
  private $code;
  /**
   * @var string $message
   * Reponse message
   */
  private $message;
  /**
   * @var InfoType $type
   * Response type
   */
  private $type;
  /**
   * Error constructor.
   * @param integer $code
   * @param string $message
   * @param InfoType $type
   */
  public function __construct($code, $message, $type)
  {
    $this->code = $code;
    $this->message = $message;
    $this->type = $type;
  }

  ////// Getters //////

  /**
   * Get the info as JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get info as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'code' => $this->code,
      'message' => $this->message,
      'type' => $this->type
    ];
  }

  /**
   * @return integer
   */
  public function getCode()
  {
    return $this->code;
  }

  /**
   * @return string
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * @return InfoType
   */
  public function getType()
  {
    return $this->type;
  }
}
