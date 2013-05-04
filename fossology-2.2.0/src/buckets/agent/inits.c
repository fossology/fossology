/***************************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * \file inits.c
 * \brief Bucket agent initialization and lookup functions
 */

#include "buckets.h"
extern int debug;

/**
 * \brief Get a bucketpool_pk based on the bucketpool_name
 *
 * \param PGconn $pgConn  Database connection object
 * \param char $bucketpool_name
 *
 * \return active bucketpool_pk or 0 if error
****************************************************/
FUNCTION int getBucketpool_pk(PGconn *pgConn, char *bucketpool_name)
{
  char *fcnName = "getBucketpool";
  int bucketpool_pk=0;
  char sqlbuf[128];
  PGresult *result;

  /* Skip file if it has already been processed for buckets. */
  sprintf(sqlbuf, "select bucketpool_pk from bucketpool where (bucketpool_name='%s') and (active='Y') order by version desc", 
          bucketpool_name);
  result = PQexec(pgConn, sqlbuf);
  if (fo_checkPQresult(pgConn, result, sqlbuf, fcnName, __LINE__)) return 0;
  if (PQntuples(result) > 0) bucketpool_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  return bucketpool_pk;
}


/**
 * \brief Initialize the bucket definition list
 * If an error occured, write the error to stdout
 *
 * \param PGconn $pgConn  Database connection object
 * \param int $bucketpool_pk
 * \param cacheroot_t $pcroot  license cache root
 *
 * \return an array of bucket definitions (in eval order)
 * or 0 if error.
 */
FUNCTION pbucketdef_t initBuckets(PGconn *pgConn, int bucketpool_pk, cacheroot_t *pcroot)
{
  char *fcnName = "initBuckets";
  char sqlbuf[256];
  char filepath[256];
  char hostname[256];
  PGresult *result;
  pbucketdef_t bucketDefList = 0;
  int  numRows, rowNum;
  int  rv, numErrors=0;
  struct stat statbuf;

  /* reasonable input validation  */
  if ((!pgConn) || (!bucketpool_pk)) 
  {
    printf("ERROR: %s.%s.%d Invalid input pgConn: %lx, bucketpool_pk: %d.\n",
            __FILE__, fcnName, __LINE__, (unsigned long)pgConn, bucketpool_pk);
    return 0;
  }

  /* get bucket defs from db */
  sprintf(sqlbuf, "select bucket_pk, bucket_type, bucket_regex, bucket_filename, stopon, bucket_name, applies_to from bucket_def where bucketpool_fk=%d order by bucket_evalorder asc", bucketpool_pk);
  result = PQexec(pgConn, sqlbuf);
  if (fo_checkPQresult(pgConn, result, sqlbuf, fcnName, __LINE__)) return 0;
  numRows = PQntuples(result);
  if (numRows == 0) /* no bucket recs for pool?  return error */
  {
    printf("ERROR: %s.%s.%d No bucket defs for pool %d.\n",
            __FILE__, fcnName, __LINE__, bucketpool_pk);
    PQclear(result);
    return 0;
  }

  bucketDefList = calloc(numRows+1, sizeof(bucketdef_t));
  if (bucketDefList == 0)
  {
    printf("ERROR: %s.%s.%d No memory to allocate %d bucket defs.\n",
            __FILE__, fcnName, __LINE__, numRows);
    return 0;
  }

  /* put each db bucket def into bucketDefList in eval order */
  for (rowNum=0; rowNum<numRows; rowNum++)
  {
    bucketDefList[rowNum].bucket_pk = atoi(PQgetvalue(result, rowNum, 0));
    bucketDefList[rowNum].bucket_type = atoi(PQgetvalue(result, rowNum, 1));
    bucketDefList[rowNum].bucketpool_pk = bucketpool_pk;

    /* compile regex if type 3 (REGEX) */
    if (bucketDefList[rowNum].bucket_type == 3)
    {
      rv = regcomp(&bucketDefList[rowNum].compRegex, PQgetvalue(result, rowNum, 2), 
                   REG_NOSUB | REG_ICASE | REG_EXTENDED);
      if (rv != 0)
      {
        printf("ERROR: %s.%s.%d Invalid regular expression for bucketpool_pk: %d, bucket: %s\n",
               __FILE__, fcnName, __LINE__, bucketpool_pk, PQgetvalue(result, rowNum, 5));
        numErrors++;
      }
      bucketDefList[rowNum].regex = strdup(PQgetvalue(result, rowNum, 2));
    }

    bucketDefList[rowNum].dataFilename = strdup(PQgetvalue(result, rowNum, 3));

    /* verify that external file dataFilename exists */
    if (strlen(bucketDefList[rowNum].dataFilename) > 0)
    {
      snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s",
        PROJECTSTATEDIR, bucketpool_pk, bucketDefList[rowNum].dataFilename);
      if (stat(filepath, &statbuf) == -1)
      {
        hostname[0] = 0;
        gethostname(hostname, sizeof(hostname));
        printf("ERROR: %s.%s.%d File: %s is missing on host: %s.  bucketpool_pk: %d, bucket: %s\n",
               __FILE__, fcnName, __LINE__, filepath, hostname, bucketpool_pk, PQgetvalue(result, rowNum, 5));
        numErrors++;
      }
    }

    /* MATCH_EVERY */
    if (bucketDefList[rowNum].bucket_type == 1)
      bucketDefList[rowNum].match_every = getMatchEvery(pgConn, bucketpool_pk, bucketDefList[rowNum].dataFilename, pcroot);

    /* MATCH_ONLY */
    if (bucketDefList[rowNum].bucket_type == 2)
    {
      bucketDefList[rowNum].match_only = getMatchOnly(pgConn, bucketpool_pk, bucketDefList[rowNum].dataFilename, pcroot);
    }

    /* REGEX-FILE */
    if (bucketDefList[rowNum].bucket_type == 5)
    {
      bucketDefList[rowNum].regex_row = getRegexFile(pgConn, bucketpool_pk, bucketDefList[rowNum].dataFilename, pcroot);
    }

    bucketDefList[rowNum].stopon = *PQgetvalue(result, rowNum, 4);
    bucketDefList[rowNum].bucket_name = strdup(PQgetvalue(result, rowNum, 5));
    bucketDefList[rowNum].applies_to = *PQgetvalue(result, rowNum, 6);
  }
  PQclear(result);
  if (numErrors) return 0;

  if (debug)
  {
    for (rowNum=0; rowNum<numRows; rowNum++)
    {
      printf("\nbucket_pk[%d] = %d\n", rowNum, bucketDefList[rowNum].bucket_pk);
      printf("bucket_name[%d] = %s\n", rowNum, bucketDefList[rowNum].bucket_name);
      printf("bucket_type[%d] = %d\n", rowNum, bucketDefList[rowNum].bucket_type);
      printf("dataFilename[%d] = %s\n", rowNum, bucketDefList[rowNum].dataFilename);
      printf("stopon[%d] = %c\n", rowNum, bucketDefList[rowNum].stopon);
      printf("applies_to[%d] = %c\n", rowNum, bucketDefList[rowNum].applies_to);
      printf("nomos_agent_pk[%d] = %d\n", rowNum, bucketDefList[rowNum].nomos_agent_pk);
      printf("bucket_agent_pk[%d] = %d\n", rowNum, bucketDefList[rowNum].bucket_agent_pk);
      printf("regex[%d] = %s\n", rowNum, bucketDefList[rowNum].regex);
    }
  }

  return bucketDefList;
}


/**
 * \brief Read the match only file (bucket type 2)
 *
 * \param PGconn $pgConn  Database connection object
 * \param int $bucketpool_pk
 * \param char $filename  File name of match_only file
 *
 * \return an array of rf_pk's that match the licenses
 * in filename.
 * or 0 if error.
 */
FUNCTION int *getMatchOnly(PGconn *pgConn, int bucketpool_pk, 
                             char *filename, cacheroot_t *pcroot)
{
  char *fcnName = "getMatchOnly";
  char *delims = ",\t\n\r";
  char *sp;
  char filepath[256];  
  char inbuf[256];
  int *match_only = 0;
  int  line_count = 0;
  int  lr_pk;
  int  matchNumb = 0;
  FILE *fin;

  /* put together complete file path to match_only file */
  snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s", 
           PROJECTSTATEDIR, bucketpool_pk, filename);

  /* open filepath */
  fin = fopen(filepath, "r");
  if (!fin)
  {
    printf("FATAL: %s.%s.%d Failure to open bucket file %s (pool=%d).\nError: %s\n",
           __FILE__, fcnName, __LINE__, filepath, bucketpool_pk, strerror(errno));
    return 0;
  }

  /* count lines in file */
  while (fgets(inbuf, sizeof(inbuf), fin)) line_count++;
  
  /* calloc match_only array as lines+1.  This set the array to 
     the max possible size +1 for null termination */
  match_only = calloc(line_count+1, sizeof(int));
  if (!match_only)
  {
    printf("FATAL: %s.%s.%d Unable to allocate %d int array.\n",
           __FILE__, fcnName, __LINE__, line_count+1);
    return 0;
  }

  /* read each line fgets 
     A match_only file has one license per line, no leading whitespace.
     Comments start with leading #
   */
  rewind(fin);
  while (fgets(inbuf, sizeof(inbuf), fin)) 
  {
    /* input string should only contain 1 token (license name) */
    sp = strtok(inbuf, delims);

    /* comment? */
    if ((sp == 0) || (*sp == '#')) continue;

    /* look up license rf_pk */
    lr_pk = lrcache_lookup(pcroot, sp);
    if (lr_pk)
    {
      /* save rf_pk in match_only array */
      match_only[matchNumb++] = lr_pk;
//printf("MATCH_ONLY license: %s, FOUND\n", sp);
    }
    else
    {
//printf("MATCH_ONLY license: %s, NOT FOUND in DB - ignored\n", sp);
    }
  }

return match_only;
}


/**
 * \brief Read the match every file filename, for bucket type 1
 *
 * \param PGconn $pgConn  Database connection object
 * \param int $bucketpool_pk
 * \param char $filename
 * \param cacheroot_t $pcroot  License cache
 *
 * \return an array of arrays of rf_pk's that define a 
 * match_every combination.
 * or 0 if error.
 */
FUNCTION int **getMatchEvery(PGconn *pgConn, int bucketpool_pk, 
                             char *filename, cacheroot_t *pcroot)
{
  char *fcnName = "getMatchEvery";
  char filepath[256];  
  char inbuf[256];
  int **match_every = 0;
  int **match_every_head = 0;
  int  line_count = 0;
  int  *lr_pkArray;
  int  matchNumb = 0;
  FILE *fin;

  /* put together complete file path to match_every file */
  snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s", 
           PROJECTSTATEDIR, bucketpool_pk, filename);

  /* open filepath */
  fin = fopen(filepath, "r");
  if (!fin)
  {
    printf("FATAL: %s.%s.%d Failure to initialize bucket %s (pool=%d).\nError: %s\n",
           __FILE__, fcnName, __LINE__, filepath, bucketpool_pk, strerror(errno));
    return 0;
  }

  /* count lines in file */
  while (fgets(inbuf, sizeof(inbuf), fin)) line_count++;
  
  /* calloc match_every array as lines+1.  This sets the array to 
     the max possible size +1 for null termination */
  match_every = calloc(line_count+1, sizeof(int *));
  if (!match_every)
  {
    printf("FATAL: %s.%s.%d Unable to allocate %d int array.\n",
           __FILE__, fcnName, __LINE__, line_count+1);
    return 0;
  }
  match_every_head = match_every;

  /* read each line fgets 
     A match_every file has 1-n licenses per line
     Comments start with leading #
   */
  rewind(fin);
  while (fgets(inbuf, sizeof(inbuf), fin)) 
  {
    /* comment? */
    if (inbuf[0] == '#') continue;
    lr_pkArray = getLicsInStr(pgConn, inbuf, pcroot);
    if (lr_pkArray)
    {
      /* save rf_pk in match_every array */
      match_every[matchNumb++] = lr_pkArray;
    }
  }

  if (!matchNumb)
  {
    free(match_every_head);
    match_every_head = 0;
  }
return match_every_head;
}


/**
 * \brief Parse filename, for bucket type 5 REGEX-FILE
 * Lines are in format:
 * {ftype1} {regex1} {op} {ftype2} {regex2}
 *
 * ftype is either "license" or "filename" \n
 * op is either "and" (1) or "or" (2) or "not" (3) \n
 * The op clause is optional. \n
 * For example: \n
 *   license bsd.*clause \n
 *   license (GPL_?v3|Affero_v3) and filename .*mypkg \n
 *
 * \param PGconn $pgConn  Database connection object
 * \param int $bucketpool_pk
 * \param char $filename
 * \param cacheroot_t $pcroot  License cache
 *
 * \return an array of arrays of regex_file_t's that 
 *        represent the rows in filename. \n
 * or 0 if error.
 */
FUNCTION regex_file_t *getRegexFile(PGconn *pgConn, int bucketpool_pk, 
                             char *filename, cacheroot_t *pcroot)
{
  char *fcnName = "getRegexFile";
  char filepath[256];  
  char inbuf[256];
  regex_file_t *regex_row_head = 0;
  int  line_count = 0;
  int  rv;
  int  rowNumb = 0;
  int  errorCount = 0;
  char *Delims = " \t\n\r";
  char *token;
  char *saveptr;
  FILE *fin;

  /* put together complete file path to match_every file */
  snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s", 
           PROJECTSTATEDIR, bucketpool_pk, filename);

  /* open filepath */
  fin = fopen(filepath, "r");
  if (!fin)
  {
    printf("FATAL: %s.%s.%d Failure to initialize bucket %s (pool=%d).\nError: %s\n",
           __FILE__, fcnName, __LINE__, filepath, bucketpool_pk, strerror(errno));
    printf("In v1.3, files were in %s.  To be LSB compliate, v1.4 now requires them to be in %s\n",
           DATADIR, PROJECTSTATEDIR);
    return 0;
  }

  /* count lines in file */
  while (fgets(inbuf, sizeof(inbuf), fin)) line_count++;
  
  /* calloc array as lines+1.  This sets the array to 
     the max possible size +1 for null termination */
  regex_row_head = calloc(line_count+1, sizeof(regex_file_t));
  if (!regex_row_head)
  {
    printf("FATAL: %s.%s.%d Unable to allocate %d regex_file_t array.\n",
           __FILE__, fcnName, __LINE__, line_count+1);
    return 0;
  }

  /* read each line fgets 
     File has 1-n expressions per line
     Comments start with leading #
   */
  rewind(fin);
  while (fgets(inbuf, sizeof(inbuf), fin)) 
  {
    /* comment? */
    if (inbuf[0] == '#') continue;

    /* get first token ftype1 */
    token = strtok_r(inbuf, Delims, &saveptr);

    /* empty line? */
    if (token[0] == 0) continue;

    regex_row_head[rowNumb].ftype1 = getRegexFiletype(token, filepath);
    if (regex_row_head[rowNumb].ftype1 == 0) break;

    /* get regex1 */
    token = strtok_r(NULL, Delims, &saveptr);
    regex_row_head[rowNumb].regex1 = strdup(token);
    rv = regcomp(&regex_row_head[rowNumb].compRegex1, token, REG_NOSUB | REG_ICASE);
    if (rv != 0)
    {
      printf("ERROR: %s.%s.%d Invalid regular expression for file: %s, [%s], row: %d\n",
              __FILE__, fcnName, __LINE__, filepath, token, rowNumb+1);
      errorCount++;
      break;
    }

    /* get optional operator 'and'=1 or 'or'=2 'not'=3 */
    token = strtok_r(NULL, Delims, &saveptr);
    if (!token)
    {
      rowNumb++;
      continue;
    }
    else
    {
      if (strcasecmp(token, "and") == 0) regex_row_head[rowNumb].op = 1;
      else
      if (strcasecmp(token, "or") == 0) regex_row_head[rowNumb].op = 2;
      else
      if (strcasecmp(token, "not") == 0) regex_row_head[rowNumb].op = 3;
      else
      {
        printf("ERROR: %s.%s.%d Invalid operator in file: %s, [%s], row: %d\n",
               __FILE__, fcnName, __LINE__, filepath, token, rowNumb+1);
        errorCount++;
        break;
      }
    }

    /* get token ftype2 */
    token = strtok_r(NULL, Delims, &saveptr);
    regex_row_head[rowNumb].ftype2 = getRegexFiletype(token, filepath);
    if (regex_row_head[rowNumb].ftype2 == 0) break;

    /* get regex2 */
    token = strtok_r(NULL, Delims, &saveptr);
    regex_row_head[rowNumb].regex2 = strdup(token);
    rv = regcomp(&regex_row_head[rowNumb].compRegex2, token, REG_NOSUB | REG_ICASE);
    if (rv != 0)
    {
      printf("ERROR: %s.%s.%d Invalid regular expression for file: %s, [%s], row: %d\n",
              __FILE__, fcnName, __LINE__, filepath, token, rowNumb+1);
      errorCount++;
      break;
    }

    rowNumb++;
  }

  /* bad file data.  Die unceremoniously.
   * todo: die more gracefully.
   */
  if (errorCount) exit(-1);

  if (!rowNumb)
  {
    free(regex_row_head);
    regex_row_head = 0;
  }
  return regex_row_head;
}


/**
 * \brief Given a filetype token from REGEX-FILE
 * return the token int representation.
 *
 * \param char $token  
 * \param char $filepath  path of REGEX-FILE data file.
 *                       used for error reporting only.
 *
 * \return 1=filename, 2=license
 */
FUNCTION int getRegexFiletype(char *token, char *filepath)
{
  if (strcasecmp(token, "filename") == 0) return(1);
  else
  if (strcasecmp(token, "license") == 0) return(2);
  printf("FATAL: Invalid bucket file (%s), unknown filetype (%s)\n",
       filepath, token);
  return(0);
}


/**
 * \brief Given a string with | separated license names
 * return an integer array of rf_pk's
 *
 * \param PGconn $pgConn  Database connection object
 * \param char $nameStr   string of lic names eg "bsd | gpl"
 * \param cacheroot_t $pcroot  License cache
 *
 * \return an array of rf_pk's that match the names in nameStr
 *
 * if nameStr contains a license name that is not in
 * the license_ref file, then 0 is returned since there
 * is no way to match all the listed licenses.
 */
FUNCTION int *getLicsInStr(PGconn *pgConn, char *nameStr,
                             cacheroot_t *pcroot)
{
  char *fcnName = "getLicsInStr";
  char *delims = "|\n\r ";
  char *sp;
  int *pkArray;
  int *pkArrayHead = 0;
  int  lic_count = 1;
  int  lr_pk;
  int  matchNumb = 0;

  if (!nameStr) return 0;

  /* count how many seperators are in nameStr
     number of licenses is the count +1 */
  sp = nameStr;
  while (*sp) if (*sp++ == *delims) lic_count++;

  /* we need lic_count+1 int array.  This sets the array to 
     the max possible size +1 for null termination */
  pkArray = calloc(lic_count+1, sizeof(int));
  if (!pkArray)
  {
    printf("FATAL: %s.%s.%d Unable to allocate %d int array.\n",
           __FILE__, fcnName, __LINE__, lic_count+1);
    return 0;
  }
  pkArrayHead = pkArray;  /* save head of array */

  /* read each line then read each license in the line
     Comments start with leading #
   */
  while ((sp = strtok(nameStr, delims)) != 0)
  {
    /* look up license rf_pk */
    lr_pk = lrcache_lookup(pcroot, sp);
    if (lr_pk)
    {
      /* save rf_pk in match_every array */
      pkArray[matchNumb++] = lr_pk;
    }
    else
    {
      /* license not found in license_ref table, so this can never match */
      matchNumb = 0;
      break;
    }
    nameStr = 0;  // for strtok
  }

  if (matchNumb == 0)
  {
    free(pkArrayHead);
    pkArrayHead = 0;
  }

  return pkArrayHead;
}


/**
 * \brief Get the latest nomos agent_pk that has data for this
 * this uploadtree.
 *
 * \param PGconn $pgConn  Database connection object
 * \param int    $upload_pk  
 *
 * \return nomos_agent_pk of the latest version of the nomos agent
 *        that has data for this upload. \n
 *        Or 0 if there is no license data available
 * 
 * NOTE: This function writes error to stdout
 */
FUNCTION int LatestNomosAgent(PGconn *pgConn, int upload_pk)
{
  char *fcnName = "LatestNomosAgent";
  char sql[512];
  PGresult *result;
  int  nomos_agent_pk = 0;

  /*** Find the latest enabled nomos agent_pk ***/
                         
  snprintf(sql, sizeof(sql),
          "select agent_fk from nomos_ars, agent \
              WHERE agent_pk=agent_fk and ars_success=true and upload_fk='%d' \
                    and agent_enabled=true order by agent_ts desc limit 1",
          upload_pk);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return 0;
  if (PQntuples(result) == 0) return 0;
  nomos_agent_pk = atoi(PQgetvalue(result,0,0));
  PQclear(result);
  return nomos_agent_pk;
}


/**
 * \brief Given an uploadtree_pk of a container, find the
 * uploadtree_pk of it's children (i.e. scan down through
 * artifacts to get the children's parent
 *
 * \param PGconn $pgConn  Database connection object
 * \param int    $uploadtree_pk  
 *
 * \return uploadtree_pk of children's parent. \n
 *         Or 0 if there are no children (empty container or non-container)
 *        
 * NOTE: This function writes error to stdout
 */
FUNCTION int childParent(PGconn *pgConn, int uploadtree_pk)
{
  char *fcnName = "childParent";
  char sql[256];
  PGresult *result;
  int  childParent_pk = 0;   /* uploadtree_pk */

  do
  {
    snprintf(sql, sizeof(sql),
           "select uploadtree_pk,ufile_mode from uploadtree where parent=%d limit 1", 
           uploadtree_pk);
    result = PQexec(pgConn, sql);
    if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) break;
    if (PQntuples(result) == 0) break;  /* empty container */

    /* not an artifact? */
    if ((atoi(PQgetvalue(result, 0, 1)) & 1<<28) == 0)
    {
      childParent_pk = uploadtree_pk;
      break;
    }
    uploadtree_pk = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
  } while (childParent_pk == 0);

  PQclear(result);
  return childParent_pk;
}
