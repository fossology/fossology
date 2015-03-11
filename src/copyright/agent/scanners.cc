
#include "scanners.hpp"

#include <sstream>

// Utility: read file to string from scanners.h

void ReadFileToString(const string& fileName, string& out)
{
  // TODO: there should be a maximum string size
  ifstream stream(fileName);
  std::stringstream sstr;
  sstr << stream.rdbuf();
  out = sstr.str();
}

