/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_STRING_OPERATIONS_H
#define MONK_AGENT_STRING_OPERATIONS_H

#include <glib.h>
#include <string.h>
#include <stdint.h>

/* Token semantic types stored in tokenType after classification */
#define TOKEN_NORMAL 0 /**< ordinary word, exact match required */
#define TOKEN_YEAR 1 /**< year or year-range, matches any other TOKEN_YEAR */
#define TOKEN_PUNCT 2 /**< all-punctuation token, treated as invisible */

/** Canonical hash assigned to every TOKEN_YEAR so they compare equal */
#define YEAR_CANONICAL_HASH 0x00594541u /* "YEA" */

typedef struct {
  unsigned int length; /**< byte length of the token in the source text */
  unsigned int removedBefore; /**< delimiter bytes before this token */
  uint32_t hashedContent; /**< rolling hash of the token characters */
  uint8_t tokenType; /**< TOKEN_NORMAL, TOKEN_YEAR or TOKEN_PUNCT */
} Token;

#define token_length(token) (token).length

#define tokens_new() g_array_new(FALSE, FALSE, sizeof (Token))
#define tokens_free(p) g_array_free((p), TRUE)

#define tokens_index(tokens,i) &g_array_index((tokens), Token, (i))

GArray* tokenize(const char* inputString, const char* delimiters);

int streamTokenize(const char* inputChunk, size_t inputSize, const char* delimiters,
        GArray** output, Token** remainder);

/** TOKEN_YEAR matches any TOKEN_YEAR; otherwise exact (length + hash). */
#define tokenEquals(a, b) \
  (((a)->tokenType == TOKEN_YEAR && (b)->tokenType == TOKEN_YEAR) || \
   (((a)->length == (b)->length) && ((a)->hashedContent == (b)->hashedContent)))

int tokensEquals(const GArray* a, const GArray* b);

size_t token_position_of(size_t index, const GArray* tokens);

char* normalize_escape_string(char* input);

/** Collapse consecutive TOKEN_YEAR runs into one spanning the full byte
 * range (gaps included). Frees input; caller owns the returned array. */
GArray* mergeYearTokenSequences(GArray* tokens);

#endif // MONK_AGENT_STRING_OPERATIONS_H
