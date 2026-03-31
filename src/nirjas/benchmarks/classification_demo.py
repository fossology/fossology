#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Contributors to FOSSology
# SPDX-License-Identifier: GPL-2.0-only

import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from nirjas.comment_classifier import classify_batch, CommentClass

RAW_COMMENTS = [
    "// SPDX-License-Identifier: Apache-2.0",
    "// Initialize database connection",
    "// TODO: add retry logic",
    "// Copyright (c) 2024 FOSSology Contributors",
    "// LicenseManager.validate(token)",
    "// Permission is hereby granted, free of charge",
    "// @param config - runtime configuration object",
    "// ========================================",
    "// Distributed without warranty of any kind",
    "// Parse the JSON response body",
]


def main():
    classified = classify_batch(RAW_COMMENTS)

    print("=" * 70)
    print("STEP 1: Raw Comments (as extracted by Nirjas)")
    print("=" * 70)
    for i, comment in enumerate(RAW_COMMENTS, 1):
        print(f"  [{i:2d}] {comment}")

    print(f"\n{'=' * 70}")
    print("STEP 2: Classified Comments")
    print("=" * 70)
    marker = {"LICENSE": "[LIC]", "DEV_COMMENT": "[DEV]", "NOISE": "[---]"}
    for i, (comment, label) in enumerate(classified, 1):
        print(f"  [{i:2d}] {marker[label.value]} {label.value:13s} | {comment}")

    license_comments = [c for c, l in classified if l == CommentClass.LICENSE]
    dev_comments = [c for c, l in classified if l == CommentClass.DEV_COMMENT]
    noise_comments = [c for c, l in classified if l == CommentClass.NOISE]

    print(f"\n{'=' * 70}")
    print("STEP 3: Filtered for Atarashi (LICENSE only)")
    print("=" * 70)
    for comment in license_comments:
        print(f"  -> {comment}")

    print(f"\n--- Stats ---")
    print(f"Total comments : {len(RAW_COMMENTS)}")
    print(f"License        : {len(license_comments)}")
    print(f"Dev comments   : {len(dev_comments)} (filtered out)")
    print(f"Noise          : {len(noise_comments)} (filtered out)")
    reduction = (1 - len(license_comments) / len(RAW_COMMENTS)) * 100
    print(f"Noise reduction: {reduction:.0f}%")


if __name__ == "__main__":
    main()
