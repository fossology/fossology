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
        self::REJECTED => "recected"
    );
  }
}