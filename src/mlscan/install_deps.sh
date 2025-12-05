#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors
# SPDX-License-Identifier: GPL-2.0-only

# Installation script for mlscan agent Python dependencies

echo "Installing Python dependencies for mlscan agent..."

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ML_DIR="$SCRIPT_DIR/agent/ml"

# Check if requirements.txt exists
if [ ! -f "$ML_DIR/requirements.txt" ]; then
    echo "Error: requirements.txt not found at $ML_DIR/requirements.txt"
    exit 1
fi

# Install dependencies
echo "Installing from: $ML_DIR/requirements.txt"
pip3 install -r "$ML_DIR/requirements.txt"

if [ $? -eq 0 ]; then
    echo "✓ Python dependencies installed successfully!"
    echo ""
    echo "To test the ML components, run:"
    echo "  cd $ML_DIR"
    echo "  python3 test_ml.py"
else
    echo "✗ Failed to install Python dependencies"
    exit 1
fi
