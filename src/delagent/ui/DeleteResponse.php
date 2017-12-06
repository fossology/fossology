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

namespace Fossology\DelAgent\UI;

class DeleteResponse
{
  /**
   * @var $deleteMessageCode DeleteMessages
   */
  private $deleteMessageCode;
  private $additionalMessage;

  /**
   * DeleteResponse constructor.
   * @param $deleteMessage int
   * @param $additionalMessage
   */
  public function __construct($deleteMessage, $additionalMessage = "")
  {
    $this->deleteMessageCode = $deleteMessage;
    $this->additionalMessage = $additionalMessage;
  }

  /**
   * @return DeleteMessages|int
   */
  public function getDeleteMessageCode()
  {
    return $this->deleteMessageCode;
  }

  /**
   * @return string
   */
  public function getDeleteMessageString()
  {
    switch ($this->getDeleteMessageCode())
    {
      case 0: return "You dont have permissions to delete the upload";
      case 1: return "Deletion Scheduling failed";
      case 2: return "Deletion added to job queue";
      default: return "Invalid Error";
    }
  }

  /**
   * @return string
   */
  public function getAdditionalMessage()
  {
    return $this->additionalMessage;
  }

}
