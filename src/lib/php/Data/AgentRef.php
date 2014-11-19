<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

class AgentRef extends Object
{
  /**
   * @var int
   */
  private $agentId;
  /**
   * @var string
   */
  private $agentName;
  /**
   * @var string
   */
  private $agentRevision;

  public function __construct($agentId, $agentName, $agentRevision)
  {
    $this->agentId = $agentId;
    $this->agentName = $agentName;
    $this->agentRevision = $agentRevision;
  }

  /**
   * @return int
   */
  public function getAgentId()
  {
    return $this->agentId;
  }

  /**
   * @return string
   */
  public function getAgentName()
  {
    return $this->agentName;
  }

  /**
   * @return string
   */
  public function getAgentRevision()
  {
    return $this->agentRevision;
  }
  
  public function __toString()
  {
    return 'AgentRef(' . $this->agentId . ', ' . $this->agentName . ', ' . $this->agentRevision . ')';
  }

} 