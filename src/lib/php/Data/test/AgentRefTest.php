<?php
/*
Copyright (C) 2014, Siemens AG

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


class AgentRefTest extends \PHPUnit_Framework_TestCase {

    private $agentId = 1243;

    private $agentName = "<agentName>";

    private $agentRevision = "<agentRevision";

    /**
     * @var AgentRef
     */
    private $agentRef;

    public function setUp() {
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
}
 