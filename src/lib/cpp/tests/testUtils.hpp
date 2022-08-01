/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
