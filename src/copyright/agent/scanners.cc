/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scanners.hpp"

#include <codecvt>
#include <sstream>

/**
 * \brief Utility: read file to string from scanners.h
 * \param[in]  fileName Path of file to read
 * \param[out] out      String created from file
 * \return True on success, fail otherwise
 * \todo There should be a maximum string size
 */
bool ReadFileToString(const string& fileName, icu::UnicodeString& out)
{
  wifstream stream(fileName);
  stream.imbue(std::locale(stream.getloc(), new std::codecvt_utf8_utf16<wchar_t>));
  std::wstringstream sstr;
  sstr << stream.rdbuf();
  out = icu::UnicodeString::fromUTF32(
    reinterpret_cast<const UChar32*>(sstr.str().c_str()),
    sstr.str().length());
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

