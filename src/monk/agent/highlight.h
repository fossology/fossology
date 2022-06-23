/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_HIGHLIGHT_H
#define MONK_AGENT_HIGHLIGHT_H

#include <glib.h>
#include "diff.h"

void convertToAbsolutePositions(GArray* diffMatchInfo,
                                GArray* textTokens,
                                GArray* searchTokens);

DiffPoint getFullHighlightFor(const GArray* tokens, size_t firstMatchedIndex, size_t matchedCount);

#endif // MONK_AGENT_HIGHLIGHT_H
