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
 * @todo add in the no parameters test, does copyright need to be
 * killed?
 *
 * @group copyright
 *
 * @version "$Id: cliParamsTest.php 3613 2010-10-27 03:12:41Z rrando $"
 *
 * Created on Oct 20, 2010
 */
require_once '/usr/share/php/PHPUnit/Framework.php';

global $GlobalReady;
$GlobalReady = TRUE;

class cliParamsTest extends PHPUnit_Framework_TestCase {

	public $agentDir;
	public $copyright;

	function setUp() {
		global $GlobalReady;

    $AGENTDIR = NULL;
		// determine where the agents are installed
		$upStream = '/usr/local/share/fossology/php/pathinclude.php';
		$pkg = '/usr/share/fossology/php/pathinclude.php';

		if (file_exists($upStream)) {
			require $upStream;
			//print "agentdir is:$AGENTDIR\n";
			$this->agentDir = $AGENTDIR;
			$this->copyright = $this->agentDir . '/copyright';
		} else
			if (file_exists($pkg)) {
				require $pkg;
				//print "agentdir is:$AGENTDIR\n";
				$this->agentDir = $AGENTDIR;
				$this->copyright = $this->agentDir . '/copyright';
			} else {
				$this->assertFileExists($upStream, $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
			}
		//print "agent:$this->agentDir\ncopy:$this->copyright\n";
		return;
	} // setUP

	function testHelp() {
		// copyright -h
		$last = exec("$this->copyright -h 2>&1", $usageOut=array(), $rtn=NULL);
		//print "testHelp: last is:$last\nusageout is:\n";
		//print_r($usageOut) . "\n";
    // Check a couple of options for sanity
		$usage = 'Usage: /usr/local/lib/fossology/agents/copyright [options]';
    $dashD = '-d  :: Turns verbose on, matches printed to Matches file.';
    $this->assertEquals($usage, $usageOut[1]);
    $this->assertEquals($dashD, trim($usageOut[3]));
		return;
	}

	function testC() {
		// copyright -c file

    $expected = array(
        '/home/fosstester/licenses/Affero-v1.0',
        '[53:82] copyright    2002 affero inc.',
        '[212:277] copyright (c) 1989, 1991 free software foundation, inc. made with'
         );
		$file = '/home/fosstester/licenses/Affero-v1.0';
		$last = exec("$this->copyright -c $file 2>&1", $got=array(), $rtn=NULL);
    $gcount = count($got);
    for($i=0; $i<$gcount; $i++)
    {
      $this->assertEquals($expected[$i], trim($got[$i]));
    }
		//print "testC: last is:$last\nout is:\n";
		//print_r($out) . "\n";
		return;
	}

	/**
   * this function assumes it is being run from within the source
   *
   * Not sure this is a very good test, but it is part of the cli/UI.
   * Use it as a baseline for now.
	 */
	function testT()
  {
		// has to run out of the source tree.

    $expected = array(
        'Total Found:     1482',
        'Correct:         1404',
        'False Positives: 78',
        'False Negatives: 0');

		if (!chdir('../../agents/copyright')) {
			$this->fail("FATAL! could not cd to ../../agents/copyright\n");
		}
		$last = exec("$this->copyright -t 2>&1", $accuracy=array(), $rtn=NULL);
		//print "testT: last is:$last\naccuracy is:\n";
		//print_r($accuracy) . "\n";
    $size = count($accuracy);
    $start = $size-4;
    //print "starting at:{$accuracy[$start]}\n";
    $j = 0;
    for($i=$start; $i<$size; $i++)
    {
      $this->assertEquals($expected[$j],$accuracy[$i]);
      $j++;
    }
		return;
	}
}
?>
