<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class AgentRefTest extends \PHPUnit\Framework\TestCase
{
  private $agentId = 1243;
  private $agentName = "<agentName>";
  private $agentRevision = "<agentRevision";
  /** @var AgentRef */
  private $agentRef;

  protected function setUp() : void
  {
    $this->agentRef = new AgentRef($this->agentId, $this->agentName, $this->agentRevision);
  }

  public function testGetAgentId()
  {
    assertThat($this->agentRef->getAgentId(), is($this->agentId));
  }

  public function testGetAgentName()
  {
    assertThat($this->agentRef->getAgentName(), is($this->agentName));
  }

  public function testGetAgentRevision()
  {
    assertThat($this->agentRef->getAgentRevision(), is($this->agentRevision));
  }

  public function testToString()
  {
    assertThat(strval($this->agentRef), is("AgentRef(1243, <agentName>, <agentRevision)"));
  }
}
