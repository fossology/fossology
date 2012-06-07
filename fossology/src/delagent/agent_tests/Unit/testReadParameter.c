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

/* cunit includes */
#include <CUnit/CUnit.h>
#include "delagent.h"
#include <string.h>

extern char *DBConfFile;

/**
 * \file testReadParameter.c
 * \brief testing for the function ReadParameter
 */

/* test functions */

/**
 * \brief for function ReadParameter
 */
void testReadParameter()
{
  char *Parm = "LIST UPLOAD 85";
  int result;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  /** exectue the tested function */
  result = ReadParameter(Parm);
  PQfinish(db_conn);
  CU_ASSERT_EQUAL(result, 1);
}

/**
 * \brief testcases for function ReadParameter
 */
CU_TestInfo testcases_ReadParameter[] =
{
#if 0
#endif
{"Testing the function ReadParameter:", testReadParameter},
  CU_TEST_INFO_NULL
};

