<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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


namespace Fossology\Reportgen;
require_once("$MODDIR/lib/php/common-cli.php");
require_once("$MODDIR/lib/php/Report/getClearedProto.php");
cli_Init();

class LicenseClearedGetter extends LicenseClearedGetterProto
{
  public function __construct() {
    parent::__construct();
  }

}

$clearedGetter = new LicenseClearedGetter();
$clearedGetter->getCliArgs();
$uploadId = $clearedGetter->getUploadId();
$userId = $clearedGetter->getUserId();
print json_encode($clearedGetter->getCleared($uploadId, $userId));