CC=g++
CFLAGS=-Iinclude -lpcre -llibstemmer
utils=\
      utils/list.c \
      utils/re.c \
      utils/token.c \
      utils/stem.c

all: utils/utils.o test

utils/utils.o: $(utils:.c=.o)
	$(AR) -cru $@ $^

test: test.cpp utils/utils.o
	$(CC) -o $@ $^ $(CFLAGS)

clean:
	rm -f test *.o utils/*.o

phony: all clean
