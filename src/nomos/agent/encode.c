/*
 SPDX-FileCopyrightText: © 2006-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Simple utility program for escaping C strings for inclusion in code.
 *
 * Reads the first line of a given file and then outputs (to stdout) text
 * suitable for initializing an licSpec_t structure. Used by GENSEARCHDATA
 * script for processing STRINGS.in.
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/** Buffer size to use */
#define myBUFSIZ BUFSIZ


int main(int argc, char **argv)
{
  char str[myBUFSIZ];
  char *cp;
  int i;
  int len = 0;
  FILE *fp;

  if (argc == 1) {
    fprintf(stderr, "Usage: %s file\n", *argv);
    exit(1);
  }

  /**
   * Open the file (or stdin)
   */
  if (strcmp(argv[1], "-") == 0) {
    fp = stdin;
  }
  else if ((fp = fopen(argv[1], "r")) == (FILE *) NULL) {
    perror(argv[1]);
    exit(1);
  }

  /**
   * read the first line and remove any trailing newline.
   */
  if (fgets(str, sizeof(str), fp) == (char *) EOF) {
    perror(argv[1]);
    exit(1);
  }
  if ((cp = strrchr(str, '\n')) != (char *) NULL) {
    *cp = '\0';
  }
  len = strlen(str);

  printf("{%d, \"", len);
  for (i = 0; i < len; i++) {
    printf("\\%o", str[i] & 0xff);
  }
  printf("\\0\"}\n");


  return 0;
}
