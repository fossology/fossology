/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "ununpack.h"
#include "externs.h"

/**
 * \file ununpack-zstd.c
 * \brief The universal unpacker - Code to unpack a ZSTd compressed file.
 **/

/**
 * \brief Given a ZSTd file, extract the contents to the directory.
 * \param Source  Pathname of source file
 * \param OrigName Original name of file
 * \param Destination Unpack destination
 * \return 0 on success, non-zero on failure.
 **/
int ExtractZstd(char *Source, const char *OrigName, char *Destination)
{
  char Cmd[FILENAME_MAX * 4]; /* command to run */
  int rc;
  char TempSource[FILENAME_MAX];
  char CWD[FILENAME_MAX];
  char OutputName[FILENAME_MAX] = {'\0'};
  char format[6] = {'\0'};
  char formatCmd[17] = {'\0'};
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
      if (strncasecmp(ext, "zst", 3) == 0) {
        strcpy(format, "zstd");
        break;
      }
      else if (strncasecmp(ext, "tzst", 4) == 0) {
        strcpy(format, "zstd");
        break;
      }
      else if (strncasecmp(ext, "lzma", 4) == 0) {
        strcpy(format, "lzma");
        break;
      }
      else if (strncasecmp(ext, "xz", 2) == 0) {
        strcpy(format, "xz");
        break;
      }
      else if (strncasecmp(ext, "txz", 3) == 0) {
        strcpy(format, "xz");
        break;
      }
      else if (strncasecmp(ext, "lz4", 3) == 0) {
        strcpy(format, "lz4");
        break;
      }
      else if (strncasecmp(ext, "tlz4", 4) == 0) {
        strcpy(format, "lz4");
        break;
      }
    }
    OutputName[i] = OrigName[i];
    i++;
  }

  if (strlen(format) > 1) {
    snprintf(formatCmd, sizeof(formatCmd), " --format=%s", format);
  }

  if (Verbose > 1) {
    printf("CWD: %s\n", CWD);
    if (!Quiet) { fprintf(stderr, "Extracting zstd: %s\n", Source); }
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
    printf("Extracting zstd with output name: %s and format: %s\n", OutputName,
        formatCmd);
  }

  /* Let's extract file */
  if (TempSource[0] != '/') {
    snprintf(Cmd, sizeof(Cmd),
        " (zstd --decompress --output-dir-flat '%s' -o '%s/%s'%s "
        "'%s/%s') 2>/dev/null", Destination, Destination, OutputName, formatCmd,
        CWD, TempSource);
  }
  else {
    snprintf(Cmd, sizeof(Cmd),
        " (zstd --decompress --output-dir-flat '%s' -o '%s/%s'%s "
        "'%s') 2>/dev/null", Destination, Destination, OutputName, formatCmd,
        TempSource);
  }
  rc = WEXITSTATUS(system(Cmd));
  if (rc) {
    fprintf(stderr, "ERROR: Command failed (rc=%d): %s\n", rc, Cmd);
  }

  /* All done */
  if (chdir(CWD) != 0) {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n", __FILE__, __LINE__, CWD);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }
  return (rc);
} /* ExtractZstd() */
