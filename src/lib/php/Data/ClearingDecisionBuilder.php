<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Exception;

class ClearingDecisionBuilder
{
  /** @var bool */
  private $sameFolder;
  /** @var ClearingEvent[] */
  private $clearingEvents;
  /** @var int */
  private $clearingId;
  /** @var int */
  private $uploadTreeId;
  /** @var int */
  private $pfileId;
  /** @var string */
  private $userName;
  /** @var int */
  private $userId;
  /** @var string */
  private $type;
  /** @var string */
  private $comment;
  /** @var string */
  private $reportinfo;
  /** @var string */
  private $acknowledgement;
  /** @var int */
  private $scope;
  /** @var int */
  private $timeStamp;

  function __construct()
  {
    $this->sameFolder = false;
    $this->clearingEvents = array();
    $this->clearingId = -1;
    $this->uploadTreeId = -1;
    $this->pfileId = -1;
    $this->userName = "fossy";
    $this->userId = -1;
    $this->type = null;
    $this->scope = DecisionScopes::ITEM;
    $this->timeStamp = time();
  }

  /**
   * @param int $clearingId
   * @return ClearingDecisionBuilder
   */
  public function setClearingId($clearingId)
  {
    $this->clearingId = intval($clearingId);
    return $this;
  }

  /**
   * @param int $timestamp
   * @return ClearingDecisionBuilder
   */
  public function setTimeStamp($timestamp)
  {
    $this->timeStamp = $timestamp;
    return $this;
  }

  /**
   * @param ClearingEvent[] $events
   * @return ClearingDecisionBuilder
   */
  public function setClearingEvents($events)
  {
    $this->clearingEvents = $events;
    return $this;
  }

  /**
   * @param int $pfileId
   * @return ClearingDecisionBuilder
   */
  public function setPfileId($pfileId)
  {
    $this->pfileId = intval($pfileId);
    return $this;
  }

  /**
   * @param boolean $sameFolder
   * @return ClearingDecisionBuilder
   */
  public function setSameFolder($sameFolder)
  {
    $this->sameFolder = $sameFolder;
    return $this;
  }

  /**
   * @param int $type
   * @return ClearingDecisionBuilder
   */
  public function setType($type)
  {
    $this->type = $type;
    return $this;
  }

  /**
   * @param int $scope
   * @return $this
   */
  public function setScope($scope)
  {
    $this->scope = $scope;
    return $this;
  }

  /**
   * @param int $uploadTreeId
   * @return ClearingDecisionBuilder
   */
  public function setUploadTreeId($uploadTreeId)
  {
    $this->uploadTreeId = $uploadTreeId;
    return $this;
  }

  /**
   * @param int $userId
   * @return ClearingDecisionBuilder
   */
  public function setUserId($userId)
  {
    $this->userId = $userId;
    return $this;
  }

  /**
   * @param string $userName
   * @return ClearingDecisionBuilder
   */
  public function setUserName($userName)
  {
    $this->userName = $userName;
    return $this;
  }

  /**
   * @return ClearingDecisionBuilder
   */
  public static function create()
  {
    return new ClearingDecisionBuilder();
  }

  /**
   * @param ClearingDecision $clearingDecision
   */
  public function copy(ClearingDecision $clearingDecision)
  {
    $this->sameFolder = $clearingDecision->getSameFolder();
    $this->clearingEvents = $clearingDecision->getClearingEvents();
    $this->clearingId = $clearingDecision->getClearingId();
    $this->uploadTreeId = $clearingDecision->getUploadTreeId();
    $this->pfileId = $clearingDecision->getPfileId();
    $this->userName = $clearingDecision->getUserName();
    $this->userId = $clearingDecision->getUserId();
    $this->type = $clearingDecision->getType();
    $this->comment = $clearingDecision->getComment();
    $this->reportinfo = $clearingDecision->getReportinfo();
    $this->acknowledgement = $clearingDecision->getAcknowledgement();
    $this->scope = $clearingDecision->getScope();
    $this->timeStamp = $clearingDecision->getTimeStamp();
  }

  /**
   * @throws Exception
   * @return ClearingDecision
   */
  public function build()
  {
    if ($this->type === null) {
      throw new Exception("decision type should be set");
    }

    return new ClearingDecision($this->sameFolder, $this->clearingId,
        $this->uploadTreeId, $this->pfileId, $this->userName, $this->userId, $this->type, $this->scope,
        $this->timeStamp, $this->clearingEvents, $this->reportinfo, $this->comment, $this->acknowledgement);
  }
}

