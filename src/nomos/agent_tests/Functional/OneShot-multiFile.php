<?php
/***********************************************************
 Copyright (C) 2012-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
 */

require_once ('CommonCliTest.php');

class OneShotMultiFileTest extends CommonCliTest
{
  public $files;
  public $Results = array(
    'Affero-v1.0' => 'AGPL-1.0',
    'Apache-v1.1' => 'Apache-1.1',
  	'ApacheLicense-v2.0' => 'Apache-2.0',
  	'ApacheV2.0.gz' => 'No_license_found',
  	'BSD_style_a.txt' => 'BSD-style',
  	'BSD_style_b.txt' => 'BSD-style',
  	'BSD_style_c.txt' => 'BSD-3-Clause',
  	'BSD_style_d.txt' => 'BSD-3-Clause',
  	'BSD_style_e.txt' => 'BSD',
  	'BSD_style_f.txt' => 'BSD-2-Clause',
  	'BSD_style_g.txt' => 'BSD-3-Clause',
    'BSD_style_h.txt' => 'BSD',
  	'BSD_style_i.txt' => 'BSD-3-Clause',
  	'BSD_style_j.txt' => 'BSD-3-Clause',
  	'BSD_style_k.txt' => 'BSD-2-Clause',
  	'BSD_style_l.txt' => 'BSD-style',
    'BSD_style_m.txt' => 'BSD',
  	'BSD_style_n.txt' => 'BSD-3-Clause',
  	'BSD_style_o.txt' => 'BSD',
  	'BSD_style_p.txt' => 'BSD-style',
  	'BSD_style_q.txt' => 'BSD-style',
  	'BSD_style_r.txt' => 'BSD-style',
  	'BSD_style_s.txt' => 'OpenSSL',
  	'BSD_style_t.txt' => 'BSD-style',
    'BSD_style_u.txt' => 'BSD-3-Clause',
    'BSD_style_v.txt' => 'BSD-style',
    'BSD_style_w.txt' => 'BSD-style',
    'BSD_style_x.txt' => 'BSD-style,Govt-work',
    'BSD_style_y.txt' => 'PHP-3.0',
    'BSD_style_z.txt' => 'OLDAP-2.3',
  	'FILEgpl3.0' => 'GPL-3.0+',
  	'FILEgplv2.1' => 'LGPL-2.1+',
  	'OSIzlibLicense-2006-10-31' => 'Zlib',
  	'RCSL_v3.0_a.txt' => 'Dual-license,RCSL-3.0',
  	'RPSL_v1.0_a.txt' => 'RPSL-1.0',
  	'RPSL_v1.0_b.txt' => 'RPSL-1.0',
  	'agpl-3.0.txt' => 'AGPL-3.0',
  	'apple.lic' => 'APSL-2.0',
  	'gpl-3.0.txt' => 'GPL-3.0+',
  	'gplv2.1' => 'LGPL-2.1+',
  	'zlibLicense-1.2.2-2004-Oct-03' => 'Zlib',
  	'DNSDigest.c' => 'Apache-2.0,BSD-style,GPL',
  	'Oracle-Berkeley-DB.java' => 'Oracle-Berkeley-DB',
  	'sleepycat.php' => 'Sleepycat',
  	'jslint.js' => 'JSON',
  );

  public function testOneShotmultiFile()
  {
    $this->files = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/*';
    /* check to see if the files exist and load up the array */
    $this->gplv3 = dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/gpl-3.0.txt';
    $this->assertTrue(file_exists(dirname(dirname(dirname(dirname(__FILE__)))).'/testing/dataFiles/TestData/licenses/'));

    list($output,) = $this->runNomos($this->files);
    $out = explode("\n", trim($output));

    foreach($out as $nomosResults)
    {
      list(,$fileName,,,$licenses) = preg_split('/[\s]+/', $nomosResults);
      $this->assertArrayHasKey($fileName, $this->Results,
        "Failure, filename $fileName was not found in the master results\n");
      $expected  = $this->Results[$fileName];
      $this->assertContains($licenses, $expected,
        "Failure, Master results $expected \n do not match current results $licenses \n");
    }
  }
}
