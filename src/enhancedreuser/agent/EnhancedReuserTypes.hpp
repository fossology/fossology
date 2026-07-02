/*
 SPDX-License-Identifier: GPL-2.0-only
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
 */

#pragma once

#include <string>
#include <vector>

#include "ClearingDecisionUtils.hpp"

/**
 * @brief A single comment block extracted by nirjas.
 */
struct CommentBlock
{
  int         startLine;
  int         endLine;
  std::string text;
};

/**
 * @brief Full output from nirjas comment extraction.
 */
struct NirjasOutput
{
  std::string            filename;
  std::string            lang;
  int                    totalLines;
  int                    totalLinesOfComments;
  int                    blankLines;
  int                    sloc;
  std::vector<CommentBlock> singleLineComments;
  std::vector<CommentBlock> contSingleLineComments;
  std::vector<CommentBlock> multiLineComments;
};
