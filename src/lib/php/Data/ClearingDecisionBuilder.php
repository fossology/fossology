<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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
*/

namespace Fossology\Lib\Data;


use DateTime;
use Fossology\Lib\Exception;
use Fossology\Lib\Util\Object;

class ClearingDecisionBuilder extends Object
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
  /** @var int */
  private $scope;
  /** @var DateTime */
  private $dateAdded;

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
    $this->dateAdded = new DateTime();
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
   * @param string $date_added
   * @return ClearingDecisionBuilder
   */
  public function setDateAdded($date_added)
  {
    $this->dateAdded->setTimestamp($date_added);
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
    $this->scope = $clearingDecision->getScope();
    $this->dateAdded = $clearingDecision->getDateAdded();
  }

  /**
   * @throws Exception
   * @return ClearingDecision
   */
  public function build()
  {
    if ($this->type === null)
    {
      throw new Exception("decision type should be set");
    }

    return new ClearingDecision($this->sameFolder, $this->clearingId,
        $this->uploadTreeId, $this->pfileId, $this->userName, $this->userId, $this->type, $this->scope,
        $this->dateAdded, $this->clearingEvents, $this->reportinfo, $this->comment);
  }

}

