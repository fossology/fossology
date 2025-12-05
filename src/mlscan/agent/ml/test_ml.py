#!/usr/bin/env python3
"""
SPDX-FileCopyrightText: © Fossology contributors
SPDX-License-Identifier: GPL-2.0-only

Simple test to verify ML components work
"""

import sys
import os

# Add ml directory to path
sys.path.insert(0, os.path.dirname(__file__))

# Test imports
print("Testing imports...")
try:
    from preprocessing import Preprocessor
    print("✓ Preprocessor imported")
except Exception as e:
    print(f"✗ Failed to import Preprocessor: {e}")
    sys.exit(1)

try:
    from rule_engine import RuleEngine
    print("✓ RuleEngine imported")
except Exception as e:
    print(f"✗ Failed to import RuleEngine: {e}")
    sys.exit(1)

try:
    from ml_engine import MLEngine
    print("✓ MLEngine imported")
except Exception as e:
    print(f"✗ Failed to import MLEngine: {e}")
    sys.exit(1)

try:
    from hybrid_engine import HybridEngine
    print("✓ HybridEngine imported")
except Exception as e:
    print(f"✗ Failed to import HybridEngine: {e}")
    sys.exit(1)

# Test basic functionality
print("\nTesting basic functionality...")
test_text = """
MIT License

Copyright (c) 2024 Test

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
"""

try:
    preprocessor = Preprocessor()
    clean_text = preprocessor.clean_text(test_text)
    print(f"✓ Preprocessing works (cleaned {len(test_text)} -> {len(clean_text)} chars)")
except Exception as e:
    print(f"✗ Preprocessing failed: {e}")
    sys.exit(1)

try:
    hybrid_engine = HybridEngine()
    result = hybrid_engine.analyze(clean_text)
    print(f"✓ Hybrid engine works")
    print(f"  Detected {len(result.detected_licenses)} licenses")
    if result.detected_licenses:
        top = result.detected_licenses[0]
        print(f"  Top result: {top.license_name} (confidence: {top.confidence:.2f})")
except Exception as e:
    print(f"✗ Hybrid engine failed: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)

print("\n✓ All tests passed!")
