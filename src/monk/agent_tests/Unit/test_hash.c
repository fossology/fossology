/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>

#include "hash.h"

void test_hash1() {
  CU_ASSERT_NOT_EQUAL(hash(""), hash("t"));
  CU_ASSERT_NOT_EQUAL(hash("t"), hash("test"));
  CU_ASSERT_NOT_EQUAL(hash("test"), hash("test1"));
}

void test_hash2() {
  CU_ASSERT_EQUAL(hash("a\0b"), hash("a"));
}

CU_TestInfo hash_testcases[] = {
  {"Testing extracting Hash test1:", test_hash1},
  {"Testing extracting Hash test2:", test_hash2},
  CU_TEST_INFO_NULL
};
