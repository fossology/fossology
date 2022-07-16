# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LIB = libfodbreposysconf.a
OBJS = libfodbreposysconf.o

all: $(LIB) $(VARS)
	@echo "make $(LIB)"

$(LIB): $(OBJS) $(VARS)
	$(AR) cr $@ $(OBJS)


$(OBJS): %.o: %.c %.h
	$(CC) -D'TESTDBDIR="$(shell pwd)/../"' $(FO_CFLAGS) -c $<

clean:
	@echo "make $(LIB) clean"
	rm -f $(LIB) *.o

.PHONY: all clean
