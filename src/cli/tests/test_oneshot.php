  <?php
  /*
   Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
   * \brief test for one-shot nomos/copyright
   */

  class test_oneshot extends PHPUnit_Framework_TestCase {


    /**
     * \brief for oneshot nomos
     */
    function test_oneshot_nomos() 
    {
      $command = "wget -qO - --post-file ./test_oneshot.php http://fossology.usa.hp.com/noauth/?mod=agent_nomos_once";
      $output = array();
      $license = "GPL-v2";

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
      $license = "copyright (c) 2013 hewlett-packard development company, l.p.";

      exec($command, $output);
      $this->assertEquals($license, $output[0]);
    }

}

?>
