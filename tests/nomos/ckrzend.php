<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/**
 * ckzend
 * \brief check that nomos recognizes the zend license
 *
 * Depends on the zend license being uploaded and processed.
 *
 */

require_once '/usr/share/php/PHPUnit/Extensions/SeleniumTestCase.php';
require_once ('../TestEnvironment.php');
//require_once ('../commonTestFuncs.php');


class WebTest extends PHPUnit_Extensions_SeleniumTestCase
{
	protected $host;

	public static $browsers = array(
	array(
    'name' => "Firefox on randotest",
    'browser' => '*firefox /usr/local/firefox/firefox-bin',
    'host' => 'randotest.ostt',
    'port' => 4444,
    'timeout' => 50000
	)
	);

	protected function setUp()
	{
		$this->setBrowserUrl('http://randotest.ostt/repo/');
	}

	public function testTitle()
	{
		$this->open('http://randotest.ostt/repo/');
		$this->assertTitle('Welcome to FOSSology');
	}
}
?>
