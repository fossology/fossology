CFLAGS = -g -I`pg_config --includedir`

all: loader

loader: loader.o
	$(CC) -o loader loader.o -lpq

clean:
	rm -f loader.o loader

phony: all clean

# vim: noet
