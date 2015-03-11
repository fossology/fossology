/*
 * regscan.cc
 * Implements the regexScanner class
 *
 */

#include "regscan.hpp"



regexScanner::regexScanner(const string& sReg, const char* t)
  : reg(rx::regex(sReg)), type(t)
{ }

void regexScanner::ScanString(const string& s, list<match>& results) const
{
  // Read file into one string
  string::const_iterator end = s.end();
  string::const_iterator pos = s.begin();
  unsigned int intPos = 0;
  
  while (pos != end)
  {
    // Find next match
    rx::smatch res;
    if (rx::regex_search(pos, end, res, reg))
    {
      // Found match
      results.push_back(match(intPos + res.position(), intPos + res.position() + res.length(), type));
      pos = res[0].second;
      intPos += res.position() + res.length();
    }
    else
      // No match found
      break;
  }
}

