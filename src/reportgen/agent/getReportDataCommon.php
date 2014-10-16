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

require_once("$MODDIR/lib/php/common-cli.php");

cli_Init();

function getUploadIdArg()
{
  $args = getopt("u:", array());

  if (!array_key_exists('u',$args))
  {
    print "missing required parameter -u {uploadId}";
    exit(2);
  }

  $uploadId = intval($args['u']);

  if ($uploadId<=0)
  {
    print "invalid uploadId ".$uploadId;
    exit(2);
  }
  return $uploadId;
}
