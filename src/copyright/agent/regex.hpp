/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Daniele Fognini, Cedric Bodet, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef REGEX_HPP_
#define REGEX_HPP_

#define USEBOOST
#ifdef USEBOOST

#include <boost/regex.hpp>
#include <boost/regex/icu.hpp>

/**
 * \namespace rx
 * \brief Represent boost namespace
 *
 * Namespace boost is included as rx
 */
namespace rx = boost;
#else
  #include <regex>
  namespace rx = std;
#endif

#endif /* REGEX_HPP_ */
