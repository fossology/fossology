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
