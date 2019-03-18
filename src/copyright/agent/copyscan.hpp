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

#ifndef COPYSCAN_HPP_
#define COPYSCAN_HPP_

#include "scanners.hpp"
#include "regex.hpp"

/**
 * \class hCopyrightScanner
 * \brief Implementation of scanner class for copyright
 */
class hCopyrightScanner : public scanner
{
public:
  void ScanString(const string& s, list<match>& results) const;
  hCopyrightScanner();
private:
  /**
   * \var rx::regex regCopyright
   * Regex for copyright statments
   * \var rx::regex regException
   * Regex for exceptions in copyright
   * \var rx::regex regNonBlank
   * Regex to find non blank statements
   * \var rx::regex regSimpleCopyright
   * Simple regex for copyright
   */
  rx::regex regCopyright, regException, regNonBlank, regSimpleCopyright;
} ;

#endif

