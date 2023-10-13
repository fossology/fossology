<?php
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

namespace Fossology\Lib\Agent;

function cli_Init()
{
}

function getopt($arg1, $arg2)
{
  global $userId;
  global $jobId;
  global $groupId;
  global $extraOpts;

  if (! is_array($extraOpts)) {
    $extraOpts = array();
  }

  return array_merge(
    array(
      "scheduler_start" => "",
      "userID" => $userId,
      "jobId" => $jobId,
      "groupID" => $groupId
    ), $extraOpts);
}

function fgets($in)
{
  global $fgetsMock;

  return $fgetsMock->fgets($in);
}

class FgetsMock
{
  function fgets($in)
  {
  }
}

