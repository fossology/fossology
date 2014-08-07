/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include "diff.h"
#include "monk.h"

#include "string_operations.h"
#include "_squareVisitor.h"
#include <stdlib.h>

/**
 @brief perform a full match search between two tokenized texts

 @param textTokens   array containing the Tokens of the text in which we are searching
 @param searchTokens array containing the Tokens of the reference text to be searched

 @param matchStart the position of the beginning of the match will be written here

 @return boolean indicating successful match
 ****************************************************/
int findMatchFull(GArray* textTokens, GArray* searchTokens, size_t* matchStart) {
  size_t textLength = textTokens->len;
  size_t searchLength = searchTokens->len;

  if (!searchLength || !textLength)
    return 0;

  size_t matched = 0;
  size_t lastStart = 0;
  size_t i = 0;
  while (i < textLength && matched < searchLength) {
    Token* textToken = &g_array_index(textTokens, Token, i);
    Token* searchToken = &g_array_index(searchTokens, Token, matched);

    if (tokenEquals(textToken, searchToken)) {
      matched++;
      i++;
    } else {
      matched = 0;
      i = ++lastStart;
    }
  }

  if (matched == searchLength) {
    *matchStart = lastStart;
    return 1;
  }
  else
    return 0;
}

inline int matchNTokens(GArray* textTokens, size_t textStart, size_t textLength,
                        GArray* searchTokens, size_t searchStart, size_t searchLength,
                        unsigned int numberOfWantedMatches){

  if (tokenEquals(
    &g_array_index(textTokens, Token, textStart),
    &g_array_index(searchTokens, Token, searchStart))) {
    unsigned matched = 1;
    size_t canMatch = MIN(numberOfWantedMatches,
                          1 + MIN(textLength - textStart, searchLength - searchStart));
    for (size_t i = 1; i < canMatch; i++) {
      if (tokenEquals(
            &g_array_index(textTokens, Token, i + textStart),
            &g_array_index(searchTokens, Token, i + searchStart)))
        matched++;
      else
        break;
    }
    return (matched == canMatch);
  }
  return 0;
}

inline DiffMatchInfo lookForDiff(GArray* textTokens, GArray* searchTokens,
        size_t iText, size_t iSearch, int maxAllowedDiff, int minTrailingMatches) {
  size_t searchLength = searchTokens->len;
  size_t textLength = textTokens->len;

  DiffMatchInfo result;
  result.diffType = NULL;

  size_t searchStopAt = MIN(iSearch + maxAllowedDiff, searchLength);
  size_t textStopAt = MIN(iText + maxAllowedDiff, textLength);

  for (unsigned int i = 0; i < SQUARE_VISITOR_LENGTH; i++) {
    result.text.start = iText + squareVisitorX[i];
    result.search.start = iSearch + squareVisitorY[i];
    if ((result.text.start > textStopAt) || (result.search.start > searchStopAt))
      break;

    if ((result.text.start < textStopAt) && (result.search.start < searchStopAt))
      if (matchNTokens(textTokens, result.text.start, textLength,
                       searchTokens, result.search.start, searchLength,
                       minTrailingMatches))
           goto diffFound;
  }

  result.diffSize = -1;
  result.search.start = iSearch;
  result.search.length = 0;
  result.text.start = iText;
  result.text.length = 0;

  return result;

diffFound:
  result.diffSize = MAX((result.search.start - iSearch), (result.text.start - iText));
  result.search.length = result.search.start - iSearch;
  result.text.length = result.text.start - iText;

  return result;
}

inline int applyDiff(const DiffMatchInfo* diff,
                     GArray* matchedInfo,
                     size_t* additionsCounter, size_t* removedCounter,
                     size_t* iText, size_t* iSearch ){

  DiffMatchInfo diffCopy = *diff;
  diffCopy.text.start = *iText;
  diffCopy.search.start = *iSearch;

  if (diffCopy.search.length == 0)
    diffCopy.diffType = DIFF_TYPE_ADDITION;
  else if (diffCopy.text.length == 0)
    diffCopy.diffType = DIFF_TYPE_REMOVAL;
  else
    diffCopy.diffType = DIFF_TYPE_REPLACE;

  *iText = diff->text.start;
  *iSearch = diff->search.start;

  g_array_append_val(matchedInfo, diffCopy);

  *additionsCounter += diffCopy.text.length;
  *removedCounter += diffCopy.search.length;

  return 1;
}

inline void initSimpleMatch(DiffMatchInfo* simpleMatch, size_t iText, size_t iSearch) {
  simpleMatch->text.start = iText;
  simpleMatch->text.length = 0;
  simpleMatch->search.start = iSearch;
  simpleMatch->search.length = 0;
}

/**
 @brief perform a diff match search between two tokenized texts

 @param textTokens   array containing the Tokens of the text in which we are searching
 @param searchTokens array containing the Tokens of the reference text to be searched
 @param textStartPosition position in the text where the search starts,
                          it will be updated to a value for a successive search
 @param maxAllowedDiff maximum number of Tokens that can be avoid
 @param minTrailingMatches minimum number of matched Tokens that

 @return pointer to the result, or NULL on negative match. To be freed with diffResult_free
 ****************************************************/
DiffResult* findMatchAsDiffs(GArray* textTokens, GArray* searchTokens,
                             size_t* textStartPosition,
                             int maxAllowedDiff, int minTrailingMatches ){
  DiffResult* result = malloc(sizeof(DiffResult));
  size_t textLength = textTokens->len;
  size_t searchLength = searchTokens->len;

  result->matchedInfo = g_array_new(TRUE, FALSE, sizeof(DiffMatchInfo));
  GArray* matchedInfo = result->matchedInfo;

  if (!searchLength || !textLength)
    return 0;

  size_t iText = *textStartPosition;
  size_t iSearch = 0;

  // match first token
  while (iText < textLength) {
      Token* textToken = &g_array_index(textTokens, Token, iText);
      Token* searchToken = &g_array_index(searchTokens, Token, iSearch);
      if (tokenEquals(textToken, searchToken))
        break;
    iText++;
  }
  *textStartPosition = iText + 1;

  size_t removedCounter = 0;
  size_t matchedCounter = 0;
  size_t additionsCounter = 0;

  if (iText < textLength) {
    DiffMatchInfo simpleMatch;
    simpleMatch.diffType = DIFF_TYPE_MATCH;
    initSimpleMatch(&simpleMatch, iText, iSearch);

    while ((iText < textLength) && (iSearch < searchLength)) {
      Token* textToken = &g_array_index(textTokens, Token, iText);
      Token* searchToken = &g_array_index(searchTokens, Token, iSearch);

      if (tokenEquals(textToken, searchToken)) {
        simpleMatch.text.length++;
        simpleMatch.search.length++;
        matchedCounter++;
        iSearch++;
        iText++;
      } else {
        if (simpleMatch.text.length > 0) {
          g_array_append_val(matchedInfo, simpleMatch);
          initSimpleMatch(&simpleMatch, iText, iSearch);
        }

        if (matchedCounter == 0) {
          iText++;
          simpleMatch.text.start++;
        } else {
          DiffMatchInfo diff = lookForDiff(textTokens, searchTokens,
                                           iText, iSearch,
                                           maxAllowedDiff,
                                           minTrailingMatches);

          if (diff.diffSize > 0) {
             applyDiff(&diff,
                       matchedInfo,
                       &additionsCounter, &removedCounter,
                       &iText, &iSearch);

             simpleMatch.text.start = iText;
             simpleMatch.search.start = iSearch;
          }
          else
            break;
        }
      }
    }
    if (simpleMatch.text.length > 0) {
      g_array_append_val(matchedInfo, simpleMatch);
    }
  }

  if (matchedCounter + removedCounter != searchLength) {
    g_array_free(matchedInfo, TRUE);
    free(result);

    return NULL;
  } else {
#ifdef DEBUG_MATCH
    for (size_t i=0; i<matchedInfo->len; i++) {
      DiffMatchInfo current = g_array_index(matchedInfo, DiffMatchInfo, i);
      printf("info[%zu]: t[%zu+%zu] %s s[%zu+%zu]}\n",
             i,
             current.text.start,
             current.text.length,
             current.diffType,
             current.search.start,
             current.search.length
      );
    }
#endif

    result->removed = removedCounter;
    result->added = additionsCounter;
    result->matched = matchedCounter;
    return result;
  }
}

void diffResult_free(DiffResult* diffResult){
  g_array_free(diffResult->matchedInfo, TRUE);
  free(diffResult);
}
