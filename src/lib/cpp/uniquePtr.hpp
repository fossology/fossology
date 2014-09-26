/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini, Cedric Bodet, Johannes Najjar
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef UNIQUE_PTR_HPP_
#define UNIQUE_PTR_HPP_

//#define USEBOOST_UNIQUEPTR
#ifdef USEBOOST_UNIQUEPTR
  #include <boost/interprocess/smart_ptr/unique_ptr.hpp>
  namespace unptr = boost::interprocess;
#else
  #include <memory>
  namespace unptr = std;
#endif

#endif /* UNIQUE_PTR_HPP_ */
