/*
 Copyright (C) 2014-2015, Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License version 2
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software Foundation,
 Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
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
