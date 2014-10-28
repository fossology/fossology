/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "highlight.h"
#include "string_operations.h"

void convertToAbsoluteHighlight(GArray* tokens, DiffPoint* indexHighlight) {
  Token* firstToken = &g_array_index(tokens, Token, indexHighlight->start);

  size_t start = token_position_of(indexHighlight->start, tokens);

  size_t length = 0;
  if (indexHighlight->length > 0)
    length += token_length(*firstToken);

  for (size_t j = indexHighlight->start + 1;
       j < indexHighlight->start + indexHighlight->length; 
       j++) {
    Token* currentToken = &g_array_index(tokens, Token, j);
    length += token_length(*currentToken) + currentToken->removedBefore;
  }

  indexHighlight->start = start;
  indexHighlight->length = length;
}

void convertToAbsolutePositions(GArray* diffMatchInfo,
                                GArray* textTokens,
                                GArray* searchTokens) {
  size_t len = diffMatchInfo->len;
  for (size_t i = 0; i < len; i++) {
    DiffMatchInfo *current = &g_array_index(diffMatchInfo, DiffMatchInfo, i);

    convertToAbsoluteHighlight(textTokens, &current->text);
    convertToAbsoluteHighlight(searchTokens, &current->search);
  }
}

DiffPoint getFullHighlightFor(GArray* tokens, size_t firstMatchedIndex, size_t matchedCount) {
  size_t matchStart = token_position_of(firstMatchedIndex, tokens);
  if (matchedCount < 1)
    return (DiffPoint){matchStart, 0};

  size_t lastMatchedIndex = firstMatchedIndex + matchedCount - 1;
  Token* lastMatchedToken = &g_array_index(tokens, Token, lastMatchedIndex);
  size_t matchLength = token_position_of(lastMatchedIndex, tokens)
                       - matchStart
                       + token_length(*lastMatchedToken);

  return (DiffPoint){matchStart, matchLength};
}
