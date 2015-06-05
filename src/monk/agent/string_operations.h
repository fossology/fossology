/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2015, Siemens AG

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

GArray* tokenize(const char* inputString, const char* delimiters);

int streamTokenize(const char* inputChunk, size_t inputSize, const char* delimiters,
        GArray** output, Token** remainder);

#define tokenEquals(a, b) ((a)->hashedContent == (b)->hashedContent)

int tokensEquals(const GArray* a, const GArray* b);

size_t token_position_of(size_t index, const GArray* tokens);

#endif // MONK_AGENT_STRING_OPERATIONS_H
