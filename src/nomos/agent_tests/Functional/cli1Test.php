<?php
/*
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
require_once ('CommonCliTest.php');

class cli1Test extends CommonCliTest
{ 
  public function testHelp()
  {
    $nomos = dirname(dirname(__DIR__)) . '/agent/nomos';
    list($output,$retCode) = $this->runNomos($args="-h");
    $out = explode("\n", $output);
    $usage = "Usage: $nomos [options] [file [file [...]]";
    $this->assertEquals($usage, $out[0]);
  }
}
