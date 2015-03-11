/*
 * Regex scanner
 */

#ifndef REGSCAN_HPP_
#define REGSCAN_HPP_

#include "scanners.hpp"
#include "regex.hpp"

class regexScanner : public scanner
{
  rx::regex reg;
  const char* type;
public:
  void ScanString(const string& str, list<match>& results) const override;
  regexScanner(const string& sReg, const char* t);
} ;


#endif

