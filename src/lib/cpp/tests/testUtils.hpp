/*
 * Copyright (C) 2015, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2
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

#ifndef FOSSOLOGY_TESTUTILS_HPP
#define FOSSOLOGY_TESTUTILS_HPP

#include <iostream>
#include <vector>

/**
 * \file
 * \brief Utility functions for test
 */

/**
 * \brief `<<` operator overload to appends a vector to an ostream object
 *
 * Operator appends the integers in a vector x to the ostream object in a JSON
 * style format.
 * \param os Stream object to be appended with vector
 * \param x  The vector to be appended
 * \return ostream with vector appended
 */
std::ostream& operator <<(std::ostream& os, const std::vector<int>& x)
{
  typedef std::vector<int>::const_iterator cpm;
  os << "[";
  bool first = true;
  for (cpm it = x.begin(); it != x.end(); ++it) {
    if (!first) {
      os << ", ";
    }
    first = false;
    os << *it;
  }
  return os << "]";
}

#endif //FOSSOLOGY_TESTUTILS_HPP
