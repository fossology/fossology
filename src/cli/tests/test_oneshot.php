<?php
/*
 SPDX-FileCopyrightText: © 2013-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief test for one-shot nomos/copyright
 */

class test_oneshot extends \PHPUnit\Framework\TestCase
{

  /**
   * \brief for oneshot nomos
   */
  function test_oneshot_nomos()
  {
    $command = "wget -qO - --post-file ./test_oneshot.php http://fossology.usa.hp.com/noauth/?mod=agent_nomos_once";
    $output = array();
    $license = " GPL-2.0";

    exec($command, $output);
    $this->assertEquals($license, $output[0]);
  }

  /**
   * \brief for oneshot copyright
   */
  function test_oneshot_copyright()
  {
    $command = "wget -qO - --post-file ./test_oneshot.php http://fossology.usa.hp.com/noauth/?mod=agent_copyright_once";
    $output = array();
    $license = "copyright (c) 2013-2014 hewlett-packard development company, l.p.";

    exec($command, $output);
    $this->assertEquals($license, $output[0]);
  }
}
