/*
 SPDX-FileCopyrightText: © 2014-2015,2022, Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef CLEANENTRIES_HPP_
#define CLEANENTRIES_HPP_

#include <string>
#include <unicode/unistr.h>
#include "regex.hpp"
#include "scanners.hpp"

#include "libfossUtils.hpp"

icu::UnicodeString cleanMatch(const icu::UnicodeString& sText, const match& m);


#endif /* CLEANENTRIES_HPP_ */
