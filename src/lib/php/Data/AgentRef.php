<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class AgentRef
{

  /**
   * @var array $AGENT_LIST
   * List of agents FOSSology uses to get agent ids
   */
  const AGENT_LIST = array(
    'nomos' => 'N',
    'monk' => 'M',
    'ninka' => 'Nk',
    'reportImport' => 'I',
    'ojo' => 'O',
    'scancode' => 'Sc',
    'spasht' => 'Sp',
    'reso' => 'Rs',
    'scanoss' => 'So'
  );
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
    $this->agentId = intval($agentId);
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
