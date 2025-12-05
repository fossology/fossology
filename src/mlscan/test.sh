#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors
# SPDX-License-Identifier: GPL-2.0-only

# Quick test script for mlscan agent

echo "========================================="
echo "ML License Scanner - Quick Test"
echo "========================================="
echo ""

# Get the correct path
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ML_DIR="$SCRIPT_DIR/agent/ml"

echo "Working directory: $SCRIPT_DIR"
echo "ML code directory: $ML_DIR"
echo ""

# Check if Python dependencies are installed
echo "Checking Python dependencies..."
if ! python3 -c "import sentence_transformers" 2>/dev/null; then
    echo "⚠️  Python dependencies not installed!"
    echo ""
    echo "To install dependencies, run:"
    echo "  cd $SCRIPT_DIR"
    echo "  ./install_deps.sh"
    echo ""
    echo "Or manually:"
    echo "  cd $ML_DIR"
    echo "  pip3 install -r requirements.txt"
    exit 1
fi

echo "✓ Dependencies installed"
echo ""

# Run the test
echo "Running ML component tests..."
cd "$ML_DIR"
python3 test_ml.py

if [ $? -eq 0 ]; then
    echo ""
    echo "========================================="
    echo "✓ All tests passed!"
    echo "========================================="
else
    echo ""
    echo "========================================="
    echo "✗ Tests failed"
    echo "========================================="
    exit 1
fi
