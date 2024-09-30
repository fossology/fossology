/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
#include <sstream>

#include "libfossUtils.hpp"

/**
 * \file
 * \brief General utility functions for CPP
 */

/**
 * Translates a string to unsigned long type.
 * \param string String to be translated
 * \return Translated unsigned long value
 */
unsigned long fo::stringToUnsignedLong(const char* string)
{
  unsigned long uLongVariable;
  std::stringstream(string) >> uLongVariable;
  return uLongVariable;
}

/**
 * Translates a string to boolean type.
 * \param str String to be translated
 * \return Translated boolean value
 */
bool fo::stringToBool(const char* str)
{
  std::string str2(str);
  if(str2 == "true" || str2 == "t")
    return true;
  else
    return false;
}

/**
 * Remove all non-UTF8 characters from a string.
 * @param input The string to be recoded
 * @return Unicode string with invalid characters removed
 */
icu::UnicodeString fo::recodeToUnicode(const std::string &input)
{
  int len = input.length();
  const unsigned char *in =
    reinterpret_cast<const unsigned char*>(input.c_str());

  icu::UnicodeString out;
  for (int i = 0; i < len;)
  {
    UChar32 uniChar;
    int lastPos = i;
    U8_NEXT(in, i, len, uniChar);
    if (uniChar > 0)
    {
      out.append(uniChar);
    }
    else
    {
      i = lastPos;
      U16_NEXT(in, i, len, uniChar);
      if (U_IS_UNICODE_CHAR(uniChar) && uniChar > 0)
      {
        out.append(uniChar);
      }
    }
  }
  out.trim();
  return out;
}
