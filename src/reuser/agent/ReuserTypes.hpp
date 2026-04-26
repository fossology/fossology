/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
#pragma once

#include <string>

/**
 * @brief Bounds of an item within an uploadtree table.
 */
struct ItemTreeBounds
{
  int         uploadtree_pk;
  std::string uploadTreeTableName;
  int         upload_fk;
  int         lft;
  int         rgt;
};

/**
 * @brief A single reuse relationship between two uploads.
 */
struct ReuseTriple
{
  int reusedUploadId;
  int reusedGroupId;
  int reuseMode;
};

/** Reuse mode bit flags (mirror of PHP ReuseTypes constants). */
constexpr int REUSE_ENHANCED  =   2; ///< Match by filename and diff
constexpr int REUSE_MAIN      =   4; ///< Copy main license
constexpr int REUSE_CONF      =  16; ///< Copy report configuration
constexpr int REUSE_COPYRIGHT = 128; ///< Copy copyright events
