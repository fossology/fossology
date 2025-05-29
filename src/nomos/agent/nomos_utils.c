/*
 SPDX-FileCopyrightText: © 2006-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif /* not defined _GNU_SOURCE */

#include "nomos_utils.h"
#include "nomos.h"

extern int should_connect_to_db;  /* Global variable to control DB connection */

#define FUNCTION

/**
 * \file
 * \brief Utilities used by nomos
 */

sem_t* mutexJson;
gboolean* printcomma;
char saveLics[myBUFSIZ];

/**
 \brief Add a new license to license_ref table

 Adds a license to license_ref table.

 @param  licenseName Name of license

 @return rf_pk for success, 0 for failure
 */
FUNCTION long add2license_ref(char *licenseName)
{

  PGresult *result;
  char query[myBUFSIZ];
  char insert[myBUFSIZ];
  char escLicName[myBUFSIZ];
  char *specialLicenseText;
  long rf_pk;

  int len;
  int error;
  int numRows;

  // escape the name
  len = strlen(licenseName);
  PQescapeStringConn(gl.pgConn, escLicName, licenseName, len, &error);
  if (error)
  LOG_WARNING("Does license name %s have multibyte encoding?", licenseName)

  /* verify the license is not already in the table */
  snprintf(query, myBUFSIZ - 1, "SELECT rf_pk FROM " LICENSE_REF_TABLE " where rf_shortname='%s'", escLicName);
  result = PQexec(gl.pgConn, query);
  if (fo_checkPQresult(gl.pgConn, result, query, __FILE__, __LINE__))
    return 0;
  numRows = PQntuples(result);
  if (numRows)
  {
    rf_pk = atol(PQgetvalue(result, 0, 0));
    PQclear(result);
    return rf_pk;
  }
  PQclear(result);

  /* Insert the new license */
  specialLicenseText = "License by Nomos.";

  snprintf(insert, myBUFSIZ - 1, "insert into license_ref(rf_shortname, rf_text, rf_detector_type) values('%s', '%s', 2)", escLicName,
      specialLicenseText);
  result = PQexec(gl.pgConn, insert);
  // ignore duplicate constraint failure (23505), report others
  if ((result == 0)
      || ((PQresultStatus(result) != PGRES_COMMAND_OK)
          && (strncmp(PG_ERRCODE_UNIQUE_VIOLATION, PQresultErrorField(result, PG_DIAG_SQLSTATE), 5))))
  {
    printf("ERROR: %s(%d): Nomos failed to add a new license. %s/n: %s/n",
    __FILE__, __LINE__, PQresultErrorMessage(result), insert);
    PQclear(result);
    return (0);
  }
  PQclear(result);

  /* retrieve the new rf_pk */
  result = PQexec(gl.pgConn, query);
  if (fo_checkPQresult(gl.pgConn, result, query, __FILE__, __LINE__))
    return 0;
  numRows = PQntuples(result);
  if (numRows)
    rf_pk = atol(PQgetvalue(result, 0, 0));
  else
  {
    printf("ERROR: %s:%s:%d Just inserted value is missing. On: %s", __FILE__, "add2license_ref()", __LINE__, query);
    PQclear(result);
    return (0);
  }
  PQclear(result);

  return (rf_pk);
}

/**
 \brief calculate the hash of an rf_shortname
 rf_shortname is the key

 @param pcroot Root pointer
 @param rf_shortname

 @return hash value
 */
FUNCTION long lrcache_hash(cacheroot_t *pcroot, char *rf_shortname)
{
  long hashval = 0;
  int len, i;

  /* use the first sizeof(long) bytes for the hash value */
  len = (strlen(rf_shortname) < sizeof(long)) ? strlen(rf_shortname) : sizeof(long);
  for (i = 0; i < len; i++)
    hashval += rf_shortname[i] << 8 * i;
  hashval = hashval % pcroot->maxnodes;
  return hashval;
}

/**
 \brief Print the contents of the hash table

 @param pcroot Table root

 @return none
 */
FUNCTION void lrcache_print(cacheroot_t *pcroot)
{
  cachenode_t *pcnode;
  long hashval = 0;
  int i;

  pcnode = pcroot->nodes;
  for (i = 0; i < pcroot->maxnodes; i++)
  {
    if (pcnode->rf_pk != 0L)
    {
      hashval = lrcache_hash(pcroot, pcnode->rf_shortname);
      printf("%ld, %ld, %s\n", hashval, pcnode->rf_pk, pcnode->rf_shortname);
    }
    pcnode++;
  }
}

/**
 \brief free the hash table

 @param pcroot Table root

 @return none
 */
FUNCTION void lrcache_free(cacheroot_t *pcroot)
{
  cachenode_t *pcnode;
  int i;

  pcnode = pcroot->nodes;
  for (i = 0; i < pcroot->maxnodes; i++)
  {
    if (pcnode->rf_pk != 0L)
    {
      free(pcnode->rf_shortname);
    }
    pcnode++;
  }
  free(pcroot->nodes);
}

/**
 \brief add a rf_shortname, rf_pk to the license_ref cache
 rf_shortname is the key

 @param pcroot        Table root
 @param rf_pk         License ID in DB
 @param rf_shortname  License shortname (key)

 @return -1 for failure, 0 for success
 */
FUNCTION int lrcache_add(cacheroot_t *pcroot, long rf_pk, char *rf_shortname)
{
  cachenode_t *pcnode;
  long hashval = 0;
  int i;
  int noden;

  hashval = lrcache_hash(pcroot, rf_shortname);

  noden = hashval;
  for (i = 0; i < pcroot->maxnodes; i++)
  {
    noden = (hashval + i) & (pcroot->maxnodes - 1);

    pcnode = pcroot->nodes + noden;
    if (!pcnode->rf_pk)
    {
      pcnode->rf_shortname = strdup(rf_shortname);
      pcnode->rf_pk = rf_pk;
      break;
    }
  }
  if (i < pcroot->maxnodes)
    return 0;

  return -1; /* no space */
}

/**
 \brief lookup rf_pk in the license_ref cache
 rf_shortname is the key

 @param pcroot
 @param rf_shortname

 @return rf_pk, 0 if the shortname is not in the cache
 */
FUNCTION long lrcache_lookup(cacheroot_t *pcroot, char *rf_shortname)
{
  cachenode_t *pcnode;
  long hashval = 0;
  int i;
  int noden;

  hashval = lrcache_hash(pcroot, rf_shortname);

  noden = hashval;
  for (i = 0; i < pcroot->maxnodes; i++)
  {
    noden = (hashval + i) & (pcroot->maxnodes - 1);

    pcnode = pcroot->nodes + noden;
    if (!pcnode->rf_pk)
      return 0;
    if (strcmp(pcnode->rf_shortname, rf_shortname) == 0)
    {
      return pcnode->rf_pk;
    }
  }

  return 0; /* not found */
}

/**
 \brief build a cache the license ref db table.

 initLicRefCache builds a cache using the rf_shortname as the key
 and the rf_pk as the value.  This is an optimization. The cache is used for
 reference license lookups instead of querying the db.

 @param pcroot

 @return 0 for failure, 1 for success
 */

FUNCTION int initLicRefCache(cacheroot_t *pcroot)
{

  PGresult *result;
  char query[myBUFSIZ];
  int row;
  int numLics;

  if (!pcroot)
    return 0;

  sprintf(query, "SELECT rf_pk, rf_shortname FROM " LICENSE_REF_TABLE " where rf_detector_type=2");
  result = PQexec(gl.pgConn, query);
  if (fo_checkPQresult(gl.pgConn, result, query, __FILE__, __LINE__))
    return 0;

  numLics = PQntuples(result);
  /* populate the cache  */
  for (row = 0; row < numLics; row++)
  {
    lrcache_add(pcroot, atol(PQgetvalue(result, row, 0)), PQgetvalue(result, row, 1));
  }

  PQclear(result);

  return (1);
} /* initLicRefCache */

/**
 \brief Get the rf_pk for rf_shortname

 Checks the cache to get the rf_pk for this shortname.
 If it doesn't exist, add it to both license_ref and the
 license_ref cache (the hash table).

 @param pcroot
 @param rf_shortname

 @return rf_pk of the matched license or 0
 */
FUNCTION long get_rfpk(cacheroot_t *pcroot, char *rf_shortname)
{
  long rf_pk;
  size_t len;

  if ((len = strlen(rf_shortname)) == 0)
  {
    printf("ERROR! Nomos.c get_rfpk() passed empty name");
    return (0);
  }

  /* is this in the cache? */
  rf_pk = lrcache_lookup(pcroot, rf_shortname);
  if (rf_pk)
    return rf_pk;

  /* shortname was not found, so add it */
  /* add to the license_ref table */
  rf_pk = add2license_ref(rf_shortname);

  /* add to the cache */
  lrcache_add(pcroot, rf_pk, rf_shortname);

  return (rf_pk);
} /* get_rfpk */

/**
 \brief Given a string that contains field='value' pairs, save the items.

 @return pointer to start of next field, or NULL at \0.

 \callgraph
 */
FUNCTION char *getFieldValue(char *inStr, char *field, int fieldMax,
    char *value, int valueMax, char separator)
{
  int s;
  int f;
  int v;
  int gotQuote;

#ifdef PROC_TRACE
  traceFunc("== getFieldValue(inStr= %s fieldMax= %d separator= '%c'\n",
      inStr, fieldMax, separator);
#endif /* PROC_TRACE */

  memset(field, 0, fieldMax);
  memset(value, 0, valueMax);

  /* Skip initial spaces */
  while (isspace(inStr[0]))
  {
    inStr++;
  }

  if (inStr[0] == '\0')
  {
    return (NULL);
  }
  f = 0;
  v = 0;

  /* Skip to end of field name */
  for (s = 0; (inStr[s] != '\0') && !isspace(inStr[s]) && (inStr[s] != '='); s++)
  {
    field[f++] = inStr[s];
  }

  /* Skip spaces after field name */
  while (isspace(inStr[s]))
  {
    s++;
  }
  /* If it is not a field, then just return it. */
  if (inStr[s] != separator)
  {
    return (inStr + s);
  }
  if (inStr[s] == '\0')
  {
    return (NULL);
  }
  /* Skip '=' */
  s++;

  /* Skip spaces after '=' */
  while (isspace(inStr[s]))
  {
    s++;
  }
  if (inStr[s] == '\0')
  {
    return (NULL);
  }

  gotQuote = '\0';
  if ((inStr[s] == '\'') || (inStr[s] == '"'))
  {
    gotQuote = inStr[s];
    s++; /* skip quote */
    if (inStr[s] == '\0')
    {
      return (NULL);
    }
  }

  if (gotQuote)
  {
    for (; (inStr[s] != '\0') && (inStr[s] != gotQuote); s++)
    {
      if (inStr[s] == '\\')
      {
        value[v++] = inStr[++s];
      }
      else
      {
        value[v++] = inStr[s];
      }
    }
  }
  else
  {
    /* if it gets here, then there is no quote */
    for (; (inStr[s] != '\0') && !isspace(inStr[s]); s++)
    {
      if (inStr[s] == '\\')
      {
        value[v++] = inStr[++s];
      }
      else
      {
        value[v++] = inStr[s];
      }
    }
  }
  /* Skip spaces */
  while (isspace(inStr[s]))
  {
    s++;
  }

  return (inStr + s);
} /* getFieldValue */

/**
 \brief parse the comma separated list of license names found

 Uses cur.compLic and sets cur.licenseList
 */

FUNCTION void parseLicenseList()
{

  int numLics = 0;

  /* char saveLics[myBUFSIZ]; */
  char *saveptr = 0; /* used for strtok_r */
  char *saveLicsPtr;

  if ((strlen(cur.compLic)) == 0)
  {
    return;
  }

  /* check for a single name  FIX THIS!*/
  if (strstr(cur.compLic, ",") == NULL)
  {
    cur.licenseList[0] = cur.compLic;
    cur.licenseList[1] = NULL;
    return;
  }

  saveLicsPtr = strcpy(saveLics, cur.compLic);

  cur.tmpLics = strtok_r(saveLicsPtr, ",", &saveptr);

  cur.licenseList[numLics] = cur.tmpLics;
  numLics++;

  saveLicsPtr = NULL;
  while (cur.tmpLics)
  {
    cur.tmpLics = strtok_r(saveLicsPtr, ",", &saveptr);
    if (cur.tmpLics == NULL)
    {
      break;
    }
    cur.licenseList[numLics] = cur.tmpLics;
    numLics++;
  }
  cur.licenseList[numLics] = NULL;
  numLics++;

  /*
   int i;
   for(i=0; i<numLics; i++){
   printf("cur.licenseList[%d] is:%s\n",i,cur.licenseList[i]);
   }

   printf("parseLicenseList: returning\n");
   */

  return;
} /* parseLicenseList */

/**
 * \brief Print nomos usage help
 * \param Name Path to nomos binary
 */
FUNCTION void Usage(char *Name)
{
  /* Disable database connection when showing usage */
  should_connect_to_db = 0;
  printf("Usage: %s [options] [file [file [...]]\n", Name);
  printf("  -h   :: help (print this message), then exit.\n");
  printf("  -i   :: initialize the database, then exit.\n");
  printf("  -c   :: specify the directory for the system configuration.\n");
  printf("  -l   :: print full file path (command line only).\n");
  printf("  -v   :: verbose (-vv = more verbose)\n");
  printf("  -J   :: output in JSON\n");
  printf("  -S   :: print Highlightinfo to stdout \n");
  printf("  file :: if files are listed, print the licenses detected within them.\n");
  printf("  no file :: process data from the scheduler.\n");
  printf("  -V   :: print the version info, then exit.\n");
  printf("  -d   :: specify a directory to scan.\n");
  printf("  -n   :: spaw n - 1 child processes to run, there will be n running processes(the parent and n - 1 children). \n the default n is 2(when n is less than 2 or not setting, will be changed to 2) when -d is specified.\n");
} /* Usage() */

/**
 * \brief Close connections and exit
 *
 * The function closes DB and scheduler connections and calls exit() with the
 * return code passed
 * \param exitval Return code to pass to exit()
 */
FUNCTION void Bail(int exitval)
{
#ifdef PROC_TRACE
  traceFunc("== Bail(%d)\n", exitval);
#endif /* PROC_TRACE */

#if defined(MEMORY_TRACING) && defined(MEM_ACCT)
  if (exitval)
  {
    memCacheDump("Mem-cache @ Bail() time:");
  }
#endif /* MEMORY_TRACING && MEM_ACCT */

  /* close database and scheduler connections */
  if (gl.pgConn) {
    fo_dbManager_free(gl.dbManager);
    PQfinish(gl.pgConn);
  }
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

/**
 * \brief Check if an CLI option is set
 * \param val Binary position to check
 * \return > 0 if it is set, 0 otherwise
 */
FUNCTION int optionIsSet(int val)
{
#ifdef PROC_TRACE
  traceFunc("== optionIsSet(%x)\n", val);
#endif /* PROC_TRACE */

  return (gl.progOpts & val);
} /* optionIsSet */

/**
 \brief Initialize the lists: regular-files list cur.regfList and buffer-offset
 list cur.offList.

 \todo CDB - Could probably roll this back into the main processing
 loop or just put in a generic init func that initializes *all*
 the lists.

 \callgraph
 */

FUNCTION  void getFileLists(char *dirpath)
{
#ifdef PROC_TRACE
  traceFunc("== getFileLists(%s)\n", dirpath);
#endif /* PROC_TRACE */

  /*    listInit(&gl.sarchList, 0, "source-archives list & md5sum map"); */
  listInit(&cur.regfList, 0, "regular-files list");
  listInit(&cur.offList, 0, "buffer-offset list");
#ifdef FLAG_NO_COPYRIGHT
  listInit(&gl.nocpyrtList, 0, "no-copyright list");
#endif /* FLAG_NO_COPYRIGHT */

  listGetItem(&cur.regfList, cur.targetFile);
  return;
} /* getFileLists */

/**
 * \brief insert rf_fk, agent_fk and pfile_fk into license_file table
 *
 * @param rfPK the reference file foreign key
 *
 * \returns The primary key for the inserted entry (or Negative value on error)
 *
 * \callgraph
 */
FUNCTION long updateLicenseFile(long rfPk)
{

  PGresult *result;

  if (rfPk <= 0)
  {
    return (-2);
  }

  /* If files are coming from command line instead of fossology repo,
   then there are no pfiles.  So don't update the db
   */
  if (cur.cliMode == 1)
    return (-1);

  result = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      gl.dbManager,
      "updateLicenseFile",
      "INSERT INTO license_file(rf_fk, agent_fk, pfile_fk) VALUES($1, $2, $3) RETURNING fl_pk",
      long, int, long
    ),
    rfPk, gl.agentPk, cur.pFileFk
  );

  if (result) {
    long licenseFileId = -1;
    if (PQntuples(result) > 0) {
      licenseFileId = atol(PQgetvalue(result, 0, 0));
    }

    PQclear(result);
    return (licenseFileId);
  } else {
    return (-1);
  }
} /* updateLicenseFile */

/**
 * \brief Return the highlight type (K|L|0) for a given index
 * @param index Index to convert
 * @return K if index is between keyword length,\n
 * L if index is larger than keyword length,\n
 * 0 otherwise
 */
FUNCTION char convertIndexToHighlightType(int index)
{

  char type;

  if ((index >= _KW_first) && (index <= _KW_last))
    type = 'K';
  else if (index > _KW_last)
    type = 'L';
  else type = '0';

  return type;

}

/**
 * \brief insert rf_fk, agent_fk, offset, len and type into highlight table
 *
 * @param pcroot The root of hash table
 *
 * \returns boolean (True or False)
 *
 * \callgraph
 */
FUNCTION int updateLicenseHighlighting(cacheroot_t *pcroot){

  /* If files are coming from command line instead of fossology repo,
   then there are no pfiles.  So don't update the db

   Also if we specifically do not want highlight information in the
   database skip this function
   */
  if(cur.cliMode == 1 || optionIsSet(OPTS_NO_HIGHLIGHTINFO ) ){
    return (TRUE);
  }
  PGresult *result;




#ifdef GLOBAL_DEBUG
  printf("%s %s %i \n", cur.filePath,cur.compLic , cur.theMatches->len);
#endif

  // This speeds up the writing to the database and ensures that we have either full highlight information or none
  PGresult* begin1 = PQexec(gl.pgConn, "BEGIN");
  PQclear(begin1);

  fo_dbManager_PreparedStatement* preparedKeywords;
  if(cur.keywordPositions->len > 0 ) {
    preparedKeywords = fo_dbManager_PrepareStamement(
      gl.dbManager,
      "updateLicenseHighlighting:keyword",
      "INSERT INTO highlight_keyword (pfile_fk, start, len) VALUES($1, $2, $3)",
      long, int, int
    );
  }
  int i;
  for (i = 0; i < cur.keywordPositions->len; ++i)
  {
    MatchPositionAndType* ourMatchv = getMatchfromHighlightInfo(cur.keywordPositions, i);
    result = fo_dbManager_ExecPrepared(
                preparedKeywords,
                cur.pFileFk, ourMatchv->start, ourMatchv->end - ourMatchv->start);
    if (result)
    {
      PQclear(result);
    }
  }
  PGresult* commit1 = PQexec(gl.pgConn, "COMMIT");
  PQclear(commit1);

  PGresult* begin2 =PQexec(gl.pgConn, "BEGIN");
  PQclear(begin2);
  fo_dbManager_PreparedStatement* preparedLicenses;

  if(cur.theMatches->len > 0 ) {
    preparedLicenses=fo_dbManager_PrepareStamement(
       gl.dbManager,
       "updateLicenseHighlighting",
       "INSERT INTO highlight (fl_fk, start, len, type) VALUES($1, $2, $3,'L')",
       long, int, int
     );
  }

  for (i = 0; i < cur.theMatches->len; ++i)
  {
    LicenceAndMatchPositions* ourLicence = getLicenceAndMatchPositions(cur.theMatches, i);

    int j;
    for (j = 0; j < ourLicence->matchPositions->len; ++j)
    {
      MatchPositionAndType* ourMatchv = getMatchfromHighlightInfo(ourLicence->matchPositions, j);
      if(ourLicence->licenseFileId == -1) {
        //! the license File ID was never set and we should not insert it in the database
        continue;
      }
      result = fo_dbManager_ExecPrepared(
                  preparedLicenses,
        ourLicence->licenseFileId,
        ourMatchv->start, ourMatchv->end - ourMatchv->start
      );
      if (result == NULL)
      {
        return (FALSE);
      } else {
        PQclear(result);
      }
    }
  }

  PGresult* commit2 = PQexec(gl.pgConn, "COMMIT");
  PQclear(commit2);
  return (TRUE);
} /* updateLicenseHighlighting */


/**
 * \brief process a single file
 * \param fileToScan File path
 * \callgraph
 */
FUNCTION void processFile(char *fileToScan)
{

  char *pathcopy;
#ifdef PROC_TRACE
  traceFunc("== processFile(%s)\n", fileToScan);
#endif /* PROC_TRACE */

  /* printf("   LOG: nomos scanning file %s.\n", fileToScan);  DEBUG */

  (void) strcpy(cur.cwd, gl.initwd);

  strcpy(cur.filePath, fileToScan);
  pathcopy = g_strdup(fileToScan);
  strcpy(cur.targetDir, dirname(pathcopy));
  g_free(pathcopy);
  strcpy(cur.targetFile, fileToScan);
  cur.targetLen = strlen(cur.targetDir);

  if (!isFILE(fileToScan))
  {
    LOG_FATAL("\"%s\" is not a plain file", fileToScan)
    Bail(-__LINE__);
  }

  getFileLists(cur.targetDir);
  listInit(&cur.fLicFoundMap, 0, "file-license-found map");
  listInit(&cur.parseList, 0, "license-components list");
  listInit(&cur.lList, 0, "license-list");

  processRawSource();

  /* freeAndClearScan(&cur); */
} /* Process File */

/**
 * \brief Set the license file id to the highlights
 * \param licenseFileId License id
 * \param licenseName   License name
 */
void setLicenseFileIdInHiglightArray(long licenseFileId, char* licenseName){
  int i;
  for (i = 0; i < cur.theMatches->len; ++i) {
    LicenceAndMatchPositions* ourLicence = getLicenceAndMatchPositions(cur.theMatches, i);
    if (strcmp(licenseName, ourLicence->licenceName) == 0)
      ourLicence->licenseFileId = licenseFileId;
  }
} /* setLicenseFileIdInHiglightArray */

/**
 * \brief Add a license to hash table, license table and highlight array
 * \param licenseName License name
 * \param pcroot      Hash table root
 * \return True if license is inserted in DB, False otherwise
 */
int updateLicenseFileAndHighlightArray(char* licenseName, cacheroot_t* pcroot) {
  long rf_pk = get_rfpk(pcroot, licenseName);
  long licenseFileId = updateLicenseFile(rf_pk);
  if (licenseFileId > 0) {
    setLicenseFileIdInHiglightArray(licenseFileId, licenseName);
    return (true);
  } else {
    return (false);
  }
}

/**
 \brief Write out the information about the scan to the FOSSology database.

 curScan is passed as an arg even though it's available as a global,
 in order to facilitate future modularization of the code.

 \returns 0 if successful, -1 if not.

 \callgraph
 */
FUNCTION int recordScanToDB(cacheroot_t *pcroot, struct curScan *scanRecord)
{

  char *noneFound;
  int numLicenses;

#ifdef SIMULATESCHED
  /* BOBG: This allows a developer to simulate the scheduler
   with a load file for testing/debugging, without updating the
   database.  Like:
   cat myloadfile | ./nomos
   myloadfile is same as what scheduler sends:
   pfile_pk=311667 pfilename=9A96127E7D3B2812B50BF7732A2D0FF685EF6D6A.78073D1CA7B4171F8AFEA1497E4C6B33.183
   pfile_pk=311727 pfilename=B7F5EED9ECB679EE0F980599B7AA89DCF8FA86BD.72B00E1B419D2C83D1050C66FA371244.368
   etc.
   */
  printf("%s\n",scanRecord->compLic);
  return(0);
#endif

  noneFound = strstr(scanRecord->compLic, LS_NONE);
  if (noneFound != NULL)
  {
    if (!updateLicenseFileAndHighlightArray("No_license_found", pcroot))
      return (-1);
    return (0);
  }

  /* we have one or more license names, parse them */
  parseLicenseList();
  /* loop through the found license names */
  for (numLicenses = 0; cur.licenseList[numLicenses] != NULL; numLicenses++)
  {
    if (!updateLicenseFileAndHighlightArray(cur.licenseList[numLicenses], pcroot))
      return (-1);
  }

  if (updateLicenseHighlighting(pcroot) == FALSE)
  {
    printf("Failure in update of highlight table \n");
  }

  return (0);
} /* recordScanToDb */

/**
 * \brief Get the MatchPositionAndType for a given index in highlight array
 * \param in    Highlight array
 * \param index Index to fetch
 * \return Match position and type
 */
FUNCTION inline MatchPositionAndType* getMatchfromHighlightInfo(GArray* in,
    int index)
{
  return &g_array_index(in, MatchPositionAndType, index);
}

/**
 * \brief Get the LicenceAndMatchPositions for a given index in match array
 * \param in    Match array
 * \param index Index to fetch
 * \return License and match position
 */
FUNCTION inline LicenceAndMatchPositions* getLicenceAndMatchPositions(
    GArray* in, int index)
{
  return &g_array_index(in, LicenceAndMatchPositions, index);
}

/**
 * \brief Initialize the scanner
 *
 * Creates a new index list, match list, keyword position list, doctored buffer
 * and license index
 * \param cur Current scanner
 */
FUNCTION void initializeCurScan(struct curScan* cur)
{
  cur->indexList =  g_array_new(FALSE, FALSE, sizeof(int));
  cur->theMatches = g_array_new(FALSE, FALSE, sizeof(LicenceAndMatchPositions));
  cur->keywordPositions = g_array_new(FALSE, FALSE, sizeof(MatchPositionAndType));
  cur->docBufferPositionsAndOffsets = g_array_new(FALSE, FALSE, sizeof(pairPosOff));
  cur->currentLicenceIndex=-1;
}


/**
 * \brief Clean-up all the per scan data structures, freeing any old data.
 * \param thisScan Scanner to clear
 * \callgraph
 */
FUNCTION void freeAndClearScan(struct curScan *thisScan)
{
  /*
   Clear lists
   */
  listClear(&thisScan->regfList, DEALLOC_LIST);
  listClear(&thisScan->offList, DEALLOC_LIST);
  listClear(&thisScan->fLicFoundMap, DEALLOC_LIST);
  listClear(&thisScan->parseList, DEALLOC_LIST);
  listClear(&thisScan->lList, DEALLOC_LIST);
  g_array_free(thisScan->indexList,TRUE);
  cleanTheMatches(thisScan->theMatches);
  g_array_free(thisScan->keywordPositions, TRUE);
  g_array_free(thisScan->docBufferPositionsAndOffsets, TRUE);


  /* remove keys, data and hash table */
  hdestroy();

}

/**
 * \brief Cleans the match array and free the memory
 * \param theMatches The matches list
 */
FUNCTION inline void cleanTheMatches(GArray* theMatches){

  int i;
  for(i=0; i< theMatches->len;  ++i) {
    cleanLicenceAndMatchPositions(getLicenceAndMatchPositions (theMatches , i));
  }
  g_array_free( theMatches, TRUE);
}

/**
 * \brief Cleans the license and match positions object and free the memory
 * \param in The matches object
 */
FUNCTION inline void cleanLicenceAndMatchPositions( LicenceAndMatchPositions* in )
{
  if(in->licenceName) g_free(in->licenceName);
  g_array_free(in->matchPositions, TRUE);
  g_array_free(in->indexList,TRUE);
}

/**
 * \brief Add a license to the matches array
 * \param[in,out] theMatches  The matches array
 * \param[in]     licenceName License to be added
 */
FUNCTION inline void addLicence(GArray* theMatches, char* licenceName ) {
  LicenceAndMatchPositions newMatch;
  newMatch.indexList = cur.indexList;
  cur.indexList=g_array_new(FALSE, FALSE, sizeof(int));

  //! fill this later
  newMatch.matchPositions = g_array_new(FALSE, FALSE, sizeof(MatchPositionAndType));
  newMatch.licenceName = g_strdup(licenceName);
  newMatch.licenseFileId = -1; //initial Value <- check if it was set
  g_array_append_val(theMatches , newMatch);
}

/**
 * \brief Clean the license buffer
 */
inline void cleanLicenceBuffer(){
  g_array_set_size(cur.indexList, 0);
}

/**
 * \brief Remove the last element from license buffer
 * \return True always
 */
inline bool clearLastElementOfLicenceBuffer(){
  if(cur.indexList->len>0)
    g_array_remove_index(cur.indexList, cur.indexList->len -1);
  return true;
}