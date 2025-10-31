/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_HIGHLIGHT_H
#define KOTOBA_AGENT_HIGHLIGHT_H

#include <glib.h>
#include "diff.h"

void convertToAbsolutePositions(GArray* diffMatchInfo,
                                GArray* textTokens,
                                GArray* searchTokens);

DiffPoint getFullHighlightFor(const GArray* tokens, size_t firstMatchedIndex, size_t matchedCount);

#endif // KOTOBA_AGENT_HIGHLIGHT_H
