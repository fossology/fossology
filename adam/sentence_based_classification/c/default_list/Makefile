######################################################################
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
# 
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
######################################################################

CC=gcc
CFLAGS=-g -O2 -Wall

files=\
	default_list.c \
	default_list_basic.c

all: default_list.a test

files_o := $(patsubst %.c,%.o,$(files))

default_list.o: default_list.c default_list.h
default_list_basic.o: default_list_basic.c default_list_basic.h

default_list.a: $(files_o)
	$(AR) -cru $@ $^

test: test.o default_list.a
	$(CC) -o $@ $^ $(CFLAGS)

clean:
	rm -f test *.o *.a

phony: all clean

# vim: ts=8 sts=8 noet
