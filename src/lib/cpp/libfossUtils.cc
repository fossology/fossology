#include <sstream>

#include "libfossUtils.hpp"

unsigned long fo::stringToUnsignedLong(const char* string)
{
  unsigned long uLongVariable;
  std::stringstream(string) >> uLongVariable;
  return uLongVariable;
}