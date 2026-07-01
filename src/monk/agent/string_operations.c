/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG
 SPDX-FileCopyrightText: © 2026 Siemens AG

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

/* Per-character state flags; internal to streamTokenize, converted to
 * TOKEN_* by classifyToken(). */
#define TOK_STATE_ALL_DIGITS 0x10u /* every char so far is a digit */
#define TOK_STATE_HAS_HYPHEN 0x20u /* saw a hyphen after leading digits */
#define TOK_STATE_DIGIT_AFTER 0x40u /* saw a digit after the hyphen */
#define TOK_STATE_ALL_PUNCT 0x80u /* every char is non-alphanumeric */

/** Initial tokenType for a new token: optimistically assume year/punct. */
#define TOK_STATE_INIT (TOK_STATE_ALL_DIGITS | TOK_STATE_ALL_PUNCT)

/* A year is a 4-digit number (e.g. 1998, 2026). Range forms anchor on a
 * 4-digit leading year, e.g. "2000-2009" or "2024-25". Shorter digit runs
 * (section numbers, list items, versions like "10", "20") are NOT years. */
#define YEAR_DIGIT_LEN 4

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
  stateToken->tokenType = TOK_STATE_INIT;
}

/** TOKEN_YEAR: 4-digit number or 4-digit-led range; TOKEN_PUNCT: all
 * non-alnum; otherwise TOKEN_NORMAL. */
static uint8_t classifyToken(const Token* t)
{
  uint8_t f = t->tokenType;

  int isYearLike =
    ((f & TOK_STATE_ALL_DIGITS) && t->length == YEAR_DIGIT_LEN) ||
    ((f & TOK_STATE_HAS_HYPHEN) && (f & TOK_STATE_DIGIT_AFTER));

  if (isYearLike)
    return TOKEN_YEAR;

  if ((f & TOK_STATE_ALL_PUNCT) && t->length > 0)
    return TOKEN_PUNCT;

  return TOKEN_NORMAL;
}

/** Update tokenType flags. Must be called BEFORE incrementing length;
 * the hyphen check reads the current digit count. */
static inline void updateTokenTypeFlags(Token* stateToken, char c)
{
  uint8_t* f = &stateToken->tokenType;

  if (isdigit((unsigned char)c)) {
    if (*f & TOK_STATE_HAS_HYPHEN)
      *f |= TOK_STATE_DIGIT_AFTER;
    *f &= (uint8_t)~TOK_STATE_ALL_PUNCT; /* digit is alphanumeric */
    /* TOK_STATE_ALL_DIGITS preserved */
  } else if (c == '-') {
    if ((*f & TOK_STATE_ALL_DIGITS) && stateToken->length == YEAR_DIGIT_LEN
        && !(*f & TOK_STATE_HAS_HYPHEN)) {
      /* year-range hyphen: exactly 4 leading digits, e.g. "2000-" */
      *f |= TOK_STATE_HAS_HYPHEN;
      *f &= (uint8_t)~TOK_STATE_ALL_DIGITS;
    } else {
      /* not a 4-digit year prefix, second hyphen, or hyphen after non-digits */
      *f &= (uint8_t)~(TOK_STATE_ALL_DIGITS | TOK_STATE_HAS_HYPHEN | TOK_STATE_DIGIT_AFTER);
    }
    /* hyphen is non-alphanumeric: TOK_STATE_ALL_PUNCT preserved */
  } else if (isalpha((unsigned char)c) || (unsigned char)c >= 0x80) {
    /* ASCII letter or any non-ASCII (UTF-8 multibyte) byte is real content:
       it ends year/punctuation-only status. Non-ASCII words (accented
       Latin, Cyrillic, CJK, ...) must not be dropped as punctuation. */
    *f &= (uint8_t)~(TOK_STATE_ALL_DIGITS | TOK_STATE_HAS_HYPHEN |
                     TOK_STATE_DIGIT_AFTER | TOK_STATE_ALL_PUNCT);
  } else {
    /* ASCII punctuation, non-hyphen (e.g. '@', '$', '&', '.', '!') */
    *f &= (uint8_t)~(TOK_STATE_ALL_DIGITS | TOK_STATE_HAS_HYPHEN | TOK_STATE_DIGIT_AFTER);
    /* TOK_STATE_ALL_PUNCT preserved: still possibly all-punctuation */
  }
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
  remToken.tokenType = TOKEN_NORMAL; /* explicit: avoids year short-circuit */

  return tokenEquals(token, &remToken);
}

int streamTokenize(const char* inputChunk, size_t inputSize, const char* delimiters, GArray** output, Token** remainder) {
  GArray* tokens = *output;
  Token* stateToken;

  unsigned int initialTokenCount = tokens->len;

  if (!inputChunk) {
    /* Flush the final in-progress token */
    if ((stateToken = *remainder)) {
      if (stateToken->length > 0 && !isIgnoredToken(stateToken)) {
        stateToken->tokenType = classifyToken(stateToken);
        if (stateToken->tokenType == TOKEN_YEAR)
          stateToken->hashedContent = YEAR_CANONICAL_HASH;
        if (stateToken->tokenType != TOKEN_PUNCT)
          g_array_append_val(tokens, *stateToken);
      }
      free(stateToken);
    }
    *remainder = NULL;
    return 0;
  }

  if (!*remainder) {
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
          /* REM keyword: treat as invisible (existing behavior) */
          stateToken->removedBefore += stateToken->length;
          stateToken->length = 0;
          stateToken->hashedContent = hash_init();
          stateToken->tokenType = TOK_STATE_INIT;
        } else {
          stateToken->tokenType = classifyToken(stateToken);
          if (stateToken->tokenType == TOKEN_PUNCT) {
            /* all-punctuation token: absorbed into the gap before next token */
            stateToken->removedBefore += stateToken->length;
            stateToken->length = 0;
            stateToken->hashedContent = hash_init();
            stateToken->tokenType = TOK_STATE_INIT;
          } else {
            if (stateToken->tokenType == TOKEN_YEAR)
              stateToken->hashedContent = YEAR_CANONICAL_HASH;
            g_array_append_val(tokens, *stateToken);
            initStateToken(stateToken);
          }
        }
      }

      stateToken->removedBefore += delimLen;

      ptr += delimLen;
      readBytes += delimLen;
    } else {
      updateTokenTypeFlags(stateToken, *ptr);

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

GArray* mergeYearTokenSequences(GArray* tokens)
{
  GArray* result = tokens_new();

  for (size_t i = 0; i < tokens->len; ) {
    Token* t = tokens_index(tokens, i);

    if (t->tokenType != TOKEN_YEAR) {
      g_array_append_val(result, *t);
      i++;
      continue;
    }

    /* Start a year group: copy the first year token and absorb followers. */
    Token merged = *t;
    i++;

    while (i < tokens->len) {
      Token* next = tokens_index(tokens, i);
      if (next->tokenType != TOKEN_YEAR) break;
      /* include gap so highlights span the full year group */
      merged.length += next->removedBefore + next->length;
      i++;
    }

    g_array_append_val(result, merged);
  }

  tokens_free(tokens);
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
