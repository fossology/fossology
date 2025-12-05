#!/usr/bin/env python3
"""
SPDX-FileCopyrightText: © Fossology contributors
SPDX-License-Identifier: GPL-2.0-only

Simple test to verify ML components work
"""

import sys
import os

# Add ml directory to path
ml_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '../agent/ml'))
sys.path.insert(0, ml_dir)

print("Testing ML License Scanner...")
print(f"ML directory: {ml_dir}")
print("")

# Test imports
print("1. Testing imports...")
try:
    from preprocessing import Preprocessor
    print("   ✓ Preprocessor imported")
except Exception as e:
    print(f"   ✗ Failed to import Preprocessor: {e}")
    sys.exit(1)

try:
    from rule_engine import RuleEngine
    print("   ✓ RuleEngine imported")
except Exception as e:
    print(f"   ✗ Failed to import RuleEngine: {e}")
    sys.exit(1)

try:
    from ml_engine import MLEngine
    print("   ✓ MLEngine imported")
except Exception as e:
    print(f"   ✗ Failed to import MLEngine: {e}")
    sys.exit(1)

try:
    from hybrid_engine import HybridEngine
    print("   ✓ HybridEngine imported")
except Exception as e:
    print(f"   ✗ Failed to import HybridEngine: {e}")
    sys.exit(1)

print("")
print("2. Testing basic functionality...")

# Test preprocessing
try:
    preprocessor = Preprocessor()
    test_text = "MIT License - Permission is hereby granted"
    clean_text = preprocessor.clean_text(test_text)
    print(f"   ✓ Preprocessing works (cleaned {len(test_text)} -> {len(clean_text)} chars)")
except Exception as e:
    print(f"   ✗ Preprocessing failed: {e}")
    sys.exit(1)

# Test hybrid engine
try:
    hybrid_engine = HybridEngine()
    
    test_license = """
MIT License

Copyright (c) 2024 Test

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software.
"""
    
    result = hybrid_engine.analyze(test_license)
    print(f"   ✓ Hybrid engine works")
    print(f"   ✓ Detected {len(result.detected_licenses)} licenses")
    
    if result.detected_licenses:
        top = result.detected_licenses[0]
        print(f"   ✓ Top result: {top.license_name} (confidence: {top.confidence:.2f}, method: {top.method})")
    
except Exception as e:
    print(f"   ✗ Hybrid engine failed: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)

print("")
print("=" * 50)
print("✓ All tests passed!")
print("=" * 50)
