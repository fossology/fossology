<?php

/*
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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
 * \brief test the ununpack agent cli parameters.
 *
 * @group ununpack
 */
require_once './utility.php';

class cliParamsTest4UnunpackExcption extends PHPUnit_Framework_TestCase {
  /* command is */
  public function testValidParam(){
    print "test unpack with an invalid parameter\n";
    global $UNUNPACK_CMD;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    
    $UNUNPACK_CMD = "../../agent/ununpack";
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $command = "$UNUNPACK_CMD -qCRs $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH -c /usr/local/etc/fossology/ > /dev/null 2>&1";
    $last = exec($command, $usageOut, $rtn);
    $this->assertNotEquals($rtn, 0);
    $this->assertFileNotExists("$TEST_RESULT_PATH/523.iso.dir/523SFP/QMFGOEM.TXT");
  }
}


?>
