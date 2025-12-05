#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SPDX-FileCopyrightText: © Fossology contributors
SPDX-License-Identifier: GPL-2.0-only

ML License Scanner - Main entry point for FOSSology integration
"""

import os
import sys
import json
import argparse
from pathlib import Path

# Add ml directory to path
script_dir = Path(__file__).parent
sys.path.insert(0, str(script_dir))

from preprocessing import Preprocessor
from hybrid_engine import HybridEngine

def scan_file(file_path: str, output_file: str):
    """
    Scan a single file for license detection using ML
    
    Args:
        file_path: Path to file to scan
        output_file: Path to write JSON results
    """
    try:
        # Read file content
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            text = f.read()
        
        # Initialize engines
        preprocessor = Preprocessor()
        hybrid_engine = HybridEngine()
        
        # Preprocess text
        clean_text = preprocessor.clean_text(text)
        
        # Analyze
        result = hybrid_engine.analyze(clean_text)
        
        # Convert to dict for JSON serialization
        output = {
            'file': file_path,
            'licenses': [
                {
                    'license_name': lic.license_name,
                    'confidence': float(lic.confidence),
                    'method': lic.method
                }
                for lic in result.detected_licenses
            ],
            'conflict_detected': result.conflict_detected,
            'message': result.message
        }
        
        # Write results
        with open(output_file, 'w') as f:
            json.dump(output, f, indent=2)
        
        return 0
        
    except Exception as e:
        print(f"Error scanning file {file_path}: {e}", file=sys.stderr)
        return 1

def scan_batch(file_list: str, output_file: str):
    """
    Scan multiple files listed in a file
    
    Args:
        file_list: Path to file containing list of files to scan
        output_file: Path to write JSON results
    """
    try:
        # Initialize engines once for batch processing
        preprocessor = Preprocessor()
        hybrid_engine = HybridEngine()
        
        results = []
        
        with open(file_list, 'r') as f:
            for line in f:
                file_path = line.strip()
                if not file_path:
                    continue
                
                try:
                    # Read file content
                    with open(file_path, 'r', encoding='utf-8', errors='ignore') as file:
                        text = file.read()
                    
                    # Preprocess and analyze
                    clean_text = preprocessor.clean_text(text)
                    result = hybrid_engine.analyze(clean_text)
                    
                    # Add to results
                    results.append({
                        'file': file_path,
                        'licenses': [
                            {
                                'license_name': lic.license_name,
                                'confidence': float(lic.confidence),
                                'method': lic.method
                            }
                            for lic in result.detected_licenses
                        ],
                        'conflict_detected': result.conflict_detected,
                        'message': result.message
                    })
                    
                except Exception as e:
                    print(f"Error scanning {file_path}: {e}", file=sys.stderr)
                    continue
        
        # Write all results
        with open(output_file, 'w') as f:
            json.dump(results, f, indent=2)
        
        return 0
        
    except Exception as e:
        print(f"Error in batch scanning: {e}", file=sys.stderr)
        return 1

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="ML-based license scanner for FOSSology")
    parser.add_argument('file_location', type=str, help='Path to file or file list to scan')
    parser.add_argument('output_file', type=str, help='Path to output JSON file')
    parser.add_argument('-b', '--batch', action='store_true', help='Batch mode: file_location is a list of files')
    
    args = parser.parse_args()
    
    if args.batch:
        exit_code = scan_batch(args.file_location, args.output_file)
    else:
        exit_code = scan_file(args.file_location, args.output_file)
    
    sys.exit(exit_code)
