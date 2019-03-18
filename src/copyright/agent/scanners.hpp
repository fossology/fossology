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
/**
 * \file scanners.hpp
 * \brief Utilities to help scanners
 */
#ifndef SCANNERS_HPP_
#define SCANNERS_HPP_

#include <fstream>
using std::ifstream;
using std::istream;
#include <string>
using std::string;
#include <list>
using std::list;

bool ReadFileToString(const string& fileName, string& out);

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
  match(const int s, const int e, const string& t) : start(s), end(e), type(t) { }
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
  virtual void ScanString(const string& s, list<match>& results) const = 0;

  /**
   * \brief Helper function to scan file
   *
   * Reads file contents to string and pass to ScanString()
   * \param[in]  fileName File name to scan
   * \param[out] results  Copyright matches are appended to this list
   */
  virtual void ScanFile(const string& fileName, list<match>& results) const
  {
    string s;
    ReadFileToString(fileName, s);
    ScanString(s, results);
  }
} ;

#endif

