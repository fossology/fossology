/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 *********************************************************************/

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <CUnit/CUnit.h>
#include <CUnit/Automated.h>

#include <libfossology.h>
#include <libfocunit.h>

/* init and cleanup functions */
int init_agent_suite(void);
int clean_agent_suite(void);

/* test case sets */
extern CU_TestInfo tests_agent[];

/* create test suite */
CU_SuiteInfo suites[] = {
    {"Testing agent.c:", init_agent_suite, clean_agent_suite, tests_agent},
    CU_SUITE_INFO_NULL
};

int main( int argc, char *argv[] )
{
  return focunit_main(argc, argv, "scheduler_Tests", suites) ;
}
