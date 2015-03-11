/*
 * scanners.hpp
 *
 * Declares the scanner class, which is an abstract base class for scanners, and the match class
 *
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

void ReadFileToString(const string& fileName, string& out);

struct match {
  // A pair of start/end positions and types
  int start, end;
  const char* type;
  match(int s, int e, const char* t) : start(s), end(e), type(t) { }
} ;

class scanner
{
public:
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

