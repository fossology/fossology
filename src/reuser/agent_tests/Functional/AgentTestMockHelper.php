<?php

namespace Fossology\Lib\Agent;

use Fossology\Lib\Util\Object;

function cli_Init()
{
}

function getopt($arg1, $arg2)
{
  global $userId;
  global $jobId;
  global $groupId;

  return array("scheduler_start" => "", "userID" => $userId, "jobId" => $jobId, "groupID" => $groupId);
}

function fgets($in)
{
  global $fgetsMock;

  return $fgetsMock->fgets($in);
}

class FgetsMock extends Object
{
  function fgets($in) {}
}

