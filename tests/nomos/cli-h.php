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
 * cli-h
 * \brief get usage message from nomos.
 *
 */
require_once '/usr/share/php/PHPUnit/Framework.php';

global $GlobalReady;
$GlobalReady=TRUE;

class cli1Test extends PHPUnit_Framework_TestCase
{
	public function testHelp()
	{
		print "Starting testHelp\n";
		// determine where nomos is installed
		$upStream = '/usr/local/share/fossology/php/pathinclude.php';
		$pkg = '/usr/share/fossology/php/pathinclude.php';
		if(file_exists($upStream))
		{
			require_once($upStream);
		}
		else if(file_exists($pkg))
		{
			require_once($pkg);
		}
		else
		{
			$this->assertFileExists($upStream,
			$message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
			$this->assertFileExists($pkg,
			$message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
		}
		$nomos = $AGENTDIR . '/nomos';
		// run it
		$last = exec("$nomos -h 2>&1", $out, $rtn);
		//print "last is:$last\nout is:\n";print_r($out) . "\n";
		$error = '/usr/local/lib/fossology/agents/nomos: invalid option -- h';
		$usage = 'Usage: /usr/local/lib/fossology/agents/nomos [options] [file [file [...]]';
		$this->assertEquals($error, $out[0]);
		$this->assertEquals($usage, $out[1]);
	}
}
?>
