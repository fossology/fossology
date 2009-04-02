## 
## Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
## 
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## version 2 as published by the Free Software Foundation.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
## 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##

PWD = $(shell pwd)
PYTHONPATH := ${PYTHONPATH}:$(PWD)/utils:$(PWD)/sparsevect/python:$(PWD)/sentence_based_classification
export PYTHONPATH
SENTENCE_FILES = Training/Sentences/*.sent
TEMPLATE_FILE = templates.txt
TEST_FILE = tests.txt

all: models/ models/SentenceModel.dat models/Database.dat

sparsevect/python/sparsevect.so:
	make -C sparsevect -f Makefile.old

templates.txt:
	find ../../fossology/agents/foss_license_agent/Licenses/Raw/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > templates.txt

models/:
	mkdir models

models/SentenceModel.dat: sparsevect/python/sparsevect.so models/ $(SENTENCE_FILES)
	python utils/sentence_trainner.py --mode train --model_file $@ $(SENTENCE_FILES)

models/Database.dat: sparsevect/python/sparsevect.so models/ models/SentenceModel.dat $(TEMPLATE_FILE)
	python sentence_based_classification/f1.py -d -S models/SentenceModel.dat -D $@ -T $(TEMPLATE_FILE)

tests: sparsevect/python/sparsevect.so models/SentenceModel.dat models/Database.dat
	rm -rf output
	mkdir output
	python sentence_based_classification/f1.py -S models/SentenceModel.dat -D models/Database.dat -f tests.txt -o output

run_webapp: models/Database.dat models/SentenceModel.dat
	webapp/manage.py runserver

print_pythonpath:
	echo $(PYTHONPATH)

clean:
	rm models/SentenceModel.dat
	rm models/Database.dat
	rm -rf output
	rm $(TEMPLATE_FILE)
	make -C sparsevect -f Makefile.old clean

