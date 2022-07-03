/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdio.h>
#include <sys/stat.h>
#include "utility.h"

/**
 * \file
 * \brief Judge if the file or directory is existed not
 * \param path_name The file or directory name including path
 * \return existed or not, 0: not existed, 1: existed
 */
int file_dir_existed(char *path_name)
{
  struct stat sts;
  int existed = 1; // 0: not existed, 1: existed, default existed
  if ((stat (path_name, &sts)) == -1)
  {
    //printf ("The file or dir %s doesn't exist...\n", path_name);
    existed = 0;
  }
  return existed;
}

/**
 * \brief Remove all files under dirpath
 * \param path_name The file or directory name including path
 * \return existed or not, 0: not existed, 1: existed
 */
int RemoveDir(char *dirpath)
{
  char RMcmd[MAX_LENGTH];
  int rc;
  memset(RMcmd, '\0', sizeof(RMcmd));
  snprintf(RMcmd, MAX_LENGTH-1, "rm -rf '%s'", dirpath);
  rc = system(RMcmd);
  return rc;
} /* RemoveDir() */

#if 0
int main()
{
  int result = file_dir_existed("./test-data");
  printf("result is:%d\n", result);
  return 0;
}
#endif
