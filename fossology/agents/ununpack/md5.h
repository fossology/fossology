/*
 * This is an OpenSSL-compatible implementation of the RSA Data Security,
 * Inc. MD5 Message-Digest Algorithm.
 *
 * Written by Solar Designer <solar at openwall.com> in 2001, and placed
 * in the public domain.  See md5.c for more information.
 */

#ifndef _MD5_H
#define _MD5_H

/* Any 32-bit or wider unsigned integer data type will do */
typedef unsigned long MD5_u32plus;

typedef struct {
	MD5_u32plus lo, hi;
	MD5_u32plus a, b, c, d;
	unsigned char buffer[64];
	MD5_u32plus block[16];
} MyMD5_CTX;

extern void MyMD5_Init(MyMD5_CTX *ctx);
extern void MyMD5_Update(MyMD5_CTX *ctx, void *data, unsigned long size);
extern void MyMD5_Final(unsigned char *result, MyMD5_CTX *ctx);

#endif
