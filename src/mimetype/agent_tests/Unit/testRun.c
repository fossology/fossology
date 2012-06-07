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

char *DBConfFile = NULL;

/**
 * \brief all test suites for mimetype
 */
CU_SuiteInfo suites[] = {
    // for finder.c
    {"DBCheckMime", DBCheckMimeInit, DBCheckMimeClean, testcases_DBCheckMime},
#if 0
#endif
    {"DBLoadMime", DBLoadMimeInit, DBLoadMimeClean, testcases_DBLoadMime},
    {"DBFindMime", DBFindMimeInit, DBFindMimeClean, testcases_DBFindMime},
    {"CheckMimeType", DBInit, DBClean, testcases_CheckMimeTypes},
    {"DBCheckFileExtention", DBInit, DBClean, testcases_DBCheckFileExtention},
    {"Utilities", NULL, NULL, testcases_Utilities},
    CU_SUITE_INFO_NULL
};

/*
 * \brief  main test function
 */
int main( int argc, char *argv[] )
{
  create_db_repo_sysconf(1, "mimetype");
  DBConfFile = get_dbconf();

  int rc = focunit_main(argc, argv, "mimetype_Tests", suites) ;
  drop_db_repo_sysconf(get_db_name());
  return rc;
}

