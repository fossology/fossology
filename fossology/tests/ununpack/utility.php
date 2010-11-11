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

$TEST_DATA_PATH = "../../agents/ununpack/tests/test-data/testdata4unpack";
$TEST_RESULT_PATH = "./test_result";
$UNUNPACK_PATH = "../../agents/ununpack/";
$WORK_PATH = "../../tests/ununpack";

/**
 * \brief juge if the file or directory is existed not
 * @param pathName, the file or directory name including path
 * @return existed or not, 0: not existed, 1: existed
 */
function fileDirDexisted($pathName){
  if(is_dir($pathName)) {
    return 1;
  } else if (is_file($pathName)) {
    return 1;
  }
  return 0;
}

?>
