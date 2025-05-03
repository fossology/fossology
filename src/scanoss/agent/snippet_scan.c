// SPDX-License-Identifier: GPL-2.0-only
/*!
 * snippet_scan.c
 *
 * The SCANOSS Agent for Fossology tool
 *
 * Copyright (C) 2018-2021 SCANOSS.COM
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

extern void logme(char *msg);

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
 * \details Scans a Temporary folder with a rebuild project and place it results on a file results.csv
 * \param folder path to temp folder
 */
int ScanFolder(char *folder)
{

  FILE *Fin;
  char Cmd[MAXCMD];
  memset(Cmd, '\0', MAXCMD);

  unsigned char apiurl[400];
  unsigned char key[110];

  if (ApiUrl[0] != '\0')
  {
    sprintf((char *)apiurl, "--apiurl %s", ApiUrl);
  }
  else
    memset(apiurl, 0, sizeof(apiurl));

  if (accToken[0] != '\0' && accToken[0] != ' ')
  {
    sprintf((char *)key, "--key %s", accToken);
  }
  else
    memset(key, 0, sizeof(key));

  char *user;
  asprintf(&user, "%s", fo_config_get(sysconfig, "DIRECTORIES", "PROJECTUSER", NULL));

  sprintf(Cmd, "PYTHONPATH='/home/%s/pythondeps/' /home/%s/pythondeps/bin/scanoss-py scan  %s --format=csv -o %s/results.csv %s %s", user, user, folder, folder, apiurl, key); /* Create the command to run */
  logme(Cmd);
  free(user);
  Fin = popen(Cmd, "r"); /* Run the command */
  if (!Fin)
  {
    LOG_ERROR("Snippet scan: failed to start scan %s", strerror(errno));
    pclose(Fin);
    return -1;
  }
  else
  {
    pclose(Fin);
  }
  return 0;
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
