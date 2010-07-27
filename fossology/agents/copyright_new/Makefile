######################################################################
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
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

# directory specific information
INCL = -I.

# C compiler options
CC = gcc
CFLAGS = -g -O3 -Wall $(INCL)
EXEC = copyright

CPP = g++

SOURCE = cvector.c \
		 radixtree.c \
		 copyright.c \
		 pair.c \

HEADER = cvector.h \
		 radixtree.h \
		 copyright.h \
		 pair.h \

OBJS   = cvector.o \
		 radixtree.o \
		 copyright.o \
		 pair.o \
		 
all: $(EXEC)

$(EXEC): $(OBJS) copyright.a main.c
	$(CC) main.c $(CFLAGS) -o $(EXEC) copyright.a

copyright.o: copyright.c $(HEADER)

radixtree.o: radixtree.c radixtree.h

cvector.o:   cvector.c   cvector.h

copyright.a: $(OBJS) $(SOURCE) $(HEADER)
	$(AR) -cru $@ $^

clean:
	rm -f *.o *.a
	rm -f $(EXEC)
	rm -f False_Positives False_Negatives Matches Found

phony: all clean
	
