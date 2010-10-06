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
 * testLogin
 * \brief test that the FOSSology web site can be logged into.
 *
 * @version "$Id $"
 *
 * Created on Oct 5, 2010
 */

require_once('fossologyMenus.php');

class testLogin extends fossologyMenus
{
	protected function setUp()
	{
		require_once('browsers.php');
		$this->setBrowserUrl('http://randotest.ostt/repo/');
	}

	public function testLogin()
	{

		print "test Login starting...\n";

		$this->login();
		$this->assertTitle('Welcome to FOSSology');
	}
}
?>
