/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
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

