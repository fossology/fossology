/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */


#ifndef IDENTITY_H_INCLUDE
#define IDENTITY_H_INCLUDE

#ifndef IDENTITY_ECC
#ifndef IDENTITY_IP
#ifndef IDENTITY_COPYRIGHT
#define IDENTITY_COPYRIGHT
#endif
#endif
#endif

#ifndef IDENTITY_ECC
 #ifndef IDENTITY_IP
  #ifdef IDENTITY_COPYRIGHT
   #define IDENTITY "copyright"
  #else
   #error
  #endif
 #else
  #ifndef IDENTITY_COPYRIGHT
   #define IDENTITY "ip"
  #else
   #error
  #endif
 #endif
#else
 #ifndef IDENTITY_IP
  #ifndef IDENTITY_COPYRIGHT
   #define IDENTITY "ecc"
  #else
   #error
  #endif
 #else
  #error
 #endif
#endif

#endif /* IDENTITY_H_INCLUDE */
