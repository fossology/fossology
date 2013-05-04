<?php
/*
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 * cli-h
 * \brief get usage message from nomos.
 *
 */

require_once('../../../testing/lib/createRC.php');
class cli1Test extends PHPUnit_Framework_TestCase
{
	public function testHelp()
	{
		// determine where nomos is installed
    createRC();
    $sysconf = getenv('SYSCONFDIR');
		$nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
		// run it
		$last = exec("$nomos -h 2>&1", $out, $rtn);
		$usage = 'Usage: /usr/local/etc/fossology/mods-enabled/nomos/agent/nomos [options] [file [file [...]]';
		$this->assertEquals($usage, $out[0]);
	}
}
?>
