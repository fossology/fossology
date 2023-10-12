#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Describe your PHP program or function here
 *
 * @param
 *
 * @version "$Id: $"
 *
 * Created on Jun 20, 2008
 */

require_once ('./GpTestSuite.php');

list($me, $infile) = $argv;
$test = new GpClassTestSuite();
if (TextReporter :: inCli())
{
  exit ($test->run(new TextReporter()) ? 0 : 1);
}
$test->run(new HtmlReporter());
