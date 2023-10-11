<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * templatePHPUnit
 * \brief test template for PHPUnit Non-Web tests
 * 
 * Use this template for creating PHPUnit tests that do not use the web.
 * For example, unit tests or cli functional tests that can be run
 * without a web browser.
 * 
 * To run a single PHPUnit test, type phpunit <test-file>
 * 
 * @version "$Id: templatePHPUnit.php 3501 2010-09-27 17:51:12Z rrando $"
 *
 */

// The standard Pear install puts PHPUnit in /usr/share/php/PHPUnit.
require_once '/usr/share/php/PHPUnit/Framework.php';

// Must have if pathinclude.php is used.
global $GlobalReady;
$GlobalReady=TRUE;

class cli1Test extends \PHPUnit\Framework\TestCase
{
	public function testHelp()
	{
		print "Starting testHelp\n";
		
		// determine if the system is installed via Upstream or packages
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
		/*
		 * For this example/template, the nomos agent will be run to get the 
		 * usage statement on the command line.  Replace the code below with
		 * your test.
		 */
		$nomos = $AGENTDIR . '/nomos';
		// run it
		$last = exec("$nomos -h 2>&1", $out, $rtn);
		$error = '/usr/local/lib/fossology/agents/nomos: invalid option -- h';
		$usage = 'Usage: /usr/local/lib/fossology/agents/nomos [options] [file [file [...]]';
		// Use an assertion to check that the output is what was expected.
		$this->assertEquals($error, $out[0]);
		$this->assertEquals($usage, $out[1]);
	}
}
