CC=gcc
CFLAGS=-g -I../include -I../default_list -lpcre -lstemmer -lmaxent -O3 -I../../../sparsevect/
CPP=g++
CPPFLAGS=-g -I../include -I../default_list -lpcre -lstemmer -lmaxent -O3 -Wno-deprecated -I../../../sparsevect/

utils=\
	  ../utils/file_utils.o \
	  ../utils/maxent_utils.o \
	  ../utils/token.o \
	  ../utils/token_feature.o \
	  ../utils/tokenizer.o \
	  ../utils/re.o \
	  ../utils/sentence.o \
	  ../utils/repr.o

all: test_tokenizer test_sentences

test_tokenizer: test_tokenizer.o ../default_list/default_list.a ../../../sparsevect/sparsevect.o $(utils)
	$(CPP) -o $@ $^ $(CPPFLAGS)

test_sentences: test_sentences.o ../default_list/default_list.a ../../../sparsevect/sparsevect.o $(utils)
	$(CPP) -o $@ $^ $(CPPFLAGS)

../default_list/default_list.a:
	make -C ../default_list/default_list.a

../utils/file_utils.o: ../utils/file_utils.c
	make -C ../ utils/file_utils.o

../utils/maxent_utils.o: ../utils/maxent_utils.cpp
	make -C ../ utils/maxent_utils.o

../utils/sentence.o: ../utils/sentence.c
	make -C ../ utils/sentence.o

../utils/token.o: ../utils/token.c
	make -C ../ utils/token.o

../utils/token_feature.o: ../utils/token_feature.c
	make -C ../ utils/token_feature.o

../utils/tokenizer.o: ../utils/tokenizer.c
	make -C ../ utils/tokenizer.o

../utils/re.o: ../utils/re.c
	make -C ../ utils/re.o

../utils/repr.o: ../utils/repr.c
	make -C ../ utils/repr.o

clean:
	rm *.o test_tokenizer

phony: clean

# vim: noet
