<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Authors: Andreas Würl, Daniele Fognini

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class LicenseMatch
{
  /**
   * @var LicenseRef
   */
  private $licenseRef;
  /**
   * @var AgentRef
   */
  private $agentRef;
  /**
   * @var null|int
   */
  private $percent;
  /**
   * @var int
   */
  private $fileId;
  /**
   * @var int
   */
  private $licenseFileId;

  /**
   * @param int $fileId
   * @param LicenseRef $licenseRef
   * @param AgentRef $agentRef
   * @param int$licenseFileId
   * @param null|int $percent
   */
  public function __construct($fileId, LicenseRef $licenseRef, AgentRef $agentRef, $licenseFileId, $percent = null)
  {
    $this->fileId = $fileId;
    $this->licenseRef = $licenseRef;
    $this->agentRef = $agentRef;
    $this->licenseFileId = $licenseFileId;
    $this->percent = $percent;
  }

  /**
   * @return int
   */
  public function getFileId()
  {
    return $this->fileId;
  }

  /**
   * @return int
   */
  public function getLicenseFileId()
  {
    return $this->licenseFileId;
  }

  /**
   * @return LicenseRef
   */
  public function getLicenseRef()
  {
    return $this->licenseRef;
  }

  /**
   * @return AgentRef
   */
  public function getAgentRef()
  {
    return $this->agentRef;
  }

  /**
   * @return int|null
   */
  public function getPercentage()
  {
    return $this->percent;
  }

  /**
   * @return int
   */
  public function getLicenseId()
  {
    return $this->licenseRef->getId();
  }
}
