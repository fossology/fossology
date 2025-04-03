<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief License
 */

namespace Fossology\UI\Api\Models;

use PhpOffice\PhpSpreadsheet\Calculation\Logical\Boolean;

/**
 * @class LicenseDecision
 * @package Fossology\UI\Api\Models
 * @brief LicenseDecision model to hold license decision related info
 */
class LicenseDecision extends License
{
  /**
   * @var array $ALLOWED_KEYS
   * Allowed keys from user to parse
   */
  const ALLOWED_KEYS = ['shortName', 'fullName', 'text', 'url', 'risk',
    'isCandidate', 'mergeRequest', 'source', 'acknowledgement', 'comment'];

  /**
   * @var array $sources
   * source of the license
   */
  private $sources;
  /**
   * @var string $acknowledgement
   * acknowledgement of the license
   */
  private $acknowledgement;
  /**
   * @var string $comment
   * The comment of the license
   */
  private $comment;

  /**
   * @var bool $isMainLicense
   * The main license
   */
  private $isMainLicense;

  /**
   * @var bool $isRemoved
   * The removed license
   */
  private $isRemoved;

  /**
   * @param array $sources
   * @param string $acknowledgement
   * @param string $comment
   */
  public function __construct($id,
                              $shortName = "",
                              $fullName = "",
                              $text = "",
                              $url = "",
                              $sources = [], $acknowledgement = "", $comment = "",
                              $isMainLicense = false,
                              $obligations = null,
                              $risk = null,
                              $isRemoved = false,
                              $isCandidate = false)
  {
    parent::__construct(
      $id,
      $shortName,
      $fullName,
      $text,
      $url,
      $obligations,
      $risk,
      $isCandidate
    );
    $this->setSources($sources);
    $this->setAcknowledgement($acknowledgement);
    $this->setComment($comment);
    $this->setIsMainLicense($isMainLicense);
    $this->setIsRemoved($isRemoved);
  }


  /**
   * JSON representation of the license
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Array representation of the license
   * @return array
   */
  public function getArray()
  {
    $data = parent::getArray();
    $data['sources'] = $this->getSources();
    $data['acknowledgement'] = $this->getAcknowledgement();
    $data['obligations'] = $this->getObligations();
    $data['comment'] = $this->getComment();
    $data['isMainLicense'] = $this->getIsMainLicense();
    $data['isRemoved'] = $this->getIsRemoved();
    return $data;
  }

  /**
   * @return array
   */
  public function getSources(): array
  {
    return $this->sources;
  }

  /**
   * @param array $sources
   */
  public function setSources(array $sources): void
  {
    $this->sources = $sources;
  }

  /**
   * @return string
   */
  public function getAcknowledgement(): string
  {
    return $this->acknowledgement;
  }

  /**
   * @param string $acknowledgement
   */
  public function setAcknowledgement(string $acknowledgement): void
  {
    $this->acknowledgement = $acknowledgement;
  }

  /**
   * @return string
   */
  public function getComment(): string
  {
    return $this->comment;
  }

  /**
   * @param string $comment
   */
  public function setComment(string $comment): void
  {
    $this->comment = $comment;
  }

  /**
   * @param bool $isRemoved
   */
  public function setIsRemoved(bool $isRemoved): void
  {
    $this->isRemoved = $isRemoved;
  }

  /**
   * @return bool
   */
  public function getIsRemoved(): bool
  {
    return $this->isRemoved;
  }

  /**
   * @return bool
   */
  public function getIsMainLicense(): bool
  {
    return $this->isMainLicense;
  }

  /**
   * @param bool $isMainLicense
   */
  public function setIsMainLicense(bool $isMainLicense): void
  {
    $this->isMainLicense = $isMainLicense;
  }
}
