<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class JobStatus extends Types
{
  const RUNNING = 0;
  const SUCCESS = 1;
  const FAILED = 2;

  public function __construct()
  {
    parent::__construct("job status type");

    $this->map = array(
        self::SUCCESS => "success",
        self::RUNNING => "running",
        self::FAILED => "failed"
    );
  }
}