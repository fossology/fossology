/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

#include <string>
#include <vector>
#include "EnhancedReuserTypes.hpp"

/**
 * @brief Extract comments from a source file by invoking the nirjas CLI tool.
 *
 * Runs `nirjas <filepath>` via popen(), reads the JSON output and parses
 * it into a NirjasOutput struct.
 *
 * Nirjas determines the language from the file extension, so the repository
 * path (hash-based, no meaningful extension) must be supplemented with the
 * original filename to create a temporary symlink with the correct extension.
 *
 * @param filePath         Absolute path to the file in the repository.
 * @param originalFilename Original filename (used for extension detection).
 * @return Parsed comment data.  On error the lang field is set to "error".
 */
NirjasOutput extractComments(const std::string& filePath,
                             const std::string& originalFilename = "");
