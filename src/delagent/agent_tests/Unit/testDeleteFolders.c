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
 * \file testDeleteFolders.c
 * \brief testing for the function DeleteFolders and DeleteUploads
 */

/**
 * \brief for function DeleteFolders
 */
void testDeleteFolders()
{
  long FolderId = 5;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  /** exectue the tested function */
  DeleteFolder(FolderId);

  PQfinish(db_conn);
  CU_PASS("DeleteFolders PASS!");
}

/**
 * \brief for function DeleteUploads
 */
void testDeleteUploads()
{
  long UploadId = 85;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  /** exectue the tested function */
  DeleteUpload(UploadId);

  PQfinish(db_conn);
  CU_PASS("DeleteUploads PASS!");
}

/**
 * \brief for function DeleteLicenses
 */
void testDeleteLicenses()
{
  long UploadId = 85;
  char *ErrorBuf;
  
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  /** exectue the tested function */
  DeleteLicense(UploadId);
  
  PQfinish(db_conn);
  CU_PASS("DeleteLicenses PASS!");
}

/**
 * \brief testcases for function Delete
 */
CU_TestInfo testcases_DeleteFolders[] =
{
#if 0
#endif
{"Testing the function DeleteFolders:", testDeleteFolders},
{"Testing the function DeleteUploads:", testDeleteUploads},
{"Testing the function DeleteLicenses:", testDeleteLicenses}, 
  CU_TEST_INFO_NULL
};

