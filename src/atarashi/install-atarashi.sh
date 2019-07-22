#!/bin/bash

sudo apt-get install software-properties-common
sudo add-apt-repository ppa:deadsnakes/ppa
sudo apt-get update
sudo apt-get install python>=3.6

curl https://bootstrap.pypa.io/get-pip.py -o get-pip.py
python3 get-pip.py

pip install tqdm>=4.23.4
pip install pandas>=0.23.1
pip install pyxDamerauLevenshtein>=1.5
pip install scikit-learn>=0.18.1
pip install scipy>=0.18.1
pip install textdistance>=3.0.3
pip install setuptools>=39.2.0
pip install code_comment@git+https://github.com/amanjain97/code_comment@master#egg=code_comment
pip install urllib3>=1.24.1

pip install -i https://test.pypi.org/simple/ atarashi
