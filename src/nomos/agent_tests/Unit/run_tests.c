/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test cases for Nomos
 * \dir
 * \brief Unit test cases for Nomos
 */

/* local includes */
#include <libfocunit.h>

/* std library includes */
#include <stdio.h>
#include <assert.h>

/* cunit includes */
#include <CUnit/CUnit.h>
#include <CUnit/Automated.h>

#include "nomos.h"
#include "util.h"
#include "list.h"
#include "licenses.h"
#include "process.h"
#include "nomos_regex.h"
#include "_autodefs.h"
//nomos globals
extern licText_t licText[]; /**< Defined in _autodata.c */
struct globals gl;
struct curScan cur;

/* ************************************************************************** */
/* **** test case sets ****************************************************** */
/* ************************************************************************** */

extern CU_TestInfo nomos_gap_testcases[];
extern CU_TestInfo doctorBuffer_testcases[];
/* ************************************************************************** */
/* **** create test suite *************************************************** */
/* ************************************************************************** */

#if CU_VERSION_P == 213
CU_SuiteInfo suites[] =
{
    {"Testing process:", NULL, NULL, NULL, NULL, nomos_gap_testcases},
    {"Testing doctor Buffer:", NULL, NULL, NULL, NULL, doctorBuffer_testcases},
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] =
{
    {"Testing process:", NULL, NULL, nomos_gap_testcases},
    {"Testing doctor Buffer:", NULL, NULL, doctorBuffer_testcases},
    CU_SUITE_INFO_NULL
};
#endif

/* ************************************************************************** */
/* **** main test functions ************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  return focunit_main(argc, argv, "nomos_Util_Tests", suites);
}
