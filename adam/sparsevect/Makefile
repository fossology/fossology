CC = /usr/bin/gcc
CFLAGS = -Wall -g -O2 #-fPIC # Using -g so that gdb is actually useful
OBJFILES = test.o sparsevect.o
HFILES = sparsevect.h
TARGET = test

all: $(TARGET) python/sparsevect.so

$(TARGET): $(OBJFILES)
	$(CC) $(CFLAGS) -o $(@) $(OBJFILES)

$(OBJFILES): $(HFILES)

python/sparsevect.so: python/sparsevect.pyx sparsevect.o
	cd python && ./setup.py build_ext --inplace

clean:
	rm -f $(OBJFILES) $(TARGET) python/*.so python/*.o python/*.c
	cd python && ./setup.py clean
