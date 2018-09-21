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

namespace Fossology\Reuser\Test;

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
