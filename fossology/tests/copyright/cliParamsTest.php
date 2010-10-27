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
 * cliParams
 * \brief test the copyright cli parameters, i, c and t and no parameters.
 *
 * @version "$Id $"
 *
 * Created on Oct 20, 2010
 */
require_once '/usr/share/php/PHPUnit/Framework.php';

global $GlobalReady;
$GlobalReady=TRUE;

class cliParamsTest extends PHPUnit_Framework_TestCase
{
	protected $agentDir;
	protected $copyright;

	function setUp()
	{
		global $GlobalReady;
		
	 // determine where the agents are installed
		$upStream = '/usr/local/share/fossology/php/pathinclude.php';
		$pkg = '/usr/share/fossology/php/pathinclude.php';

		if(file_exists($upStream))
		{
			print "setup in upstream, before require\n";
			require_once($upStream);
			print "agentdir is:$AGENTDIR\n";
			$this->agentDir = $AGENTDIR;
			$this->copyright = $this->agentDir . '/copyright';
		}
		else if(file_exists($pkg))
		{
			require_once($pkg);
			print "agentdir is:$AGENTDIR\n";
			$this->agentDir = $AGENTDIR;
			$this->copyright = $this->agentDir . '/copyright';
		}
		else
		{
			$this->assertFileExists($upStream,
			$message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
		}
		print "agent:$this->agentDir\ncopy:$this->copyright\n";
    return;			
	} // setUP

	function testHelp()
	{
		// copyright -h
		$last = exec("$this->copyright -h 2>&1", $out, $rtn);
		print "testHelp: last is:$last\nout is:\n";print_r($out) . "\n";
		return;
	}

	function testC()
	{
		// copyright -c file
		$file = '/home/fosstester/licenses/Affero-v1.0';
		$last = exec("$this->copyright -c $file 2>&1", $out, $rtn);
		print "testC: last is:$last\nout is:\n";print_r($out) . "\n";
		return;
	}

	// this function assumes it is being run from within the source
	function testT()
	{
		// has to run out of the source tree.
		if(!chdir('../../agents/copyright'))
		{
			$this->fail("FATAL! could not cd to ../../agents/copyright\n");
		}
		$last = exec("$this->copyright -t 2>&1", $out, $rtn);
		print "testT: last is:$last\nout is:\n";print_r($out) . "\n";
		return;
	}
}
?>
