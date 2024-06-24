/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
#include <sstream>
#include <unicode/schriter.h>
#include <unicode/brkiter.h>

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
  int const len = input.length();
  const auto *in =
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

/**
 * Remove all non-UTF8 characters from a string.
 * @param input The string to be recoded
 * @return Unicode string with invalid characters removed
 */
icu::UnicodeString fo::recodeToUnicode(const icu::UnicodeString &input)
{
  auto iter = icu::StringCharacterIterator(input);

  icu::UnicodeString out;
  while (iter.hasNext())
  {
    UChar32 uniChar = iter.next32PostInc();
    if (uniChar > 0)
    {
      out.append(uniChar);
    }
    else
    {
      uniChar = iter.next32PostInc();
      if (U_IS_UNICODE_CHAR(uniChar) && uniChar > 0)
      {
        out.append(uniChar);
      }
    }
  }
  out.trim();
  return out;
}
