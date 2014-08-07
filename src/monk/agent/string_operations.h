/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
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

#define token_length(token) ((Token) (token)).length

#define tokens_new() g_array_new(TRUE, FALSE, sizeof (Token))

GArray* tokenize(char* inputString, const char* delimiters);

int streamTokenize(char * inputChunk, int inputSize, const char * delimiters, 
        GArray ** output, Token ** remainder);

#define tokenEquals(a, b) (((Token*) a)->hashedContent == ((Token*) b)->hashedContent)

int tokensEquals(GArray* a, GArray* b);

size_t token_position_of(size_t index, GArray * tokens);

typedef struct {
  GArray * contents;
  size_t length;
} StringBuilder;

StringBuilder* stringBuilder_new();

void stringBuilder_free(StringBuilder* stringBuilder);

void stringBuilder_printf(StringBuilder* stringBuilder, const char* format, ...);

char* stringBuilder_build(StringBuilder* stringBuilder);
#endif
