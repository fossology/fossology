/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_STRING_OPERATIONS_H
#define MONK_AGENT_STRING_OPERATIONS_H

#include <glib.h>
#include <string.h>
#include <stdint.h>

typedef struct {
  unsigned int length;
  unsigned int removedBefore;
  uint32_t hashedContent;
} Token;

#define token_length(token) (token).length

#define tokens_new() g_array_new(FALSE, FALSE, sizeof (Token))
#define tokens_free(p) g_array_free((p), TRUE)

#define tokens_index(tokens,i) &g_array_index((tokens), Token, (i))

GArray* tokenize(const char* inputString, const char* delimiters);

int streamTokenize(const char* inputChunk, size_t inputSize, const char* delimiters,
        GArray** output, Token** remainder);

#define tokenEquals(a, b) (((a)->length == (b)->length ) && ((a)->hashedContent == (b)->hashedContent))

int tokensEquals(const GArray* a, const GArray* b);

size_t token_position_of(size_t index, const GArray* tokens);

char* normalize_escape_string(char* input);

#endif // MONK_AGENT_STRING_OPERATIONS_H
