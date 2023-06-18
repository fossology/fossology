<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief BulkHistory model
 */
namespace Fossology\UI\Api\Models;

class BulkHistory
{

  /**
   * @var int $bulkId ID of the Bulk
   */
  private $bulkId;

  /**
   * @var int $clearingEventId ID of the associated clearing event
   */
  private $clearingEventId;

  /**
   * @var string $text Scan reference text
   */
  private $text;

  /**
   * @var boolean $matched Whether matched or not
   */
  private $matched;

  /**
   * @var boolean $tried Whether tried or not
   */
  private $tried;

  /**
   * @var array $addedLicenses Added licenses
   */
  private $addedLicenses;

  /**
   * @var array $removedLicenses Removed licenses
   */
  private $removedLicenses;

  /**
   * BulkHistory constructor.
   *
   * @param int $bulkId
   * @param int $clearingEventId
   * @param string $text
   * @param boolean $matched
   * @param boolean $tried
   * @param array $addedLicenses
   * @param array $removedLicenses
   */
  public function __construct($bulkId, $clearingEventId, $text, $matched, $tried, $addedLicenses, $removedLicenses)
  {
    $this->bulkId = intval($bulkId);
    $this->clearingEventId = intval($clearingEventId);
    $this->text = $text;
    $this->matched = $matched;
    $this->tried = $tried;
    $this->addedLicenses = $addedLicenses;
    $this->removedLicenses = $removedLicenses;
  }

  public function getBulkId()
  {
    return $this->bulkId;
  }

  public function getClearingEventId()
  {
    return $this->clearingEventId;
  }

  public function getText()
  {
    return $this->text;
  }

  public function getMatched()
  {
    return $this->matched;
  }

  public function getTried()
  {
    return $this->tried;
  }

  public function getAddedLicenses()
  {
    return $this->addedLicenses;
  }

  public function getRemovedLicenses()
  {
    return $this->removedLicenses;
  }

  public function setBulkId($bulkId)
  {
    $this->bulkId = $bulkId;
  }

  public function setClearingEventId($clearingEventId)
  {
    $this->clearingEventId = $clearingEventId;
  }

  public function setText($text)
  {
    $this->text = $text;
  }

  public function setMatched($matched)
  {
    $this->matched = $matched;
  }

  public function setTried($tried)
  {
    $this->tried = $tried;
  }

  public function setAddedLicenses($addedLicenses)
  {
    $this->addedLicenses = $addedLicenses;
  }

  public function setRemovedLicenses($removedLicenses)
  {
    $this->removedLicenses = $removedLicenses;
  }

  public function getArray()
  {
    return [
      "bulkId" => $this->bulkId,
      "clearingEventId" => $this->clearingEventId,
      "text" => $this->text,
      "matched" => $this->matched,
      "tried" => $this->tried,
      "addedLicenses" => $this->addedLicenses,
      "removedLicenses" => $this->removedLicenses
    ];
  }
}
