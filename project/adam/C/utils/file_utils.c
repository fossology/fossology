/*********************************************************************
Copyright (C) 2009, 2010 Hewlett-Packard Development Company, L.P.

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
 *********************************************************************/

#include "file_utils.h"

void readtomax(char *filename, char **buffer, size_t max) {
  // local variables
  FILE *pFile;
  long lSize;
  size_t result;

  // open and check to make sure the file openned
  pFile = fopen(filename, "rb");
  if (pFile==NULL) {
    fputs("File error.\n", stderr);
    exit(1);
  }

  // read the size of the file
  fseek(pFile, 0, SEEK_END);
  lSize = ftell(pFile);
  rewind(pFile);

  // if the maximum provided is zero or negative, don't put a limit
  // on the number of bytes to read in
  if (max > 0 && max < lSize) {
    lSize = max;
  }

  // allocate memory for the buffer that will store the file. an extra
  // byte is allocated for the null terminator
  *buffer = (char*)calloc(lSize+1, sizeof(char));
  if (*buffer == NULL) {
    fputs("Memory error.\n",stderr);
    exit(2);
  }

  // read the entire file in one go
  result = fread(*buffer, 1, lSize, pFile);
  if (result != lSize) {
    fputs("Reading error",stderr); exit(3);
  }

  // append the null terminal and close the file
  buffer[0][result] = '\0';
  fclose(pFile);
}

void openfile(char *filename, char **buffer) {
  // since openfile is functionally equivalent to calling
  // read to max with no limit, that is what openfile does
  readtomax(filename, buffer, -1);
}

