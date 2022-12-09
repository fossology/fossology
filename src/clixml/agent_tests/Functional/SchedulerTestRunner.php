<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\CliXml\Test;

interface SchedulerTestRunner
{
  public function run($uploadId, $userId = 2, $groupId = 2, $jobId = 1, $args = "");
}
