<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Compatibility\Test;

/**
 * @interface SchedulerTestRunner
 * @brief Scheduler run interface
 */
interface SchedulerTestRunner
{
  /**
   * @brief Function to run agent from scheduler
   * @param int $uploadId Upload id to run agent on
   * @param int $userId   User id to use
   * @param int $groupId  Group id to use
   * @param int $jobId    Job id to run
   * @param string $args  Arguments for scheduler
   * @return array Success code, output, return code
   */
  public function run($uploadId, $userId = 2, $groupId = 2, $jobId = 1, $args = "");
}
