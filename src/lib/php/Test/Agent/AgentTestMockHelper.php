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
  global $extraOpts;
  
  if (!is_array($extraOpts))
  {
    $extraOpts = array();
  }

  $opts = array_merge(array("scheduler_start" => "", "userID" => $userId, "jobId" => $jobId, "groupID" => $groupId), $extraOpts);
  
  return $opts;
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

