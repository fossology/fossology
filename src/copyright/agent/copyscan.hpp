/*
 * Heuristic copyright scanner
 */
 
#ifndef COPYSCAN_HPP_
#define COPYSCAN_HPP_

#include "scanners.hpp"

class hCopyrightScanner : public scanner
{
public:
  void ScanString(const string& s, list<match>& results) const override;
} ;

#endif

