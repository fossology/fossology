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
CFLAGS=-g -I../utils -I../default_list -lpcre -lstemmer -O3 -I../../sparsevect/
CPP=g++
CPPFLAGS=-g -I../utils -I../default_list -lpcre -lstemmer -lmaxent -O3 -Wno-deprecated -I../../sparsevect/
PWD=/home/adam/Work/fossology_trunk/project/adam/C/gld
FOSSDB_CONF="$(PWD)/Db.conf"
SVN_REV=0

utils=\
      ../utils/re.c \
      ../utils/token.c \
      ../utils/token_feature.c \
      ../utils/tokenizer.c \
      ../utils/file_utils.c \
      ../utils/repr.c

utils_o := $(patsubst %.c,%.o,$(utils))

libfoss_o=\
	../../../../fossology/devel/libfossdb/libfossdb.o\
	../../../../fossology/devel/libfossagent/libfossagent.o\
	../../../../fossology/devel/libfossrepo/libfossrepo.o

libfoss_I=\
	  -I../../../../fossology/devel/libfossagent/\
	  -I../../../../fossology/devel/libfossdb/\
	  -I../../../../fossology/devel/libfossrepo/

sv_o=../../sparsevect/sparsevect.o

dl_a=../default_list/default_list.a

all: db gld

db:
	make -C ../../../../fossology/devel/libfossdb/ CONFDEF="-DFOSSDB_CONF='\"Db.conf\"'"

../default_list/default_list.a: ../default_list/default_list.c ../default_list/default_list_basic.c
	make -C ../default_list default_list.a

../utils/%:
	make -C ../utils

gld: gld.o $(utils_o) $(libfoss_o) $(sv_o) $(dl_a)
	$(CC) -o $@ $^ $(CFLAGS) $(libfoss_I) -I`pg_config --includedir` -lpq

gld.o: gld.c
	$(CC) -o $@ -c $^ $(CFLAGS) $(libfoss_I) -DSVN_REV=$(SVN_REV) -I`pg_config --includedir`

../../sparsevect/sparsevect.o: ../../sparsevect/sparsevect.c
	make -C ../../sparsevect/

clean:
	rm -f *.o gdl

phony: all db clean ../sparsevect/sparsevect.o

# vim: noet
