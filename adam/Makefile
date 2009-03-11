PWD = $(shell pwd)
PYTHONPATH := ${PYTHONPATH}:$(PWD)/utils:$(PWD)/sparsevect/python:$(PWD)/sentence_based_classification
export PYTHONPATH
SENTENCE_FILES = Training/Sentences/*.sent
TEMPLATE_FILE = templates.txt
TEST_FILE = tests.txt

all: models/SentenceModel.dat models/Database.dat

models/SentenceModel.dat: $(SENTENCE_FILES)
	mkdir models
	python utils/sentence_trainner.py --mode train --model_file $@ $(SENTENCE_FILES)

models/Database.dat: $(TEMPLATE_FILE)
	mkdir models
	python sentence_based_classification/f1.py -d -S models/SentenceModel.dat -D $@ -T $<

tests: models/SentenceModel.dat models/Database.dat
	rm -rf output
	mkdir output
	python sentence_based_classification/f1.py -S models/SentenceModel.dat -D models/Database.dat -f tests.txt -o output

clean:
	rm models/SentenceModel.dat
	rm models/Database.dat
	rm -rf output

