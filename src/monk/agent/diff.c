/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "diff.h"

#include "_squareVisitor.h"
#include <stdlib.h>

int matchNTokens(const GArray* textTokens, size_t textStart, size_t textLength,
                 const GArray* searchTokens, size_t searchStart, size_t searchLength,
                 unsigned int numberOfWantedMatches) {

  if (
    textStart < textLength &&
    searchStart < searchLength &&
    tokenEquals(
      tokens_index(textTokens, textStart),
      tokens_index(searchTokens, searchStart
    )))
  {
    unsigned matched = 1;

    size_t canMatch = MIN(textLength - textStart, searchLength - searchStart);
    size_t shouldMatch = MIN(numberOfWantedMatches, canMatch);
    for (size_t i = 1; i < shouldMatch; i++) {
      if (tokenEquals(
            tokens_index(textTokens, i + textStart),
            tokens_index(searchTokens, i + searchStart)))
        matched++;
      else
        break;
    }
    return (matched == shouldMatch);
  }
  return 0;
}

int lookForDiff(const GArray* textTokens, const GArray* searchTokens,
                       size_t iText, size_t iSearch,
                       unsigned int maxAllowedDiff, unsigned int minAdjacentMatches,
                       DiffMatchInfo* result) {
  size_t searchLength = searchTokens->len;
  size_t textLength = textTokens->len;

  size_t searchStopAt = MIN(iSearch + maxAllowedDiff, searchLength);
  size_t textStopAt = MIN(iText + maxAllowedDiff, textLength);

  size_t textPos;
  size_t searchPos;
  for (unsigned int i = 0; i < SQUARE_VISITOR_LENGTH; i++) {
    textPos = iText + squareVisitorX[i];
    searchPos = iSearch + squareVisitorY[i];

    if ((textPos < textStopAt) && (searchPos < searchStopAt))
      if (matchNTokens(textTokens, textPos, textLength,
                       searchTokens, searchPos, searchLength,
                       minAdjacentMatches))
      {
        result->search.start = searchPos;
        result->search.length = searchPos - iSearch;
        result->text.start = textPos;
        result->text.length = textPos - iText;
        return 1;
      }
  }

  return 0;
}

static int applyDiff(const DiffMatchInfo* diff,
              GArray* matchedInfo,
              size_t* additionsCounter, size_t* removedCounter,
              size_t* iText, size_t* iSearch) {

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

static void applyTailDiff(GArray* matchedInfo,
        size_t searchLength,
        size_t* removedCounter, size_t* additionsCounter,
        size_t* iText, size_t* iSearch) {

  DiffMatchInfo tailDiff = (DiffMatchInfo) {
          .search = (DiffPoint) {
                  .start = (*iSearch),
                  .length = searchLength - (*iSearch)
          },
          .text = (DiffPoint) {
                  .start = (*iText),
                  .length = 0
          },
          .diffType = NULL
  };

  applyDiff(&tailDiff, matchedInfo, additionsCounter, removedCounter, iText, iSearch);
}

static void initSimpleMatch(DiffMatchInfo* simpleMatch, size_t iText, size_t iSearch) {
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
 @param maxAllowedDiff maximum number of Tokens that can be avoided
 @param minAdjacentMatches minimum number of adjacent matched Tokens that must be equal

 @return pointer to the result, or NULL on negative match. To be freed with diffResult_free
 ****************************************************/
DiffResult* findMatchAsDiffs(const GArray* textTokens, const GArray* searchTokens,
                             size_t textStartPosition, size_t searchStartPosition,
                             unsigned int maxAllowedDiff, unsigned int minAdjacentMatches) {
  size_t textLength = textTokens->len;
  size_t searchLength = searchTokens->len;

  if ((searchLength<=searchStartPosition) || (textLength<=textStartPosition)) {
    return NULL;
  }

  DiffResult* result = malloc(sizeof(DiffResult));
  result->matchedInfo = g_array_new(FALSE, FALSE, sizeof(DiffMatchInfo));
  GArray* matchedInfo = result->matchedInfo;

  size_t iText = textStartPosition;
  size_t iSearch = 0;

  size_t removedCounter = 0;
  size_t matchedCounter = 0;
  size_t additionsCounter = 0;

  if (searchStartPosition > 0) {
    DiffMatchInfo licenseHeadDiff = (DiffMatchInfo) {
      .search = (DiffPoint) {
        .start = 0,
        .length = searchStartPosition
      },
      .text = (DiffPoint) {
        .start = iText,
        .length = 0
      },
      .diffType = NULL
    };
    applyDiff(&licenseHeadDiff, matchedInfo, &additionsCounter, &removedCounter, &iText, &iSearch);
    iSearch = searchStartPosition;
  }

  // match first token
  while (iText < textLength) {
      Token* textToken = tokens_index(textTokens, iText);
      Token* searchToken = tokens_index(searchTokens, iSearch);
      if (tokenEquals(textToken, searchToken))
        break;
    iText++;
  }

  if (iText < textLength) {
    DiffMatchInfo simpleMatch;
    simpleMatch.diffType = DIFF_TYPE_MATCH;
    initSimpleMatch(&simpleMatch, iText, iSearch);

    while ((iText < textLength) && (iSearch < searchLength)) {
      Token* textToken = tokens_index(textTokens, iText);
      Token* searchToken = tokens_index(searchTokens, iSearch);

      if (tokenEquals(textToken, searchToken)) {
        simpleMatch.text.length++;
        simpleMatch.search.length++;
        matchedCounter++;
        iSearch++;
        iText++;
      } else {
        /* the previous tokens matched, here starts a difference */
        g_array_append_val(matchedInfo, simpleMatch);
        initSimpleMatch(&simpleMatch, iText, iSearch);

        DiffMatchInfo diff;
        if (lookForDiff(textTokens, searchTokens,
                        iText, iSearch,
                        maxAllowedDiff, minAdjacentMatches,
                        &diff)) {
          applyDiff(&diff,
                    matchedInfo,
                    &additionsCounter, &removedCounter,
                    &iText, &iSearch);

          simpleMatch.text.start = iText;
          simpleMatch.search.start = iSearch;
        } else {
          break;
        }
      }
    }
    if (simpleMatch.text.length > 0) {
      g_array_append_val(matchedInfo, simpleMatch);
    }
  }

  if ((iSearch < searchLength) && (searchLength < maxAllowedDiff + iSearch)) {
    applyTailDiff(matchedInfo, searchLength, &removedCounter, &additionsCounter, &iText, &iSearch);
  }

  if (matchedCounter + removedCounter != searchLength) {
    g_array_free(matchedInfo, TRUE);
    free(result);

    return NULL;
  } else {
#ifdef DEBUG_DIFF
    printf("diff: (=%zu +%zu -%zu)/%zu\n", matchedCounter, additionsCounter, removedCounter, searchLength);
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
    printf("\n");
#endif
    result->removed = removedCounter;
    result->added = additionsCounter;
    result->matched = matchedCounter;
    return result;
  }
}

void diffResult_free(DiffResult* diffResult) {
  g_array_free(diffResult->matchedInfo, TRUE);
  free(diffResult);
}
