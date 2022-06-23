/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Daniele Fognini, Cedric Bodet, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef UNIQUE_PTR_HPP_
#define UNIQUE_PTR_HPP_

/**
 * \file
 * \brief Defined which unique to be used by creating new `unptr` namespace
 *
 * If a macro `USEBOOST_UNIQUEPTR` is defined, then use boost::interprocess
 * unique pointer, else `std` unique pointer under `unptr` namespace.
 */

//#define USEBOOST_UNIQUEPTR
#ifdef USEBOOST_UNIQUEPTR
  #include <boost/interprocess/smart_ptr/unique_ptr.hpp>
  namespace unptr = boost::interprocess;
#else

#include <memory>

namespace unptr = std;
#endif

#endif /* UNIQUE_PTR_HPP_ */
