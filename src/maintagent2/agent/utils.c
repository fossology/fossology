/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Miscellaneous utility functions for maintagent
 */

#include "maintagent.h"

/* monotonic time helper */
double now_monotonic_seconds(void)
{
  struct timespec ts;
  #if defined(CLOCK_MONOTONIC)
    clock_gettime(CLOCK_MONOTONIC, &ts);
  #else
    clock_gettime(CLOCK_REALTIME, &ts);
  #endif
  return ts.tv_sec + ts.tv_nsec / 1e9;
}

/* log start marker; always a NOTICE, extra detail only when agent_verbose >= 3 */
void log_action_start(const char* action)
{
  time_t t = time(NULL);
  /* format ISO8601 time */
  char timestr[64];
  struct tm tm;
  if (localtime_r(&t, &tm)) {
    strftime(timestr, sizeof(timestr), "%Y-%m-%dT%H:%M:%S%z", &tm);
  } else {
    snprintf(timestr, sizeof(timestr), "%ld", (long)t);
  }

  /* shorten file path to start from "maintagent2/" if present */
  const char *fullpath = __FILE__;
  const char *shortpath = strstr(fullpath, "maintagent2/");
  if (!shortpath) shortpath = fullpath;

  LOG_NOTICE("START %s %ld", action, (long)t);

  if (agent_verbose >= 3) {
    LOG_NOTICE("%s %s Action %s started at %ld", timestr, shortpath, action, (long)t);
  }
}

/* log end marker and duration; always a NOTICE, extra detail only when agent_verbose >= 3 */
void log_action_end(const char* action, double start)
{
  double end = now_monotonic_seconds();
  double dur = end - start;
  time_t t = time(NULL);
  /* format ISO8601 time */
  char timestr[64];
  struct tm tm;
  if (localtime_r(&t, &tm)) {
    strftime(timestr, sizeof(timestr), "%Y-%m-%dT%H:%M:%S%z", &tm);
  } else {
    snprintf(timestr, sizeof(timestr), "%ld", (long)t);
  }

  /* shorten file path to start from "maintagent2/" if present */
  const char *fullpath = __FILE__;
  const char *shortpath = strstr(fullpath, "maintagent2/");
  if (!shortpath) shortpath = fullpath;

  LOG_NOTICE("END %s %ld duration=%.3f", action, (long)t, dur);

  if (agent_verbose >= 3) {
    LOG_NOTICE("%s %s Action %s ended at %ld (duration=%.3f s)", timestr, shortpath, action, (long)t, dur);
  }
}

/**
 * @brief Exit function.  This does all cleanup and should be used
 *        instead of calling exit() or main() return.
 *
 * @param exitVal Exit value
 * @returns void Calls exit()
 */
FUNCTION void exitNow(int exitVal)
{
  if (pgConn) PQfinish(pgConn);
  if (dbManager) fo_dbManager_free(dbManager);

  if (exitVal) LOG_ERROR("Exiting with status %d", exitVal);

  fo_scheduler_disconnect(exitVal);
  exit(exitVal);
} /* ExitNow() */

/**
 * @brief Helper function to upper case a string
 *
 * @param s Input string
 * @return char* Upper case string
 * @sa toupper()
 */
FUNCTION char* strtoupper(char* s)
{
  char* p = s;
  while ((*p = toupper(*p)))
    p++;
  return s;
}

/**
 * @brief Recursively read directory till level is 0
 *
 * Since files in repository are stored in following format, we need to iterate
 * on path for 3 levels before we reach a file.
 * @verbatim
 *   /srv/fossology/repository/localhost/gold
 *   ├── fc
 *   │   └── 49
 *   │       └── 77
 *   │           └── fc49776c2a..... < the actual file
 * @endverbatim
 * The function will iteratively go to deper level till it reaches level 0, i.e.
 * the file level. Once at file level, will call checkPFileExists() with
 * individual pfile elements.
 *
 * @param type  Type of repo file (gold/files)
 * @param path  Root of path to traverse
 * @param level How many more levels to go (0 if we've reached file level)
 */
FUNCTION void recurseDir(const char* type, char* path, int level)
{
  DIR* dir;
  struct dirent* entry;
  dir = opendir(path);
  if (dir == NULL)
  {
    LOG_ERROR("Unable to open dir: '%s'", path);
    LOG_ERROR("Error: %s", strerror(errno));
    return;
  }
  while ((entry = readdir(dir)) != NULL)
  {
    if (strcmp(entry->d_name, ".") != 0 && strcmp(entry->d_name, "..") != 0)
    {
      char nextpath[myBUFSIZ];
      if (level > 0)
      {
        // Have not reached to file yet
        strncpy(nextpath, path, myBUFSIZ - 1);
        strncat(nextpath, "/", myBUFSIZ - 1);
        strncat(nextpath, entry->d_name, myBUFSIZ - 1);

        recurseDir(type, nextpath, level - 1);
      }
      else
      {
        // entry is a file
        char delim[] = ".";
        char sha1[41];
        char md5[33];
        long fsize;
        char* ptr;

        memset(sha1, '\0', 41);
        memset(md5, '\0', 33);
        strncpy(nextpath, entry->d_name, myBUFSIZ - 1);
        ptr = strtok(nextpath, delim);
        if (ptr == NULL)
        {
          LOG_FATAL("Unable to split path '%s' for pfile.", nextpath);
          exitNow(-105);
        }
        strncpy(sha1, ptr, 40);
        ptr = strtok(NULL, delim);
        strncpy(md5, ptr, 32);
        ptr = strtok(NULL, delim);
        fsize = atol(ptr);
        checkPFileExists(sha1, md5, fsize, type);
      }
    }
  }
  closedir(dir);
}

/**
 * @brief Check if given checksums exists in DB, if not call deleteRepoFile()
 *
 * @param sha1  SHA1 of the file
 * @param md5   MD5 of the file
 * @param fsize size of the file
 * @param type  Type of file
 */
FUNCTION void checkPFileExists(char* sha1, char* md5, long fsize,
                               const char* type)
{
  PGresult* result;
  int countTuples;
  fo_dbManager_PreparedStatement* existsStatement;
  char sql[] =
      "WITH pf AS ("
      "SELECT pfile_pk FROM pfile "
      "WHERE pfile_md5 = $1 AND pfile_sha1 = $2 AND pfile_size = $3) "
      "SELECT 1 AS exists FROM uploadtree INNER JOIN pf "
      "ON pf.pfile_pk = pfile_fk "
      "UNION ALL "
      "SELECT 1 AS exists FROM upload INNER JOIN pf "
      "ON pf.pfile_pk = pfile_fk;";

  existsStatement = fo_dbManager_PrepareStamement(dbManager, "checkPfileExists",
                                                  sql, char*, char*, long);
  result = fo_dbManager_ExecPrepared(existsStatement, strtoupper(md5),
                                     strtoupper(sha1), fsize);
  if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__))
  {
    exitNow(-140);
  }

  countTuples = PQntuples(result);
  if (countTuples < 1)
  {
    deleteRepoFile(sha1, md5, fsize, type);
    fo_scheduler_heart(1);
  }
  else
  {
    fo_scheduler_heart(0);
  }
  PQclear(result);
}

/**
 * @brief Take a file checksum, generate repo path and call unlink()
 *
 * @param sha1  SHA1 of the file
 * @param md5   MD5 of the file
 * @param fsize size of the file
 * @param type  Type of file (gold/files)
 * @sa fo_RepMkPath()
 */
FUNCTION void deleteRepoFile(char* sha1, char* md5, long fsize,
                             const char* type)
{
  char filename[myBUFSIZ];
  char* goldFilePath;

  snprintf(filename, myBUFSIZ, "%s.%s.%ld", sha1, md5, fsize);
  goldFilePath = fo_RepMkPath(type, filename);
  unlink(goldFilePath);
  free(goldFilePath);
}
