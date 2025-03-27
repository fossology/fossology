/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
#ifndef LIBFOSS_UTILS_HPP_
#define LIBFOSS_UTILS_HPP_

#include <unicode/unistr.h>

/**
 * \file
 * \brief General utility functions for CPP
 */

namespace fo
{
  unsigned long stringToUnsignedLong(const char* string);
  bool stringToBool(const char* string);
  icu::UnicodeString recodeToUnicode(const std::string &input);
  icu::UnicodeString recodeToUnicode(const icu::UnicodeString &input);
}

#endif
