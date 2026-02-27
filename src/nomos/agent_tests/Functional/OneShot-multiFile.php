<?php
/*
 SPDX-FileCopyrightText: © 2012-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Perform a one-shot license analysis on multiple files
 *
 * License returned should be: See the array $Results below.
 */

require_once ('CommonCliTest.php');

/**
 * @class OneShotMultiFileTest
 * @brief Perform a one-shot license analysis on multiple files
 */
class OneShotMultiFileTest extends CommonCliTest
{
  /**
   * @var string $files
   * Location of test files
   */
  public $files;
  /**
   * @var array $Results
   * Mapping of files => expected result
   */
  public $Results = array(
    'Affero-v1.0' => 'AGPL-1.0-only',
    'Apache-v1.1' => 'Apache-1.1',
  	'ApacheLicense-v2.0' => 'Apache-2.0',
  	'ApacheV2.0.gz' => 'No_license_found',
  	'BSD_style_a.txt' => 'LicenseRef-BSD-style',
  	'BSD_style_b.txt' => 'LicenseRef-BSD-style',
  	'BSD_style_c.txt' => 'BSD-3-Clause',
  	'BSD_style_d.txt' => 'BSD-3-Clause',
  	'BSD_style_e.txt' => 'BSD',
  	'BSD_style_f.txt' => 'BSD-2-Clause',
  	'BSD_style_g.txt' => 'BSD-3-Clause',
    'BSD_style_h.txt' => 'BSD',
  	'BSD_style_i.txt' => 'BSD-3-Clause',
  	'BSD_style_j.txt' => 'BSD-3-Clause',
  	'BSD_style_k.txt' => 'LicenseRef-Apache-1.1-style',
  	'BSD_style_l.txt' => 'LicenseRef-BSD-style',
    'BSD_style_m.txt' => 'BSD',
  	'BSD_style_n.txt' => 'BSD-3-Clause',
  	'BSD_style_o.txt' => 'BSD',
  	'BSD_style_p.txt' => 'LicenseRef-BSD-style',
  	'BSD_style_q.txt' => 'LicenseRef-BSD-style',
  	'BSD_style_r.txt' => 'LicenseRef-BSD-style',
  	'BSD_style_s.txt' => 'OpenSSL',
  	'BSD_style_t.txt' => 'SSLeay',
    'BSD_style_u.txt' => 'BSD-3-Clause',
    'BSD_style_v.txt' => 'LicenseRef-MIT-CMU-style',
    'BSD_style_w.txt' => 'LicenseRef-BSD-style',
    'BSD_style_x.txt' => 'Govt-work,LicenseRef-BSD-style',
    'BSD_style_y.txt' => 'PHP-3.0',
    'BSD_style_z.txt' => 'OLDAP-2.3',
  	'FILEgpl3.0' => 'GPL-3.0-only',
  	'FILEgplv2.1' => 'LGPL-2.1-only',
  	'OSIzlibLicense-2006-10-31' => 'Zlib',
  	'RCSL_v3.0_a.txt' => 'Dual-license,RCSL-3.0',
  	'RPSL_v1.0_a.txt' => 'RPSL-1.0',
  	'RPSL_v1.0_b.txt' => 'RPSL-1.0',
  	'agpl-3.0.txt' => 'AGPL-3.0-only',
  	'apple.lic' => 'APSL-2.0',
  	'gpl-3.0.txt' => 'GPL-3.0-only',
  	'gplv2.1' => 'LGPL-2.1-only',
  	'zlibLicense-1.2.2-2004-Oct-03' => 'Zlib',
  	'DNSDigest.c' => 'Apache-2.0,GPL,LicenseRef-BSD-style',
  	'Oracle-Berkeley-DB.java' => 'Oracle-Berkeley-DB',
  	'sleepycat.php' => 'Sleepycat',
  	'jslint.js' => 'JSON',
  );

  /**
   * @brief Run NOMOS on multiple files at once
   * @test
   * -# Get the location of test files
   * -# Run NOMOS on the test files and record the output
   * -# For every result output, check if the file is in $Results array
   * -# For every result output, match it using the $Results map
   */
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
      $this->assertStringContainsString($licenses, $expected,
        "Failure, Master results $expected \n do not match current results $licenses \n");
    }
  }
}
