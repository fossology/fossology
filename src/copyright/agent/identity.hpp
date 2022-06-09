/*
 SPDX-FileCopyrightText: © 2014, 2018 Siemens AG
 Author: Daniele Fognini, anupam.ghosh@siemens.com

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \def IDENTITY_ECC
 * \brief If the current agent being compiled is ECC
 */
/**
 * \def IDENTITY_COPYRIGHT
 * \brief If the current agent being compiled is Copyright
 */
/**
 * \def IDENTITY
 * \brief Name of the agent being compiled. Used in database statements
 */
/**
 * \def MAX_TYPES
 * \brief Maximum types of statements that can be identified by the
 * current agent
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
