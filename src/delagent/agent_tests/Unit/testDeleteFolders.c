/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "delagent.h"
#include <string.h>

extern char *DBConfFile;
static PGresult *result = NULL;

/**
 * \file testDeleteFolders.c
 * \brief testing for the function DeleteFolders and DeleteUploads
 */

/**
 * \brief for function DeleteFolders
 * \test
 * -# Give a folder id to deleteFolder()
 * -# Check for return code
 */
void testDeleteFolders()
{
  long FolderId = 3;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;
  int rc;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  /** exectue the tested function */
  rc = deleteFolder(3, FolderId, 3, 10);

  PQfinish(pgConn);
  CU_ASSERT_EQUAL(rc, 0);
  CU_PASS("DeleteFolders PASS!");
}

/**
 * \brief for function DeleteUploads
 * \test
 * -# Delete an upload using deleteUpload() and check for the return
 * -# Check if the upload is delete from database
 * -# Check if the copyrights for the given upload is also deleted
 * -# Check if the repository files owned only by that upload are removed
 * -# Check false input is rejected
 */
void testDeleteUploads()
{
  long UploadId = 2;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;
  char sql[1024];
  char *pfile;
  int rc, Row, maxRow, foundFileBeforeDelete;
  PGresult *pfileResult;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);

  /* Get the pfiles (sha1.md5.size) that are only referenced by this
   upload, i.e. the ones deleteUpload() is expected to remove from the
   repository. This mirrors the query deleteUpload() itself runs. */
  memset(sql, '\0', 1024);
  snprintf(sql, 1024,
    "SELECT DISTINCT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM uploadtree INNER JOIN pfile ON upload_fk = %ld AND pfile_fk = pfile_pk "
    "WHERE pfile_pk NOT IN "
      "(SELECT pfile_fk FROM uploadtree WHERE upload_fk != %ld);",
    UploadId, UploadId);
  pfileResult = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, pfileResult, sql, __FILE__, __LINE__))
  {
    CU_FAIL("DeleteUploads FAIL! Could not fetch pfiles for upload.");
  }
  maxRow = PQntuples(pfileResult);
  CU_ASSERT_TRUE(maxRow > 0);

  /* Sanity check: the test fixture must actually have at least one of
   these files on disk before delete, otherwise the post-delete "gone"
   assertion below would trivially pass even if deleteUpload() never
   touched the repository at all. */
  foundFileBeforeDelete = 0;
  for (Row = 0; Row < maxRow; Row++)
  {
    pfile = PQgetvalue(pfileResult, Row, 0);
    if (fo_RepExist("files", pfile) || fo_RepExist("gold", pfile))
    {
      foundFileBeforeDelete = 1;
    }
  }
  CU_ASSERT_TRUE(foundFileBeforeDelete);

  /** exectue the tested function */
  rc = deleteUpload(UploadId, 3, 10);
  CU_ASSERT_EQUAL(rc, 0);

  /* check if uploadtree records deleted */
  memset(sql, '\0', 1024);
  snprintf(sql, 1024, "SELECT * FROM uploadtree WHERE upload_fk = %ld;", UploadId);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__))
  {
    CU_FAIL("DeleteUploads FAIL!");
  }
  else
  {
    CU_ASSERT_EQUAL(PQntuples(result),0);
  }
  PQclear(result);

  /* check if copyright records deleted */
  memset(sql, '\0', 1024);
  snprintf(sql, 1024, "SELECT * FROM copyright C INNER JOIN uploadtree U ON C.pfile_fk = U.pfile_fk AND U.upload_fk = %ld;", UploadId);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__))
  {
    CU_FAIL("DeleteUploads FAIL!");
  }
  else
  {
    CU_ASSERT_EQUAL(PQntuples(result),0);
  }
  PQclear(result);

  /* Check that the repository files owned only by this upload were
   actually removed once deleteUpload() reports success. This is the
   regression check for the DB/repository consistency fix: deleteUpload()
   must not report success (or leave the DB rows deleted) while the
   corresponding repository content is still left behind, and it must
   not remove repository content while leaving the DB rows referencing
   it (covered above, since rc == 0 and the DB assertions already passed
   by the time we get here). */
  for (Row = 0; Row < maxRow; Row++)
  {
    pfile = PQgetvalue(pfileResult, Row, 0);
    CU_ASSERT_FALSE(fo_RepExist("files", pfile));
    CU_ASSERT_FALSE(fo_RepExist("gold", pfile));
  }
  PQclear(pfileResult);

  /** Check false input */
  UploadId = 4;
  rc = deleteUpload(UploadId, 2, 10);
  CU_ASSERT_NOT_EQUAL(rc, 0);

  PQfinish(pgConn);
  CU_PASS("DeleteUploads PASS!");
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
  CU_TEST_INFO_NULL
};

