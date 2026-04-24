/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Florian Krügel

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file scanners.hpp
 * \brief Utilities to help scanners
 */
#ifndef SCANNERS_HPP_
#define SCANNERS_HPP_

#include <fstream>
#include <list>
#include <string>
#include <unicode/unistr.h>
using std::wifstream;
using std::istream;
using std::string;
using std::list;

bool ReadFileToString(const string& fileName, icu::UnicodeString& out);

/**
 * \struct match
 * \brief Store the results of a regex match
 */
struct match {
  /**
   * \var int start
   * Start position of match
   * \var int end
   * End position of match
   */
  const int start, end;
  /**
   * \var
   * Type of the match
   */
  const string& type;
  /**
   * \var bool is_enabled
   * True if the match is an active copyright finding; false if it was
   * removed by a cleanup/exception rule and should be stored as deactivated.
   */
  bool is_enabled;
  match(const int s, const int e, const string& t, bool enabled = true)
    : start(s), end(e), type(t), is_enabled(enabled) { }
} ;

bool operator==(const match& m1, const match& m2);
bool operator!=(const match& m1, const match& m2);

/**
 * \class scanner
 * \brief Abstract class to provide interface to scanners
 */
class scanner
{
public:
  virtual ~scanner() {};

  /**
   * \brief Scan the given string and add matches to results
   * \param[in]  s       String to scan
   * \param[out] results Copyright matches are appended to this list
   */
  virtual void ScanString(const icu::UnicodeString& s, list<match>& results) const = 0;

  /**
   * \brief Helper function to scan file
   *
   * Reads file contents to string and pass to ScanString()
   * \param[in]  fileName File name to scan
   * \param[out] results  Copyright matches are appended to this list
   */
  virtual void ScanFile(const string& fileName, list<match>& results) const
  {
    icu::UnicodeString s;
    ReadFileToString(fileName, s);
    ScanString(s, results);
  }
} ;

#endif

