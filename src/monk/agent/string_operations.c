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

int streamTokenize(char * inputChunk, int inputSize, const char * delimiters,
        GArray ** output, Token ** remainder) {
  GArray * tokens = *output;
  Token * stateToken;

  unsigned int initialTokenCount = tokens->len;

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

  char * ptr = inputChunk;

  while (ptr - inputChunk < inputSize) {
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
      hash_add(ptr, &(stateToken->hashedContent));
      stateToken->length++;
    }
    ptr++;
  }

  return tokens->len - initialTokenCount;
}

GArray* tokenize(char* inputString, const char* delimiters) {
  GArray* tokenArray = tokens_new();

  char* remainder = NULL;
  char* currentPos = inputString;
  Token token;
  char * tokenString = strtok_r(inputString, delimiters, &remainder);
  while (tokenString != NULL) {
    token.hashedContent = hash(tokenString);
    token.length = strlen(tokenString);
    token.removedBefore = tokenString - currentPos;

    currentPos += token.removedBefore + token.length;
    g_array_append_val(tokenArray, token);
    tokenString = strtok_r(NULL, delimiters, &remainder);
  }

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

StringBuilder* stringBuilder_new(){
  StringBuilder* result = malloc(sizeof(StringBuilder));
  result->contents = g_array_new(TRUE, FALSE, sizeof(gchar*));
  result->length = 0;
  return result;
}

void stringBuilder_free(StringBuilder* stringBuilder) {
  for (size_t i = 0; i < stringBuilder->contents->len; i++) {
    g_free(g_array_index(stringBuilder->contents, gchar*, i));
  }
  g_array_free(stringBuilder->contents, TRUE);
  free(stringBuilder);
}

void stringBuilder_printf(StringBuilder* stringBuilder, const gchar* format, ...){
  va_list args;
  va_start(args, format);
  gchar* value = g_strdup_vprintf(format, args);
  va_end(args);
  g_array_append_val(stringBuilder->contents, value);
  stringBuilder->length += strlen(value);
}

char* stringBuilder_build(StringBuilder* stringBuilder){
  char* result = malloc(stringBuilder->length + 1);
  char* temporaryBuffer = result;
  guint pieceCount = stringBuilder->contents->len;
  for (guint i = 0; i < pieceCount; i++) {
    char* nextString = g_array_index(stringBuilder->contents, gchar*, i);
    temporaryBuffer = g_stpcpy(temporaryBuffer, nextString);
  }

  return result;
}