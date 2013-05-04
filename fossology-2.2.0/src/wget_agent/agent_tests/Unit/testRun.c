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

#include "testRun.h"

/**
 * \file testRun.c
 * \brief main function for in this testing module
 */

/**
 * \brief all test suites for wget agent
 */
CU_SuiteInfo suites[] = {
    // for wget_agent.c
#if 0
#endif
    {"GetURL", GetURLInit, GetURLClean, testcases_GetURL},
    {"SetEnv", SetEnvInit, SetEnvClean, testcases_SetEnv},
    {"Utiliies", NULL, NULL, testcases_Utiliies},
    {"DBLoadGold", DBLoadGoldInit, DBLoadGoldClean, testcases_DBLoadGold},
    CU_SUITE_INFO_NULL
};

/*
 * \brief  main test function
 */
int main( int argc, char *argv[] )
{
  return focunit_main(argc, argv, "wget_agent_Tests", suites) ;
}

