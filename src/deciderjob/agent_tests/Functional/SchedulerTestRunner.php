<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\DeciderJob\Test;

/**
 * @interface SchedulerTestRunner
 * @brief Interface for scheduler. Called by test case
 */
interface SchedulerTestRunner
{
  /**
   * @brief Setup and run agent based on inputs
   * @param int $uploadId
   * @param int $userId
   * @param int $groupId
   * @param int $jobId
   * @param string $args
   * @return array Run success code, agent output, agent return code
   */
  public function run($uploadId, $userId = 2, $groupId = 2, $jobId = 1, $args = "");
}
