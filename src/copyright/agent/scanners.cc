/*
 * Copyright (C) 2015, Siemens AG
 * Author: Florian Krügel
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "scanners.hpp"

#include <sstream>
#include <cstring>

/**
 * \brief Utility: read file to string from scanners.h
 * \param[in]  fileName Path of file to read
 * \param[out] out      String created from file
 * \return True on success, fail otherwise
 * \todo There should be a maximum string size
 */

bool ReadFileToString(const string& fileName, string& out)
{
  ifstream stream(fileName);
  std::stringstream sstr;
  sstr << stream.rdbuf();
  out = sstr.str();
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

