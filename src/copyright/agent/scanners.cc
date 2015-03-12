/*
 * Copyright (C) 2015, Siemens AG
 * Author: Florian Krügel
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "scanners.hpp"

#include <sstream>
#include <cstring>

// Utility: read file to string from scanners.h

void ReadFileToString(const string& fileName, string& out)
{
  // TODO: there should be a maximum string size
  ifstream stream(fileName);
  std::stringstream sstr;
  sstr << stream.rdbuf();
  out = sstr.str();
}


bool operator==(const match& m1, const match& m2)
{
  return m1.start == m2.start && m1.end == m2.end && strcmp(m1.type, m2.type) == 0;
}
bool operator!=(const match& m1, const match& m2)
{
  return !(m1 == m2);
}

