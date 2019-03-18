/****************************************************************
 sha1.h - Secure Hashing Algorithm 1
 Copyright (C) The Internet Society (2001).  All Rights Reserved.
 Modifications Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

 Source: RFC3174

 Tue Mar 14 12:59:09 MST 2006
 Original code from RFC3174 Modified by Dr. Neal Krawetz for HP
 Optimized SHA1Input() for speed.
 ****************************************************************/

/**
 * \file sha1.h
 *
 *  Description:
 *      This is the header file for code which implements the Secure
 *      Hashing Algorithm 1 as defined in FIPS PUB 180-1 published
 *      April 17, 1995.
 *
 *      Many of the variable names in this code, especially the
 *      single character names, were used because those were the names
 *      used in the publication.
 *
 *      Please read the file \link sha1.c \endlink for more information.
 *
 */

#ifndef _SHA1_H_
#define _SHA1_H_

#include <stdint.h>
/**
 * If you do not have the ISO standard stdint.h header file, then you
 * must typdef the following:
 *
 * |   Name        |    Meaning |
 * | ---: | :--- |
 * | uint32_t      | unsigned 32 bit integer |
 * | uint8_t       | unsigned 8 bit integer (i.e., unsigned char) |
 * | int_least16_t | integer of >= 16 bits |
 *
 */

#ifndef _SHA_enum_
#define _SHA_enum_
enum
{
    shaSuccess = 0,     /** SHA return code */
    shaNull,            /** Null pointer parameter */
    shaInputTooLong,    /** Input data too long */
    shaStateError       /** Called Input after Result */
};
#endif
#define SHA1HashSize 20

/**
 *  This structure will hold context information for the SHA-1
 *  hashing operation
 */
typedef struct SHA1Context
{
    uint32_t Intermediate_Hash[SHA1HashSize/4]; /** Message Digest */

    uint32_t Length_Low;            /** Message length in bits      */
    uint32_t Length_High;           /** Message length in bits      */

    int_least16_t Message_Block_Index;  /** Index into message block array */
    uint8_t *Message_Block;  /** 512-bit message blocks -- NAK: [64] Allocated during init */
    uint8_t Static_Message_Block[64]; /** used for carrying data between calls */

    int Computed;              /** Is the digest computed?         */
    int Corrupted;             /** Is the message digest corrupted? */
} SHA1Context;

/*
 *  Function Prototypes
 */

int SHA1Reset(  SHA1Context *);
int SHA1Input(  SHA1Context *,
                uint8_t *,
                unsigned int);
int SHA1Result( SHA1Context *,
                uint8_t Message_Digest[SHA1HashSize]);

#endif

