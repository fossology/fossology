<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class UploadStatus extends Types
{
  const OPEN = 1;
  const IN_PROGRESS = 2;
  const CLOSED = 3;
  const REJECTED = 4;

  public function __construct()
  {
    parent::__construct("upload status type");

    $this->map = array(
        self::OPEN => "open",
        self::IN_PROGRESS => "in progress",
        self::CLOSED => "closed",
        self::REJECTED => "rejected"
    );
  }
}