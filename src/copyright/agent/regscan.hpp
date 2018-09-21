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

#ifndef REGSCAN_HPP_
#define REGSCAN_HPP_

#include "scanners.hpp"
#include "regex.hpp"
#include "regexConfProvider.hpp"
#include <sstream>

/**
 * \class regexScanner
 * \brief Provides a regex scanner using predefined regexs
 */
class regexScanner : public scanner
{
  /**
   * \var rx::regex _reg
   * Regex to be used during scan
   */
  rx::regex _reg;
  /**
   * \var string _type
   * Type of regex to use
   * \var string _identity
   * Identity of regex
   */
  const string _type, _identity;
  /**
   * \var int _index
   * Index of regex
   */
  int _index;

public:
  void ScanString(const string& str, list<match>& results) const;

  regexScanner(const string& type,
               const string& identity,
               int index = 0);
  regexScanner(const string& type,
               std::istringstream& stream,
               int index = 0);
} ;


#endif

