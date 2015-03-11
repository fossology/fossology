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

#include <magic.h>
#include <fcntl.h>

#include "hash.h"
#include "string_operations.h"

#define BUFFSIZE 4096

int readTokensFromFile(const char* fileName, GArray** tokens, const char* delimiters)
{
  *tokens = tokens_new();

  int fd = open(fileName, O_RDONLY);
  if (fd < 0)
  {
    printf("FATAL: can not open %s\n", fileName);
    return 0;
  }

  int guessedConverter = 0;
  iconv_t converter = NULL;

  Token* remainder = NULL;

  char buffer[BUFFSIZE];
  char convertedBuffer[BUFFSIZE];

  ssize_t n;
  size_t leftFromLast = 0;
  while ((n = read(fd, buffer + leftFromLast, sizeof(buffer) - leftFromLast)) > 0)
  {
    size_t len = (size_t) n;
    char* chunk = buffer;
    leftFromLast = 0;

    if (!guessedConverter)
    {
      guessedConverter = 1;
      converter = guessConverter(buffer, len);
    }

    if (converter)
    {
      char* input = buffer;
      size_t inputLeft = (size_t) n;

      char* output = convertedBuffer;
      size_t outputLength = sizeof(convertedBuffer);
      iconv(converter, &input, &inputLeft, &output, &outputLength);

      if (outputLength != sizeof(convertedBuffer))
      {
        chunk = convertedBuffer;
        len = sizeof(convertedBuffer) - outputLength;

        leftFromLast = inputLeft;
        for (size_t i = 0; i < leftFromLast; i++)
        {
          buffer[i] = *input++;
        }
      }
    }

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


iconv_t guessConverter(char* buffer, size_t n)
{
  char* const target = "utf-8";

  iconv_t iconvCookie = NULL;

  gchar* encoding = guessEncoding(buffer, n);
  if (encoding && (strcmp(encoding, target) != 0))
  {
    iconvCookie = iconv_open(target, encoding);
    g_free(encoding);
  }

  return iconvCookie;
}

gchar* guessEncoding(const char* buffer, size_t len)
{
  gchar* result = NULL;

  magic_t cookie = magic_open(MAGIC_MIME);
  magic_load(cookie, NULL);

  const char* resp = magic_buffer(cookie, buffer, len);

  if (!resp)
  {
    printf("magic error: %s\n", magic_error(cookie));
    goto done;
  }

  char* charset = strstr(resp, "charset=");

  if (!charset)
  {
    goto done;
  }

  charset += 8; // len of "charset="

  result = g_strdup(charset);

  done:
  magic_close(cookie);
  return result;
}
