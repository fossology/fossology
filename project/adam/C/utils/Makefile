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
CFLAGS=-g -I../default_list -lpcre -lstemmer -O3 -I../../sparsevect/
CPP=g++
CPPFLAGS=-g -I../default_list -lpcre -lstemmer -lmaxent -O3 -Wno-deprecated -I../../sparsevect/

all: file_utils.o maxent_utils.o re.o repr.o sentence.o token.o token_feature.o tokenizer.o

%.o: %.c
	$(CC) -c -o $@ $^ $(CFLAGS)

%.o: %.cpp
	$(CPP) -c -o $@ $^ $(CPPFLAGS)

clean:
	rm -f *.o

phony: all clean

# vim: noet
