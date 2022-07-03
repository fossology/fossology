# FOSSology Makefile - ./src/testing/lib/c
# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LIB = libfocunit.a
OBJS = libfocunit.o

all: $(LIB) $(VARS)
	@echo "make $(LIB)"

$(LIB): $(OBJS) $(VARS)
	$(AR) cr $@ $(OBJS)

$(OBJS): %.o: %.c %.h
	$(CC) -c -fPIC $(FO_CFLAGS) $<

clean:
	@echo "make $(LIB) clean"
	rm -f $(LIB) *.o

.PHONY: all clean
