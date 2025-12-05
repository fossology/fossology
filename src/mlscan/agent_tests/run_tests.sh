#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors
# SPDX-License-Identifier: GPL-2.0-only

# Run all unit tests for mlscan agent

echo "========================================="
echo "ML License Scanner - Unit Tests"
echo "========================================="
echo ""

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ML_DIR="$SCRIPT_DIR/../../agent/ml"

# Check if Python dependencies are installed
echo "Checking Python dependencies..."
if ! python3 -c "import sentence_transformers" 2>/dev/null; then
    echo "⚠️  Python dependencies not installed!"
    echo "Run: cd $SCRIPT_DIR/../.. && ./install_deps.sh"
    exit 1
fi

echo "✓ Dependencies installed"
echo ""

# Run unit tests
echo "Running unit tests..."
cd "$SCRIPT_DIR/Unit"

# Run ML component tests
echo ""
echo "=== Testing ML Components ==="
python3 test_ml_components.py

if [ $? -ne 0 ]; then
    echo "✗ ML component tests failed"
    exit 1
fi

# Run integration tests
echo ""
echo "=== Testing Integration ==="
python3 test_integration.py

if [ $? -ne 0 ]; then
    echo "✗ Integration tests failed"
    exit 1
fi

echo ""
echo "========================================="
echo "✓ All tests passed!"
echo "========================================="
