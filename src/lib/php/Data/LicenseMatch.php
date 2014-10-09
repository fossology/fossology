<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Andreas Würl, Daniele Fognini

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

use Fossology\Lib\Util\Object;

class LicenseMatch extends Object
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

}