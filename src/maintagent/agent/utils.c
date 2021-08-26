/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2019 Siemens AG

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
 * \file
 * \brief Miscellaneous utility functions for maintagent
 */

#include "maintagent.h"

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
 * Since gold files are stored in following format, we need to iterate on path
 * for 3 levels before we reach a file.
 * @verbatim
 *   /srv/fossology/repository/localhost/gold
 *   ├── fc
 *   │   └── 49
 *   │       └── 77
 *   │           └── fc49776c2a..... < the actual file
 * @endverbatim
 * The function will iteratively go to deper level till it reaches level 0, i.e.
 * the file level. Once at file level, will call checkGoldExists() with
 * individual pfile elements.
 *
 * @param path  Root of path to traverse
 * @param level How many more levels to go (0 if we've reached file level)
 */
FUNCTION void recurseDir(char* path, int level)
{
  DIR* dir;
  struct dirent* entry;
  dir = opendir(path);
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

        recurseDir(nextpath, level - 1);
      }
      else
      {
        // entry is a file
        char delim[] = ".";
        char sha1[41];
        char md5[33];
        long fsize;
        char* ptr;

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
        checkGoldExists(sha1, md5, fsize);
      }
    }
  }
}

/**
 * @brief Check if given checksums exists in DB, if not call deleteGoldFile()
 *
 * @param sha1  SHA1 of the file
 * @param md5   MD5 of the file
 * @param fsize size of the file
 */
FUNCTION void checkGoldExists(char* sha1, char* md5, long fsize)
{
  PGresult* result;
  int countTuples;
  fo_dbManager_PreparedStatement* existsStatement;
  char sql[] =
      "SELECT 1 FROM pfile "
      "WHERE pfile_md5 = $1 AND pfile_sha1 = $2 AND "
      "pfile_size = $3;";

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
    deleteGoldFile(sha1, md5, fsize);
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
 * @sa fo_RepMkPath()
 */
FUNCTION void deleteGoldFile(char* sha1, char* md5, long fsize)
{
  char filename[myBUFSIZ];
  char* goldFilePath;

  snprintf(filename, myBUFSIZ, "%s.%s.%ld", sha1, md5, fsize);
  goldFilePath = fo_RepMkPath("gold", filename);
  unlink(goldFilePath);
  free(goldFilePath);
}
