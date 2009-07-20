CC=gcc
CFLAGS=-g -Iinclude -lpcre -lstemmer -lmaxent
CPP=g++
CPPFLAGS=-g -Iinclude -lpcre -lstemmer -lmaxent -Wno-deprecated
utils=\
      utils/list.c \
      utils/re.c \
      utils/token.c \
      utils/stem.c \
      utils/feature_type.c \
      utils/tokenizer.c \
      utils/file_utils.c \
      utils/maxent_utils.cpp

utils_o := $(patsubst %.cpp,%.o,$(utils))
utils_o := $(patsubst %.c,%.o,$(utils_o))

all: utils/utils.a train_sent_model label_sentences test

utils/utils.a: $(utils_o)
	$(AR) -cru $@ $^

test: test.o utils/utils.a
	$(CPP) -o $@ $^ $(CPPFLAGS)

train_sent_model: train_sent_model.o utils/utils.a
	$(CPP) -o $@ $^ $(CPPFLAGS)

label_sentences: label_sentences.o utils/utils.a
	$(CPP) -o $@ $^ $(CPPFLAGS)

clean:
	rm -f test *.o utils/*.o utils/*.a

phony: all clean
