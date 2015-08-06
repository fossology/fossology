<?php
/***********************************************************
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
 ***********************************************************/

/**
 * \file OneShot-empty.php
 * \brief Perform a one-shot license analysis on an empty file
 *       License returned should be: No_license_found
 */
require_once ('CommonCliTest.php');

class OneShotemptyTest extends CommonCliTest
{
  public $empty;

  public function testOneShotempty()
  {
    /* check to see if the file exists */
    $this->empty = dirname(dirname(__FILE__)).'/testdata/empty';
    $this->assertFileExists($this->empty,"OneShotemptyTest FAILURE! $this->empty not found\n");

    list($output,) = $this->runNomos("",array($this->empty));
    list(,$fname,,,$license) = explode(' ', $output);
    $this->assertEquals($fname, 'empty', "Error filename $fname does not equal empty");
    $this->assertEquals(trim($license), 'No_license_found', "Error license does not
      equal No_license_found, $license was returned");
  }
}
