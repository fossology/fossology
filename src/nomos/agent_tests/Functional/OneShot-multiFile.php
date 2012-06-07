<?php
/***********************************************************
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
 ***********************************************************/
/**
 * \brief Perform a one-shot license analysis on multiple files
 *
 * License returned should be: See the array $Results below.
 *
 * @version "$Id$"
 *
 * Created on March 1, 2012
 */

require_once ('../../../testing/lib/createRC.php');


class OneShotMultiFileTest extends PHPUnit_Framework_TestCase
{
  public $nomos;
  public $files = '../../../testing/dataFiles/TestData/licenses/*';
  public $Results = array(
    'Affero-v1.0' => 'Affero_v1',
    'Apache-v1.1' => 'Apache_v1.1,Public-domain',
  	'ApacheLicense-v2.0' => 'Apache_v2.0',
  	'ApacheV2.0.gz' => 'No_license_found',
  	'BSD_style_a.txt' => 'BSD-style',
  	'BSD_style_b.txt' => 'BSD-style',
  	'BSD_style_c.txt' => 'BSD-style',
  	'BSD_style_d.txt' => 'BSD-style',
  	'BSD_style_e.txt' => 'BSD',
  	'BSD_style_f.txt' => 'BSD-style',
  	'BSD_style_g.txt' => 'BSD-style',
    'BSD_style_h.txt' => 'BSD',
  	'BSD_style_i.txt' => 'BSD-style',
  	'BSD_style_j.txt' => 'BSD-style',
  	'BSD_style_k.txt' => 'BSD-style',
  	'BSD_style_l.txt' => 'BSD-style',
    'BSD_style_m.txt' => 'BSD',
  	'BSD_style_n.txt' => 'BSD-style',
  	'BSD_style_o.txt' => 'BSD',
  	'BSD_style_p.txt' => 'BSD-style',
  	'BSD_style_q.txt' => 'BSD-style',
  	'BSD_style_r.txt' => 'BSD-style',
  	'BSD_style_s.txt' => 'BSD-style',
  	'BSD_style_t.txt' => 'BSD-style',
    'BSD_style_u.txt' => 'BSD-style',
    'BSD_style_v.txt' => 'BSD-style',
    'BSD_style_w.txt' => 'BSD-style',
    'BSD_style_x.txt' => 'BSD-style,Gov\'t-work',
    'BSD_style_y.txt' => 'PHP_v3.0',
    'BSD_style_z.txt' => 'BSD-style',
  	'FILEgpl3.0' => 'FSF,GPL_v3,Public-domain',
  	'FILEgplv2.1' => 'LGPL_v2.1',
  	'OSIzlibLicense-2006-10-31' => 'zlib/libpng',
  	'RCSL_v3.0_a.txt' => 'RCSL_v3.0',
  	'RPSL_v1.0_a.txt' => 'GPL,LGPL,RPSL_v1.0,zlib/libpng',
  	'RPSL_v1.0_b.txt' => 'GPL,LGPL,RPSL_v1.0,zlib/libpng',
  	'agpl-3.0.txt' => 'Affero_v3,Public-domain',
  	'apple.lic' => 'APSL_v2.0',
  	'gpl-3.0.txt' => 'FSF,GPL_v3,Public-domain',
  	'gplv2.1' => 'LGPL_v2.1',
  	'zlibLicense-1.2.2-2004-Oct-03' => 'zlib/libpng',
  );

  function setUp()
  {
    /* check to see if the files exist and load up the array */
    $this->gplv3 = '../../../testing/dataFiles/TestData/licenses/gpl-3.0.txt';
    $this->assertTrue(file_exists('../../../testing/dataFiles/TestData/licenses/'));
    createRC();
    $sysconf = getenv('SYSCONFDIR');
    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
  }

  function testOneShotmultiFile()
  {
    $last = exec("$this->nomos $this->files 2>&1", $out, $rtn);
    //echo "DB: out is:\n";print_r($out) . "\n";
    foreach($out as $nomosResults)
    {
      list(,$fileName,,,$licenses) = preg_split('/[\s]+/', $nomosResults);
      $this->assertArrayHasKey($fileName, $this->Results,
        "Failure, filename $fileName was not found in the master results\n");
      $this->assertContains($licenses, $this->Results[$fileName],
        "Failure, Master results $this->Results[$fileName]\n do not match current results $licenses \n");
    }
  }
}
?>
