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
CFLAGS=-g -Iinclude -Idefault_list -lpcre -lstemmer -O3 -I../sparsevect/
CPP=g++
CPPFLAGS=-g -Iinclude -Idefault_list -lpcre -lstemmer -lmaxent -O3 -Wno-deprecated -I../sparsevect/
utils=\
      utils/re.c \
      utils/token.c \
      utils/token_feature.c \
      utils/tokenizer.c \
      utils/file_utils.c \
      utils/repr.c

utils_o := $(patsubst %.cpp,%.o,$(utils))
utils_o := $(patsubst %.c,%.o,$(utils_o))

all: train_sent_model label_sentences database test f1 gld

default_list/default_list.a:
	make -C default_list default_list.a

#utils/utils.a: $(utils_o) ../sparsevect/sparsevect.o
#	$(AR) -cru $@ $^

test_app: test_app.o $(utils_o) ../sparsevect/sparsevect.o default_list/default_list.a utils/maxent_utils.o utils/maxent_utils.o
	$(CPP) -o $@ $^ $(CPPFLAGS)

gld: gld.o $(utils_o) ../sparsevect/sparsevect.o default_list/default_list.a ../../../fossology/devel/libfossdb/libfossdb.o ../../../fossology/devel/libfossagent/libfossagent.o ../../../fossology/devel/libfossrepo/libfossrepo.o
	$(CC) -o $@ $^ $(CFLAGS) -I../../../fossology/devel/libfossagent/ -I../../../fossology/devel/libfossdb/ -I../../../fossology/devel/libfossrepo/ -I`pg_config --includedir` -lpq

gld.o: gld.c
	$(CC) -o $@ -c $^ $(CFLAGS) -I../../../fossology/devel/libfossagent/ -I../../../fossology/devel/libfossdb/ -I../../../fossology/devel/libfossrepo/ -DSVN_REV $(SVN_REV) -I`pg_config --includedir`

f1: f1.o $(utils_o) ../sparsevect/sparsevect.o default_list/default_list.a utils/sentence.o utils/maxent_utils.o
	$(CPP) -o $@ $^ $(CPPFLAGS)

train_sent_model: train_sent_model.o $(utils_o) ../sparsevect/sparsevect.o default_list/default_list.a utils/maxent_utils.o utils/sentence.o
	$(CPP) -o $@ $^ $(CPPFLAGS)

label_sentences: label_sentences.o $(utils_o) ../sparsevect/sparsevect.o default_list/default_list.a utils/maxent_utils.o utils/sentence.o
	$(CPP) -o $@ $^ $(CPPFLAGS)

database: database.o $(utils_o) ../sparsevect/sparsevect.o default_list/default_list.a utils/sentence.o utils/maxent_utils.o
	$(CPP) -o $@ $^ $(CPPFLAGS)

../sparsevect/sparsevect.o:
	make -C ../sparsevect/

maxent.txt:
	find ../Training/Sentences/*.sent > maxent.txt

training.txt:
	find ../Licenses/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > training.txt

maxent.dat: train_sent_model maxent.txt
	./train_sent_model -f maxent.txt -o maxent.dat

database.dat: database maxent.dat training.txt
	./database -f training.txt -m maxent.dat -o database.dat

clean:
	rm -f test_app f1 database train_sent_model label_sentences *.o utils/*.o utils/*.a
	make -C default_list clean

phony: all clean ../sparsevect/sparsevect.o

# vim: noet
