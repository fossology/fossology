/*
 * Copyright (C) 2014, 2018, Siemens AG
 * Author: Daniele Fognini, anupam.ghosh@siemens.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */


#ifndef IDENTITY_HPP
#define IDENTITY_HPP

#ifndef IDENTITY_KW
#ifndef IDENTITY_ECC
#ifndef IDENTITY_COPYRIGHT
#define IDENTITY_COPYRIGHT
#endif
#endif
#endif

#ifndef IDENTITY_KW
  #ifndef IDENTITY_ECC
     #ifdef IDENTITY_COPYRIGHT
       #define IDENTITY "copyright"
       #define MAX_TYPES 4
     #else
       #error
     #endif
  #else
    #ifndef IDENTITY_COPYRIGHT
      #define IDENTITY "ecc"
      #define MAX_TYPES 1
    #else
      #error
    #endif
  #endif
#else
 #ifndef IDENTITY_ECC
    #ifndef IDENTITY_COPYRIGHT
      #define IDENTITY "keyword"
      #define MAX_TYPES 1
    #else
      #error
    #endif
  #else
    #error
  #endif
#endif

#define ALL_TYPES ((1<<MAX_TYPES) -1)

#endif // IDENTITY_HPP
