/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

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
*/

#include "file_operations.h"
#include <sys/stat.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>

#include <fcntl.h>

#include "hash.h"
#include "string_operations.h"
#include "encoding.h"

#define BUFFSIZE 4096

int readTokensFromFile(const char* fileName, GArray** tokens, const char* delimiters)
{
  int fd = open(fileName, O_RDONLY);
  if (fd < 0)
  {
    printf("FATAL: can not open %s\n", fileName);
    return 0;
  }

  *tokens = tokens_new();

  int needConverter = 1;
  iconv_t converter = NULL;

  Token* remainder = NULL;

  char buffer[BUFFSIZE];
  char convertedBuffer[BUFFSIZE];

  ssize_t n;
  size_t leftFromLast = 0;
  while ((n = read(fd, buffer + leftFromLast, sizeof(buffer) - leftFromLast)) > 0)
  {
    size_t len = (size_t) n + leftFromLast;
    char* chunk = buffer;
    leftFromLast = 0;

    if (needConverter)
    {
      needConverter = 0;
      converter = guessConverter(buffer, len);
    }

    if (converter)
    {
      char* input = buffer;
      size_t inputLeft = len;

      char* output = convertedBuffer;
      size_t outputLength = sizeof(convertedBuffer);
      iconv(converter, &input, &inputLeft, &output, &outputLength);

      if (outputLength != sizeof(convertedBuffer)) {
        chunk = convertedBuffer;
        len = sizeof(convertedBuffer) - outputLength;

        leftFromLast = inputLeft;
        for (size_t i = 0; i < leftFromLast; i++)
        {
          buffer[i] = *input++;
        }
      } else {
        // the raw buffer is full and we could not write to the converted buffer
        printf("WARNING: cannot re-encode '%s', going binary from now on\n", fileName);
        iconv_close(converter);
        converter = NULL;
      }
    }

    /* N.B. this tokenizes inside the re-encoded buffer:
     * the offsets found are byte positions in the UTF-8 stream, not file positions
     **/
    int addedTokens = streamTokenize(chunk, len, delimiters, tokens, &remainder);
    if (addedTokens < 0)
    {
      printf("WARNING: can not complete tokenizing of '%s'\n", fileName);
      break;
    }
  }

  streamTokenize(buffer, leftFromLast, delimiters, tokens, &remainder);
  streamTokenize(NULL, 0, NULL, tokens, &remainder);

  close(fd);

  if (converter)
  {
    iconv_close(converter);
  }

  return 1;
}
