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


use Fossology\Lib\Db\DbManager;

class UploadStatus extends Types
{
  const OPEN = 1;
  const IN_PROGRESS = 2;
  const CLOSED = 3;
  const REJECTED = 4;
  
  /** @var array */
  private $values = array(self::OPEN, self::IN_PROGRESS, self::CLOSED, self::REJECTED);

  public function __construct(DbManager $dbManager)
  {
    parent::__construct("upload status type");

    $this->map = $dbManager->createMap('upload_status', 'status_pk', 'meaning');

    assert($this->map[self::OPEN] == "open");
    assert($this->map[self::IN_PROGRESS] == "in progress");
    assert($this->map[self::CLOSED] == "closed");
    assert($this->map[self::REJECTED] == "rejected");
    assert(count($this->map) == count($this->values));
  }

}