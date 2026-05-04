/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scanners.hpp"

#include <fstream>
#include <sstream>

/**
 * \brief Utility: read file to UnicodeString from scanners.h
 * \param[in]  fileName Path of file to read
 * \param[out] out      UnicodeString created from file
 * \return True on success, fail otherwise
 * \todo There should be a maximum string size
 */
bool ReadFileToString(const string& fileName, icu::UnicodeString& out)
{
  std::ifstream stream(fileName, std::ios::binary);
  if (!stream)
    return false;
  std::ostringstream sstr;
  sstr << stream.rdbuf();
  std::string content = sstr.str();
  out = icu::UnicodeString::fromUTF8(content);
  return !stream.fail();
}

/**
 * \brief Compare two regex match
 * \return True if they are equal, false otherwise
 */
bool operator==(const match& m1, const match& m2)
{
  return m1.start == m2.start && m1.end == m2.end && m1.type == m2.type;
}

/**
 * \brief Compare two regex match
 * \return True if they are not equal, false otherwise
 * \see operator==(const match& m1, const match& m2)
 */
bool operator!=(const match& m1, const match& m2)
{
  return !(m1 == m2);
}

