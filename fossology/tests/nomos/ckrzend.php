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
require_once ('../commonTestFuncs.php');

global $URL;

class WebTest extends PHPUnit_Extensions_SeleniumTestCase
{
	protected $host;

	protected function setUp()
	{
		global $URL;
		print "URL is:$URL\n";
		$this->setBrowser('*firefox /usr/local/firefox/firefox-bin');
		//$this->host = getHost($URL);
		//$this->host = 'http://randotest.ostt/repo/';
		$this->host = 'http://www.google.com/';
		print "host is:$this->host\n";
		$this->setBrowserUrl($this->host);
	}

	public function testTitle()
	{
		global $URL;
		print "TT: URL is:$URL\n";
			
		$this->open($this->host);
		$this->assertTitle('Google');
	}
}
?>
