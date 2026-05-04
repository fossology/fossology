/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef REGSCAN_HPP_
#define REGSCAN_HPP_

#include "scanners.hpp"
#include "regex.hpp"
#include "regexConfProvider.hpp"
#include <sstream>

extern "C" {
#include "libfossology.h"
}

/**
 * \class regexScanner
 * \brief Provides a regex scanner using predefined regexs
 *
 * Uses boost::regex on UTF-8 strings for performance; the ecc/keyword/ipra
 * patterns only match ASCII content.  Byte offsets are converted to UChar16
 * offsets at the end so positions are consistent with the ICU-based storage.
 */
class regexScanner : public scanner
{
  /**
   * \var rx::regex _reg
   * Regex to be used during scan (operates on UTF-8 bytes)
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
  void ScanString(const icu::UnicodeString& s, list<match>& results) const override;

  regexScanner(const string& type,
               const string& identity,
               int index = 0);
  regexScanner(const string& type,
               std::istringstream& stream,
               int index = 0);
} ;


#endif

