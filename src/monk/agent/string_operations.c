/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#define _GNU_SOURCE
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <ctype.h>
#include <stdarg.h>

#include "string_operations.h"
#include "hash.h"
#include "monk.h"

#define MAX_TOKENS_ARRAY_SIZE 4194304
#define MAX_DELIMIT_LEN 255

unsigned splittingDelim(char a, const char* delimiters) {
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

unsigned specialDelim(const char* z){
  char a, b, c;
  a = *z;
  b = *(z+1);
  c = *(z+2);
  if( a=='/') {
    if (b=='/' || b=='*')
      return 2;
  }
  else if( a=='*') {
    if (b=='/')
      return 2;
    return 1;
  }
  else if( a==':' && b==':') {
    return 2;
  }
  else if ((a==b && b==c) && (a=='"' || a=='\'')) {
    return 3;
  }
  else if (a=='d' && b=='n' && c=='l') {
    // dnl comments
    return 3;
  }
  return 0;
}

static inline void initStateToken(Token* stateToken) {
  stateToken->hashedContent = hash_init();
  stateToken->length = 0;
  stateToken->removedBefore = 0;
}

static int isIgnoredToken(Token* token) {
  Token remToken;

#ifndef MONK_CASE_INSENSITIVE
  remToken.hashedContent = hash("REM");
#else
  remToken.hashedContent = hash("rem");
#endif
  remToken.length = 3;
  remToken.removedBefore = 0;

  return tokenEquals(token, &remToken);
}

int streamTokenize(const char* inputChunk, size_t inputSize, const char* delimiters, GArray** output, Token** remainder) {
  GArray* tokens = *output;
  Token* stateToken;

  unsigned int initialTokenCount = tokens->len;

  if (!inputChunk) {
    if ((stateToken = *remainder)) {
      if ((stateToken->length > 0) && !isIgnoredToken(stateToken)) {
        g_array_append_val(tokens, *stateToken);
      }
      free(stateToken);
    }
    *remainder = NULL;
    return 0;
  }

  if (!*remainder) {
    //initialize state
    stateToken = malloc(sizeof (Token));
    *remainder = stateToken;
    initStateToken(stateToken);
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
    unsigned delimLen = 0;
    if (inputSize - readBytes >= 2) {
      delimLen = specialDelim(ptr);
    }
    if (!delimLen) {
      delimLen = splittingDelim(*ptr, delimiters);
    }

    if (delimLen > 0) {
      if (stateToken->length > 0) {
        if (isIgnoredToken(stateToken)) {
          stateToken->removedBefore += stateToken->length;
          stateToken->length = 0;
          stateToken->hashedContent = hash_init();
        } else {
          g_array_append_val(tokens, *stateToken);
          initStateToken(stateToken);
        }
      }

      stateToken->removedBefore += delimLen;

      ptr += delimLen;
      readBytes += delimLen;
    } else {
#ifndef MONK_CASE_INSENSITIVE
      const char* newCharPtr = ptr;
#else
      char newChar = g_ascii_tolower(*ptr);
      const char* newCharPtr = &newChar;
#endif
      hash_add(newCharPtr, &(stateToken->hashedContent));

      stateToken->length++;

      ptr += 1;
      readBytes += 1;
    }
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

int tokensEquals(const GArray* a, const GArray* b) {
  if (b->len != a->len)
    return 0;

  for (size_t i = 0; i < a->len; i++) {
    Token* aToken = tokens_index(a, i);
    Token* bToken = tokens_index(b, i);

    if (!tokenEquals(aToken, bToken))
      return 0;
  }

  return 1;
}

size_t token_position_of(size_t index, const GArray* tokens) {
  size_t result = 0;
  size_t previousLength = 0;

  size_t limit = MIN(index + 1, tokens->len);

  for (size_t i = 0; i < limit; i++) {
    Token* token = tokens_index(tokens, i);
    result += token->removedBefore + previousLength;
    previousLength = token_length(*token);
  }

  if (index == tokens->len) {
    result += previousLength;
  }

  if (index > tokens->len) {
    result += previousLength;
    printf("WARNING: requested calculation of token index after the END token\n");
  }

  return result;
}

inline char* normalize_escape_string(char* input)
{
  char* p = input;
  char* q;
  char ret[MAX_DELIMIT_LEN];
  int i = 0;
  bool flag = false;
  bool space = false;
  while (*p)
  {
    if (*p == ' ')
    {
      space = true;
    }
    if (*p == '\\')
    {
      q = p + 1;
      if (*q == 'a')
      {
        ret[i] = '\a';
        flag = true;
      }
      else if (*q == 'b')
      {
        ret[i] = '\b';
        flag = true;
      }
      else if (*q == 'f')
      {
        ret[i] = '\f';
        flag = true;
      }
      else if (*q == 'n')
      {
        ret[i] = '\n';
        flag = true;
      }
      else if (*q == 'r')
      {
        ret[i] = '\r';
        flag = true;
      }
      else if (*q == 't')
      {
        ret[i] = '\t';
        flag = true;
      }
      else if (*q == 'v')
      {
        ret[i] = '\v';
        flag = true;
      }
      else if (*q == '\\')
      {
        ret[i] = '\\';
        flag = true;
      }
      if (flag == true)
      {
        flag = false;
        p = q + 1;
        i++;
        continue;
      }
    }
    ret[i++] = *p;
    p++;
  }
  if (space != true)
  {
    ret[i++] = ' ';
  }
  ret[i] = '\0';
  return g_strdup(ret);
}
