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
