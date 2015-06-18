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

struct match {
  // A pair of start/end positions and types
  const int start, end;
  const string& type;
  match(const int s, const int e, const string& t) : start(s), end(e), type(t) { }
} ;

bool operator==(const match& m1, const match& m2);
bool operator!=(const match& m1, const match& m2);

class scanner
{
public:
  virtual ~scanner() {};

  // s: string to scan
  // results: copyright matches are appended to this list
  virtual void ScanString(const string& s, list<match>& results) const = 0;

  // fileName: file name to scan
  // results: copyright matches are appended to this list
  virtual void ScanFile(const string& fileName, list<match>& results) const
  {
    string s;
    ReadFileToString(fileName, s);
    ScanString(s, results);
  }
} ;

#endif

