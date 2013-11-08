/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/
/**
 * \file process.c
 * \brief Functions to process a single file and process an upload
 */

#include "demomod.h"

/**********  Globals  *************/
extern psqlCopy_t psqlcpy;       // fo_sqlCopy struct used for fast data insertion
extern PGconn    *pgConn;        // database connection

/**
 * @brief Process a single file - read the first 32 bytes 
 * @param FilePath Path of the file to read.
 * @param FileResult Structure to save the file result (32 bytes)
 *
 * @returns 0 if success, may also write fatal error to stderr.
 */
FUNCTION int ProcessFile(char *FilePath, pFileResult_t FileResult) 
{
  int   rv;             // generic renurn value
  FILE *fin;

  LOG_VERBOSE("ProcessFile: %s",FilePath);

  /* Open the file */
  fin = fopen(FilePath, "r");
  if (!fin)
  {
    LOG_ERROR("FATAL: %s.%s.%d Failure to open file %s.\nError: %s\n",
               __FILE__, "ProcessFile()", __LINE__, FilePath, strerror(errno));
    return -1;
  }

  /* Read the first buffer's worth from the file, ignoring errors to simplify the demo */
  rv = fread(FileResult->Buf, sizeof(char), sizeof(FileResult->Buf), fin);

  /* Convert to a hex string and save in the FileResult */
  Char2Hex(FileResult->Buf, DataSize, FileResult->HexStr);

  /* Close the file */
  fclose(fin);

  /* Return Success */
  return 0;
}


/**
 * @brief Process a single upload - read the first 32 bytes in each file
 * @param upload_pk
 * @param agent_fk version of the agent that is processing this upload
 *
 * @returns 0 if success, may also write fatal error to stderr.
 */
FUNCTION int ProcessUpload(int upload_pk, int agent_fk)
{
  PGresult* result; // the result of the database access
  int i;
  int rv;             // generic return value
  int numrows;             // generic return value
  int pfile_pk;
  char *FilePath;   // complete path to file in the repository
  char *uploadtree_tablename;
  char  LastChar;
  char  sqlbuf[1024];
  char  FileName[128];
  char  DataBuf[128];
  char *RepoArea = "files";
//  char  *ufile_mode = "30000000";  // This mode is for artifacts and containers (which will be excluded)
  char  *ufile_mode = "10000000";  // This mode is for artifacts only (which will be excluded)
  FileResult_t FileResult;

  /* Select each upload filename (repository filename) that hasn't been processed by this agent yet */
  char* SelectFilename_sql = "\
        SELECT pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename \
          FROM ( SELECT distinct(pfile_fk) AS PF  FROM uploadtree \
                  WHERE upload_fk = %d and (ufile_mode&x'%s'::int)=0 \
               ) AS SS \
          left outer join demomod on (PF = pfile_fk ) \
          inner join pfile on (PF = pfile_pk) \
          WHERE demomod_pk IS null ";
  char* SelectFilename2_sql = "\
        SELECT pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename \
          FROM ( SELECT distinct(pfile_fk) AS PF  FROM %s \
                  WHERE (ufile_mode&x'%s'::int)=0 \
               ) AS SS \
          left outer join demomod on (PF = pfile_fk ) \
          inner join pfile on (PF = pfile_pk) \
          WHERE demomod_pk IS null or agent_fk <> %d";

  /* Find the correct uploadtree table name */
  uploadtree_tablename = GetUploadtreeTableName(pgConn, upload_pk);
  if (!uploadtree_tablename)
  {
    LOG_FATAL("demomod passed invalid upload, upload_pk = %d", upload_pk);
    return(-110);
}

  /* If the last character of the uploadtree_tablename is a digit, then we don't need upload_fk
   * in the query (because the table only has that uplaod).
   */
  LastChar = uploadtree_tablename[strlen(uploadtree_tablename)-1];
  if (LastChar >= '0' && LastChar <= '9')
  {
    snprintf(sqlbuf, sizeof(sqlbuf), SelectFilename2_sql, uploadtree_tablename, ufile_mode, agent_fk);
  }
  else
  {
    snprintf(sqlbuf, sizeof(sqlbuf), SelectFilename_sql, upload_pk, ufile_mode, agent_fk);
  }
  free(uploadtree_tablename);

  /* retrieve the records to process */
  result = PQexec(pgConn, sqlbuf);
  if (fo_checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) ExitNow(-100);
  numrows = PQntuples(result);

  /* process all files in this upload */
  for (i=0; i<numrows; i++)
  {
    strcpy(FileName, PQgetvalue(result, i, 1));
    pfile_pk = atoi(PQgetvalue(result, i, 0));
    FilePath = fo_RepMkPath(RepoArea, FileName);
    if (!FilePath)
    {
      LOG_FATAL("demomod was unable to derive a file path for pfile %d.  Check your HOSTS configuration.", pfile_pk);
      return(-111);
    }

    rv = ProcessFile(FilePath, &FileResult);
    if (rv == 0) 
    {
      fo_scheduler_heart(1);  // Tell the scheduler that we are alive and update item count

      /* Update the database (through fo_sqlCopyAdd buffered copy, this is much faster than single inserts) */
      snprintf(DataBuf, sizeof(DataBuf), "%d\t%d\t%s\n", pfile_pk, agent_fk, FileResult.HexStr);
      fo_sqlCopyAdd(psqlcpy, DataBuf);
    }
  }
  PQclear(result);
  return(0);  // success
}
