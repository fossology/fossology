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

namespace api\models;


class Info
{
  private $code;
  private $message;
  private $type;
  /**
   * Error constructor.
   * @param $code
   * @param $message
   * @param $type
   */
  public function __construct($code, $message, $type)
  {
    $this->code = $code;
    $this->message = $message;
    $this->type = $type;
  }

  public function getJSON()
  {
    return json_encode(array(
      'code' => $this->code,
      'message' => $this->message,
      'type' => $this->type
    ));
  }

  /**
   * @return mixed
   */
  public function getCode()
  {
    return $this->code;
  }

  /**
   * @return mixed
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * @return mixed
   */
  public function getType()
  {
    return $this->type;
  }


}
