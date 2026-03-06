// SPDX-License-Identifier: GPL-2.0-only
/*!
 * snippet_scan.c
 *
 * The SCANOSS Agent for Fossology tool
 *
 * Copyright (C) 2018-2025 SCANOSS.COM
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/*!
 * \file snippet_scan.c
 * \date
 * \brief Scanoss agent for Fossology. Scans  for licenses on osskb.org
 */
#define _GNU_SOURCE
#include "snippet_scan.h"
#include <stdio.h>
#include <errno.h>
#include <sys/types.h>
#include <unistd.h>

extern void logme(char *msg);

/**
 * @brief Safely run scanoss-py command using fork+exec instead of popen
 *
 * This function addresses GitHub Issue #3109 where the SCANOSS agent crashes
 * with a segmentation fault in libcrypto.so.3 when executed by the FOSSology
 * scheduler. The crash occurs because popen() inherits the parent's OpenSSL
 * state after fork(), which can be corrupted.
 *
 * By using fork()+execv(), we ensure the child process gets a completely
 * fresh library state, avoiding the inherited corruption.
 *
 * @param pythonPath Path to Python dependencies
 * @param scanossPath Path to scanoss-py executable
 * @param folder Folder to scan
 * @param outputFile Output CSV file path
 * @param apiurl API URL option (or empty string)
 * @param key API key option (or empty string)
 * @return 0 on success, -1 on failure
 */
static int run_scanoss_command(const char *pythonPath, const char *scanossPath,
                                const char *folder, const char *outputFile,
                                const char *apiurl, const char *key)
{
  pid_t pid;
  int status;

  pid = fork();

  if (pid < 0)
  {
    LOG_ERROR("Snippet scan: fork() failed: %s", strerror(errno));
    return -1;
  }

  if (pid == 0)
  {
    /* Child process - completely fresh state, no inherited OpenSSL corruption */

    /* Set PYTHONPATH environment variable */
    if (setenv("PYTHONPATH", pythonPath, 1) != 0)
    {
      _exit(127);
    }

    /* Build argument list for execv */
    /* Maximum args: scanoss-py scan <folder> --format=csv -o <output> [--apiurl X] [--key Y] NULL */
    char *args[12];
    int argc = 0;

    args[argc++] = (char *)scanossPath;
    args[argc++] = "scan";
    args[argc++] = (char *)folder;
    args[argc++] = "--format=csv";
    args[argc++] = "-o";
    args[argc++] = (char *)outputFile;

    /* Add optional API URL if provided */
    if (apiurl != NULL && apiurl[0] != '\0')
    {
      args[argc++] = "--apiurl";
      args[argc++] = (char *)apiurl;
    }

    /* Add optional API key if provided */
    if (key != NULL && key[0] != '\0')
    {
      args[argc++] = "--key";
      args[argc++] = (char *)key;
    }

    args[argc] = NULL;

    /* Execute scanoss-py - this replaces the process entirely */
    execv(scanossPath, args);

    /* If execv returns, it failed */
    _exit(127);
  }

  /* Parent process - wait for child to complete */
  if (waitpid(pid, &status, 0) < 0)
  {
    LOG_ERROR("Snippet scan: waitpid() failed: %s", strerror(errno));
    return -1;
  }

  if (WIFEXITED(status))
  {
    int exit_code = WEXITSTATUS(status);
    if (exit_code != 0)
    {
      LOG_ERROR("Snippet scan: scanoss-py exited with code %d", exit_code);
      return -1;
    }
  }
  else if (WIFSIGNALED(status))
  {
    LOG_ERROR("Snippet scan: scanoss-py killed by signal %d", WTERMSIG(status));
    return -1;
  }

  return 0;
}

extern char *baseTMP;
int Verbose = 0;
PGconn *db_conn = NULL; ///< The connection to Database
extern int Agent_pk;
extern char ApiUrl[200];
extern char accToken[100];

/***************************************************************************/

int splitLine(char *lineToSplit, char *separator, char **fields)
{
  int i = 0;

  char *token;

  char *strSplit = lineToSplit;
  while ((token = strtok_r(strSplit, separator, &strSplit)))
    sprintf(fields[i++], "%s", token);
  return i;
}

void extract_csv(char *out, char *in, int n, long limit, char sep)
{
  char seps[3];
  char line[2048];
  sprintf(line, "%s", in);
  sprintf(seps, "%c", sep);
  char *token = strtok(line, seps);
  // loop through the string to extract all other tokens
  int count = 0;
  while (token != NULL)
  {
    count++; // printf( " %s\n", token ); //printing each token
    if (count == n)
    {
      sprintf(out, "%s", token);
      break;
    }
    token = strtok(NULL, seps);
  }
}

/*!
 * \brief Open a file of the repository given its primary key
 * \param pFileKey the key of the file to be retrieved
 * \return Pointer to the file
 */
FILE *openFileByKey(long pFileKey)
{
  char sqlbuf[200];
  PGresult *result;
  sprintf(sqlbuf, "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile_path FROM  pfile WHERE pfile_pk = %ld;", pFileKey);
  result = PQexec(db_conn, sqlbuf);
  if (fo_checkPQresult(db_conn, result, sqlbuf, __FILE__, __LINE__))
  {

    exit(-1);
  }
  char path[500];
  sprintf(path, "%s", PQgetvalue(result, 0, 0));
  return fo_RepFread("files", path);
}

/**
 * \brief Retrieves the license id (license_ref.rf_pk) given its short name
 */
int getLicenseId(unsigned char *name)
{
  PGresult *result;
  char sqlbuf[500];
  sprintf(sqlbuf, "select lr.rf_pk from license_ref lr where lr.rf_shortname like '%s';", name);
  result = PQexec(db_conn, sqlbuf);

  if (fo_checkPQresult(db_conn, result, sqlbuf, __FILE__, __LINE__))
  {
    return -1;
  }

  int num_rows = PQntuples(result);
  int return_value = -1;
  if (num_rows > 0)
  {
    const char *value = PQgetvalue(result, 0, 0);
    if (value && *value)
    {
      return_value = atoi(value);
    }
    else
    {
      /* Found empty response */
      return_value = -2;
    }
  }
  else
  {
    /* No results found */
    return_value = -3;
  }

  PQclear(result);
  return return_value;
}

/*!
* \brief Dumps the content of a file in the repository to a temporary file
  \param path Path to the temporary file
  \param content Buffer containing the file
  \param size Size of the file to be stored
*/
void dumpToFile(const char *path, unsigned char *content, long size)
{
  FILE *fptr;
  fptr = fopen(path, "w");

  if (fptr == NULL)
  {
    LOG_ERROR("Snippet scan: Could not create temp file")
  }
  else
  {
    fwrite(content, size, 1, fptr);
    fclose(fptr);
  }
}

void RestoreTempFile(char *uploadFolder, long key, long realParent, char *realName)
{
  char dstName[256];
  FILE *f = openFileByKey(key);                                               /* Open the file from the repository by its key */
  sprintf(dstName, "%s/%ld_%ld_%s", uploadFolder, realParent, key, realName); /* Create a name for the temp file, including the temp folder */
  if (f != NULL)
  {
    fseek(f, 0, SEEK_END); /* Read the file and dump the content into the temp file*/
    int size = ftell(f);
    unsigned char *contents = calloc(1, size);
    memset(contents, '\0', size);
    rewind(f);
    size_t readSize = fread(contents, size, 1, f);
    fo_RepFclose(f);
    if (readSize == 0)
      return;
    dumpToFile(dstName, contents, size);
  }
}

/*1 inventory_id,
  2 path,
  3 detected_usage,
  4 detected_component,
  5 detected_license,
  6 detected_version,
  7 detected_latest,
  8 detected_purls,
  9 detected_url,
  10 detected_match,
  11 detected_lines,
  12 detected_oss_lines,
  13 detected_path
*/
/*!
* \brief Parse results from a temporary file and store results on database
  \param folder Path to the temporary project folder
*/

void ParseResults(char *folder)
{
  PGresult *result;
  char Cmd[100];
  char auxSql[MAXCMD * 4];

  char detectedUsage[MAXCMD];
  char detPurls[MAXCMD];
  char detLicenses[MAXCMD];
  char path[MAXCMD];
  char detMatch[MAXCMD];
  char detLines[MAXCMD];
  char detPath[MAXCMD];
  char detUrl[MAXCMD];

  sprintf(Cmd, "%s/results.csv", folder);
  FILE *file = fopen(Cmd, "r");

  char line[MAXCMD * 10];
  int resCount = 0;
  if (file == NULL)
  {
    LOG_ERROR("Error while opening the file");
    return;
  }
  while (fgets(line, sizeof(line), file))
  {
    if (resCount == 0)
    {
      resCount++;
      continue;
    } // Skip header
    if (line[0] != 0 && line[0] != ' ')
    {
      long parent;
      long key;
      char srcName[MAXCMD];
      extract_csv(path, line, 2, MAXCMD, ',');
      
      if (strlen(path)>0)
      {
        sscanf(path, "%ld_%ld_%s", &parent, &key, srcName);
      }
      else
      {
        return;
      }
      int lenLine = strlen(line);
      extract_csv(detectedUsage, line, 3, lenLine, ',');
      extract_csv(detPurls, line, 8, lenLine, ',');
      extract_csv(detLicenses, line, 5, lenLine, ',');
      extract_csv(detUrl, line, 9, lenLine, ',');

      extract_csv(detMatch, line, 10, lenLine, ',');
      extract_csv(detLines, line, 12, lenLine, ',');
      extract_csv(detPath, line, 13, lenLine, ',');

      // License store

      for (int i = 1; i < 5; i++)
      {
        char aux[100000];
        if (strlen(detLicenses) > 1)
        {
          extract_csv(aux, detLicenses, i, strlen(detLicenses), ';');
          if (strlen(aux)<=0) 
            break;
          else
          {

            int detLic = getLicenseId((unsigned char *)aux);
            /* ... from name, get the key of license that matches short_name at license_ref*/
            if (detLic > 0)
            { /* If the key is valid, insert the result on DB Table */
              sprintf(auxSql, "INSERT INTO license_file(rf_fk, agent_fk, rf_timestamp, pfile_fk) VALUES(%d,%d, now(), %ld);", detLic, Agent_pk, key);
              result = PQexec(db_conn, auxSql);
            }
            else
            {
              // Unknown license
            }
          }
        }
      }

      // File info store
      if (strcmp((char *)detectedUsage, "none") && (!(strcmp((char *)detectedUsage, "file")) || !(strcmp((char *)detectedUsage, "snippet"))))
      {
        char *auxSQL;
        asprintf(&auxSQL, "INSERT INTO scanoss_fileinfo (pfile_fk, matchtype, lineranges, purl,filepath,url) VALUES(%ld, '%s', '%s', '%s','%s','%s');", key, detectedUsage, detLines, detPurls, detPath, detUrl); //,url,filePath);
        result = PQexec(db_conn, auxSQL);
        free(auxSQL);
        if (PQntuples(result) == 0)
        {
          PQclear(result);
        }
      }
      resCount++;
    }
    else
    {
      break;
    }
  }
}

/*!
 * \brief Scans a Temporary folder
 * \details Scans a Temporary folder with a rebuild project and places results in results.csv
 *
 * This function was updated to use fork+execv instead of popen() to fix
 * GitHub Issue #3109: SCANOSS agent segfaults in libcrypto.so.3 when
 * executed by the FOSSology scheduler.
 *
 * \param folder path to temp folder
 * \return 0 on success, -1 on failure
 */
int ScanFolder(char *folder)
{
  char pythonPath[512];
  char scanossPath[512];
  char outputFile[512];
  char *apiurlPtr = NULL;
  char *keyPtr = NULL;

  /* Get project user from config */
  char *user = fo_config_get(sysconfig, "DIRECTORIES", "PROJECTUSER", NULL);
  if (user == NULL)
  {
    LOG_ERROR("Snippet scan: PROJECTUSER not configured");
    return -1;
  }

  /* Build paths */
  snprintf(pythonPath, sizeof(pythonPath), "/home/%s/pythondeps/", user);
  snprintf(scanossPath, sizeof(scanossPath), "/home/%s/pythondeps/bin/scanoss-py", user);
  snprintf(outputFile, sizeof(outputFile), "%s/results.csv", folder);

  /* Set API URL if configured */
  if (ApiUrl[0] != '\0')
  {
    apiurlPtr = ApiUrl;
  }

  /* Set API key if configured */
  if (accToken[0] != '\0' && accToken[0] != ' ')
  {
    keyPtr = accToken;
  }

  /* Log the command for debugging */
  char logMsg[MAXCMD];
  snprintf(logMsg, sizeof(logMsg), "Running scanoss-py scan on %s", folder);
  logme(logMsg);

  /* Use the safe fork+exec function to avoid libcrypto crash (Issue #3109) */
  int result = run_scanoss_command(pythonPath, scanossPath, folder, outputFile,
                                    apiurlPtr, keyPtr);

  return result;
}

int RebuildUpload(long upload_pk, char *tempFolder)
{

  char sqlbuf[1024];
  PGresult *result;
  int numrows;
  int i;
  char *uploadtree_tablename;

  if (!upload_pk) /* when upload_pk is empty */
  {
    LOG_ERROR("Snippet scan: Missing upload key");
    return -1;
  }

  uploadtree_tablename = GetUploadtreeTableName(db_conn, upload_pk);
  if (NULL == uploadtree_tablename)
    uploadtree_tablename = strdup("uploadtree_a");
  /*  retrieve the records to process */
  snprintf(sqlbuf, sizeof(sqlbuf),
           "SELECT * from uploadtree_a, upload  where upload_fk = upload_pk  and upload_pk = '%ld' ", upload_pk);
  result = PQexec(db_conn, sqlbuf);
  if (fo_checkPQresult(db_conn, result, sqlbuf, __FILE__, __LINE__))
  {
    LOG_ERROR("Snippet scan: Error retrieving jobs");
    exit(-1);
  }

  numrows = PQntuples(result);
  long parent = 0;
  long realParent = 0;
  long fileMode = 0;
  long pFileFK = 0;
  char *realName;
  /*  for each record, get it name and real parent */
  for (i = 0; i < numrows; i++)
  {

    fo_scheduler_heart(1);
    parent = atoi(PQgetvalue(result, i, 1));
    realParent = atoi(PQgetvalue(result, i, 2));
    fileMode = atol(PQgetvalue(result, i, 5));
    asprintf(&realName, "%s", PQgetvalue(result, i, 8)); // 8 fileName
    for (int j = 0; j < strlen(realName); j++)
    {
      if ((realName[j] >= 'A' && realName[j] <= 'Z') ||
          (realName[j] >= 'a' && realName[j] <= 'z') ||
          (realName[j] >= '0' && realName[j] <= '9') || realName[j] == '.')
        ;
      else
        realName[j] = '_';
    }
    // Nothing to be done on folders entries
    if (parent != realParent && (fileMode == ((1 << 28) | (1 << 13) | (1 << 9))))
    {
    }
    else
    {
      // Ensure that it is a real file
      // fileMode & ((1<<28)|(1<<13)|(1<<9)) == 0
      if (fileMode != ((1 << 28) | (1 << 13) | (1 << 9)))
      {
        pFileFK = atoi(PQgetvalue(result, i, 4));
        if (pFileFK != 0)
        {
          RestoreTempFile(tempFolder, pFileFK, parent, realName);
        }
      }
    }
    free(realName);
  }
  PQclear(result);

  return (0);
}

/***********************************************
 Usage():
 Command line options allow you to write the agent so it works
 stand alone, in addition to working with the scheduler.
 This simplifies code development and testing.
 So if you have options, have a Usage().
 Here are some suggested options (in addition to the program
 specific options you may already have).
 ***********************************************/
void Usage(char *Name)
{
  printf("Usage: %s [file|folder]\n", Name);
  printf("  -i   :: initialize the database, then exit.\n");
  printf("  -v   :: verbose (-vv = more verbose)\n");
  printf("  -c   :: Specify the directory for the system configuration.\n");
  printf("  -C   <file path/folder path> :: run from command line.\n");
  printf("  -V   :: print the version info, then exit.\n");
} /* Usage() */
