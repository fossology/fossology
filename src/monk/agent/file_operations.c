/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "file_operations.h"
#include <sys/stat.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <fcntl.h>

#include "hash.h"
#include "string_operations.h"

#define BUFFSIZE 4096

GArray* readTokensFromFile(char* fileName, char* delimiters) {
  GArray* tokens = tokens_new();

  int fd = open(fileName, O_RDONLY);
  if (fd < 0) {
    printf("FATAL: can not open %s\n", fileName);
    return tokens;
  }

  Token* remainder = NULL;

  char buffer[BUFFSIZE];
  int n;
  while ((n = read(fd, buffer, BUFFSIZE)) > 0) {
    int addedTokens = streamTokenize(buffer, n, delimiters, &tokens, &remainder);
    if (addedTokens < 0) {
      printf("WARNING: can not complete tokenizing of '%s'\n", fileName);
      break;
    }
  }
  streamTokenize(NULL, 0, NULL, &tokens, &remainder);

  if (remainder)
    free(remainder);

  close(fd);

  return tokens;
}

char* readFile(char* fileName) {
  char* result;
  int fd = open(fileName, O_RDONLY);
  if (fd < 0) {
    printf("FATAL: can not open %s\n", fileName);
    return NULL;
  }

  struct stat statBuf;
  if (fstat(fd, &statBuf) < 0) {
    printf("fstat failure!\n");
    perror(fileName);
    close(fd);
    return NULL;
  }
  if (S_ISDIR(statBuf.st_mode)) {
    printf("file(%s): is a directory\n", fileName);
    close(fd);
    return NULL;
  }

  size_t fileSize = statBuf.st_size + 1;
  char* buffer = malloc(fileSize);
  result = buffer;
  size_t n = 0;
  size_t rem = fileSize-1;
  while ((n = read(fd, buffer, rem)) > 0) {
    rem -= n;
    buffer += n;
  }
  *buffer = '\0';

  // change all '\0' to ' ' to allow scanning binary files
  buffer = result;
  for (n = 0; n < fileSize - 1; n++) {
    if (!*buffer) {
      *buffer = ' ';
    }
    buffer++;
  }

  close(fd);
  return result;
}
