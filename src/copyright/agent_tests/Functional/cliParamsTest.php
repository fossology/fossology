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
 * @version "$Id$"
 *
 * Created on Oct 20, 2010
 */

class cliParamsTest extends PHPUnit_Framework_TestCase {

	public $agentDir;
	public $copyright;

	function setUp() {
        $this->agentDir = '../../agent';
        $this->copyright = $this->agentDir .'/copyright';
		return;
	} // setUP

	function testHelp() {
		// copyright -h
		$last = exec("$this->copyright -h 2>&1", $usageOut, $rtn=NULL);
		//print "testHelp: last is:$last\nusageout is:\n";
		//print_r($usageOut) . "\n";
    // Check a couple of options for sanity
		$usage = 'Usage: ../../agent/copyright [options]';
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
      'Total Found:     1506',
      'Correct:         1403',
      'False Positives: 103',
      'False Negatives: 0');

        // make agent_tests the working directory.
        // -t requires the working dir to contain testdata/
		if (!chdir("..")) {
			$this->fail("FATAL! could not cd to agent_tests\n");
		}
		$last = exec("../agent/copyright -t 2>&1", $accuracy, $rtn=NULL);
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
