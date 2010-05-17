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
CFLAGS=-g -O2 -Wall -I.

files= cvector.c

all: cvector.a

files_o := $(patsubst %.c,%.o,$(files))

cvector.o: cvector.c cvector.h

cvector.a: $(files_o)
	$(AR) -cru $@ $^

clean:
	rm -f test *.o *.a

phony: all clean

# vim: ts=8 sts=8 noet
