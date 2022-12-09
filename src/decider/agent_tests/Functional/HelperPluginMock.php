<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
