<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\DelAgent\UI;

/**
 * @class DeleteResponse
 * @brief Handle response from delagent
 */
class DeleteResponse
{
  /**
   * @var DeleteMessages $deleteMessageCode
   * Messages from delagent
   */
  private $deleteMessageCode;
  /**
   * @var string $additionalMessage
   * Additional messages for user
   */
  private $additionalMessage;

  /**
   * DeleteResponse constructor.
   * @param DeleteMessages|int $deleteMessage
   * @param string $additionalMessage
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
   * @brief Translates message code to strings
   * @return string
   */
  public function getDeleteMessageString()
  {
    switch ($this->getDeleteMessageCode()) {
      case 1:
        return "Deletion Scheduling failed";
      case 2:
        return "Deletion added to job queue";
      case 3:
        return "You don't have permissions to delete the upload";
      default:
        return "Invalid Error";
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
