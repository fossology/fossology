CFLAGS = -g -I`pg_config --includedir`

all: test

test: test.o
	$(CC) -o test test.o -lpq

clean:
	rm -f test.o test

phony: all clean

# vim: noet
