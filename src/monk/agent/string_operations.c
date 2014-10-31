/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#define _GNU_SOURCE
#include <stdio.h>
#include <stdbool.h>
#include <string.h>
#include <stdlib.h>
#include <ctype.h>
#include <stdarg.h>

#include "string_operations.h"
#include "hash.h"
#include "monk.h"

#define MAX_TOKENS_ARRAY_SIZE 4194304

inline int isDelim(char a, const char * delimiters) {
  if (a == '\0')
    return 1;
  const char * ptr = delimiters;
  while (*ptr) {
    if (*ptr == a)
      return 1;
    ptr++;
  }

  return 0;
}

int streamTokenize(const char* inputChunk, size_t inputSize, const char* delimiters,
  GArray** output, Token** remainder) {
  GArray* tokens = *output;
  Token* stateToken;

  unsigned int initialTokenCount = tokens->len;

  if (!inputChunk) {
    stateToken = *remainder;
    if ((stateToken) && (stateToken->length > 0))
      g_array_append_val(tokens, *stateToken);
  }

  if (!*remainder) {
    //initialize state
    stateToken = malloc(sizeof (Token));
    *remainder = stateToken;

    stateToken->length = 0;
    stateToken->hashedContent = hash_init();
    stateToken->removedBefore = 0;
  } else {
    stateToken = *remainder;
  }

  if (tokens->len >= MAX_TOKENS_ARRAY_SIZE) {
    printf("WARNING: stream has more tokens than maximum allowed\n");
    return -1;
  }

  const char* ptr = inputChunk;

  size_t readBytes = 0;
  while (readBytes < inputSize) {
    if (isDelim(*ptr, delimiters)) {
      if (stateToken->length > 0) {
        g_array_append_val(tokens, *stateToken);
        stateToken->hashedContent = hash_init();
        stateToken->length = 0;
        stateToken->removedBefore = 1;
      } else {
        stateToken->removedBefore++;
      }
    } else {
#ifndef MONK_CASE_INSENSITIVE
      const char* newCharPtr = ptr;
#else
      char newChar = g_ascii_tolower(*ptr);
      const char* newCharPtr = &newChar;
#endif
      hash_add(newCharPtr, &(stateToken->hashedContent));
      stateToken->length++;
    }
    ptr++;
    readBytes++;
  }

  return tokens->len - initialTokenCount;
}

GArray* tokenize(const char* inputString, const char* delimiters) {
  GArray* tokenArray = tokens_new();

  Token* remainder = NULL;

  size_t inputLength = strlen(inputString);

#define CHUNKS 4096
  size_t chunksCount = inputLength / CHUNKS;
  for (size_t i = 0; i < chunksCount; i++) {
    int addedTokens = streamTokenize(inputString + i * CHUNKS, CHUNKS, delimiters, &tokenArray, &remainder);
    if (addedTokens < 0) {
      printf("WARNING: can not complete tokenizing of '%.30s...'\n", inputString);
      break;
    }
  }
  streamTokenize(inputString + chunksCount * CHUNKS, MIN(CHUNKS, inputLength - chunksCount * CHUNKS),
                 delimiters, &tokenArray, &remainder);
  streamTokenize(NULL, 0, NULL, &tokenArray, &remainder);
#undef CHUNKS

  return tokenArray;
}

int tokensEquals(GArray* a, GArray* b) {
  if (b->len != a->len)
    return 0;

  for (size_t i = 0; i < a->len; i++) {
    Token* aToken = &g_array_index(a, Token, i);
    Token* bToken = &g_array_index(b, Token, i);

    if (!tokenEquals(aToken, bToken))
      return 0;
  }

  return 1;
}

size_t token_position_of(size_t index, GArray* tokens) {
  size_t result = 0;
  size_t previousLength = 0;

  for (size_t i = 0; i <= index; i++) {
    Token* token = &g_array_index(tokens, Token, i);
    result += token->removedBefore + previousLength;
    previousLength = token_length(*token);
  }

  return result;
}
