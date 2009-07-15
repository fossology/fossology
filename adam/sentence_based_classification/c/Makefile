CC=g++
CFLAGS=-g -Iinclude -lpcre -lstemmer -Wno-deprecated
utils=\
      utils/list.c \
      utils/re.c \
      utils/token.c \
      utils/stem.c

all: utils/utils.a test

utils/utils.a: $(utils:.c=.o)
	$(AR) -cru $@ $^

test: test.cpp utils/utils.a
	$(CC) -o $@ $^ $(CFLAGS)

clean:
	rm -f test *.o utils/*.o utils/*.a

phony: all clean
