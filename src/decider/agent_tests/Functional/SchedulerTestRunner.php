<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Decider\Test;

/**
 * @interface SchedulerTestRunner
 * @brief Create dummy run interface
 */
interface SchedulerTestRunner
{
  /**
   * @brief Get the arguments required by agent to run and try to run the agent
   * @param int $uploadId Upload id agent should work on
   * @param int $userId   User who run the agent
   * @param int $groupId  Group who run the agent
   * @param int $jobId    Job id agent should work on
   * @param string $args  Additional arguments to the agent
   */
  public function run($uploadId, $userId = 2, $groupId = 2, $jobId = 1, $args = "");
}
