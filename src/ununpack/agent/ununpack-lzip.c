/*
 SPDX-FileCopyrightText: © 2025 Siemens Healthineers AG
 SPDX-FileContributor: Sushant Kumar <sushant.kumar@siemens-healthineers.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "ununpack.h"
#include "externs.h"

/**
 * \file ununpack-lzip.c
 * \brief The universal unpacker - Code to unpack an lzip compressed file.
 **/

/**
 * \brief Given an lzip file, extract the contents to the directory.
 * \param Source  Pathname of source file
 * \param OrigName Original name of file
 * \param Destination Unpack destination
 * \return 0 on success, non-zero on failure.
 **/
int ExtractLzip(char *Source, const char *OrigName, char *Destination)
{
  char Cmd[FILENAME_MAX * 4]; /* command to run */
  int rc;
  char TempSource[FILENAME_MAX];
  char CWD[FILENAME_MAX];
  char OutputName[FILENAME_MAX] = {'\0'};
  int i;

  /* judge if the parameters are empty */
  if ((NULL == Source) || (!strcmp(Source, "")) || (NULL == Destination) ||
      (!strcmp(Destination, ""))) {
    return 1;
  }

  if (getcwd(CWD, sizeof(CWD)) == NULL) {
    fprintf(stderr, "ERROR: directory name longer than %d characters\n",
        (int) sizeof(CWD));
    return (-1);
  }

  i = 0;
  while (OrigName[i] != '\0') {
    if (OrigName[i] == '.') {
      const char *ext = &OrigName[i + 1];
      if (strncasecmp(ext, "lz", 2) == 0) {
        break;
      }
      else if (strncasecmp(ext, "tlz", 3) == 0) {
        /* Usually tlz implies tar.lz, stripping extension leaves base name.
           Subsequent file type detection will handle the resulting tar. */
        break;
      }
    }
    OutputName[i] = OrigName[i];
    i++;
  }
  /* Null terminate the output name explicitly */
  OutputName[i] = '\0';

  if (Verbose > 1) {
    printf("CWD: %s\n", CWD);
    if (!Quiet) { fprintf(stderr, "Extracting lzip: %s\n", Source); }
  }

  if (chdir(Destination) != 0) {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n",
        __FILE__, __LINE__, Destination);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }

  if (TaintString(TempSource, FILENAME_MAX, Source, 1, NULL)) {
    return (-1);
  }
  memset(Cmd, '\0', sizeof(Cmd));

  if (Verbose > 1) {
    printf("Extracting lzip with output name: %s\n", OutputName);
  }

  /* lzip options:
   * -d, --decompress
   * -k, --keep (keep input file, consistent with how standard tools behave)
   * -f, --force (overwrite output if exists)
   * -o, --output (specify output filename)
   */
  if (TempSource[0] != '/') {
    snprintf(Cmd, sizeof(Cmd),
        " (lzip --decompress --keep --force -o '%s/%s' "
        "'%s/%s') 2>/dev/null", Destination, OutputName,
        CWD, TempSource);
  }
  else {
    snprintf(Cmd, sizeof(Cmd),
        " (lzip --decompress --keep --force -o '%s/%s' "
        "'%s') 2>/dev/null", Destination, OutputName,
        TempSource);
  }
  
  rc = WEXITSTATUS(system(Cmd));
  if (rc) {
    fprintf(stderr, "ERROR: Command failed (rc=%d): %s\n", rc, Cmd);
  }

  if (chdir(CWD) != 0) {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n", __FILE__, __LINE__, CWD);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }
  return (rc);
} /* ExtractLzip() */
