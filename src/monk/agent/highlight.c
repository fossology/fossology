/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "highlight.h"
#include "string_operations.h"

void convertToAbsoluteHighlight(const GArray* tokens, DiffPoint* indexHighlight) {
  Token* firstToken = tokens_index(tokens, indexHighlight->start);

  size_t start = token_position_of(indexHighlight->start, tokens);

  size_t length = 0;
  if (indexHighlight->length > 0)
    length += token_length(*firstToken);

  for (size_t j = indexHighlight->start + 1;
       j < indexHighlight->start + indexHighlight->length; 
       j++) {
    Token* currentToken = tokens_index(tokens, j);
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

DiffPoint getFullHighlightFor(const GArray* tokens, size_t firstMatchedIndex, size_t matchedCount) {
  size_t matchStart = token_position_of(firstMatchedIndex, tokens);
  if (matchedCount < 1)
    return (DiffPoint){matchStart, 0};

  size_t lastMatchedIndex = firstMatchedIndex + matchedCount - 1;
  Token* lastMatchedToken = tokens_index(tokens, lastMatchedIndex);
  size_t matchLength = token_position_of(lastMatchedIndex, tokens)
                       - matchStart
                       + token_length(*lastMatchedToken);

  return (DiffPoint){matchStart, matchLength};
}
