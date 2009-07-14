CC=g++
CFLAGS=-Iinclude -Ilibstemmer_c/include -lpcre
utils=\
      utils/list.c \
      utils/re.c \
      utils/token.c \
      utils/stem.c

all: libstemmer_c/libstemmer.o utils/utils.o test

utils/utils.o: $(utils:.c=.o)
	$(AR) -cru $@ $^

test: test.cpp utils/utils.o libstemmer_c/libstemmer.o
	$(CC) -o $@ $^ $(CFLAGS)

libstemmer_c/libstemmer.o:
	make -C libstemmer_c libstemmer.o

clean:
	make -C libstemmer_c clean
	rm -f test *.o utils/*.o

phony: libstemmer_c all clean
