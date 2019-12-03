<?php
/*
Copyright (C) 2015, Siemens AG

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
/**
 * @file HelperPluginMock.php
 * @brief Provide mock objects and functions for DeciderAgent
 */

namespace Fossology\Decider;

use Mockery as M;

/** @var Mockery::MockInterface $deciderPlugin
 * Mock object for decider plugin
 */
$deciderPlugin = M::mock();//'Fossology\\DeciderJob\\UI\\DeciderJobAgentPlugin');
$deciderPlugin->shouldReceive('AgentAdd')->withArgs(array(1,2,anything(), arrayWithSize(1)))->once();
/**
 * @var array $GLOBALS
 * Create mock plugin array for decider plugin
 */
$GLOBALS['xyyzzzDeciderJob'] = $deciderPlugin;
/**
 * @brief Mock function to get decider plugin required by BulkReuser
 * @param string $x
 * @return Mockery::MockInterface Mock plugin object
 */
function plugin_find($x)
{
  return $GLOBALS['xyyzzzDeciderJob'];
}
/**
 * @brief Mock function to depict scheduler working
 * @param int $jobId
 * @param string $agentName
 * @param int $uploadId
 * @return int Mock job id
 */
function IsAlreadyScheduled($jobId, $agentName, $uploadId)
{
  return 177;
}
