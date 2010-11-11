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
 * \brief test the ununpack agent cli parameters.
 *
 * @group ununpack
 */
require_once '/usr/share/php/PHPUnit/Framework.php';
require_once './utility.php';

print "hello, $TEST_RESULT_PATH, $TEST_DATA_PATH, $UNUNPACK_CMD \n";

class cliParamsTest4Ununpack extends PHPUnit_Framework_TestCase {
  /* command is */
  public function testNormal1(){
    global $UNUNPACK_CMD;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $command = "$UNUNPACK_CMD -qCR $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH";
    $last = exec($command, $usageOut=array(), $rtn=NULL);
    print "testHelp: last is:$last\nusageout is:\n";
    print_r($usageOut) . "\n";
    print "ok, $command\n";
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523SFP/QMFGOEM.TXT");
  }
}


?>
