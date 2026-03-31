#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Contributors to FOSSology
# SPDX-License-Identifier: GPL-2.0-only

import sys
import os

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

LICENSE_KEYWORDS = ["license", "copyright", "apache", "mit", "gpl", "spdx"]


def classify(comment):
    text = comment.lower()

    for word in LICENSE_KEYWORDS:
        if word in text:
            return "LICENSE"

    if "todo" in text or "fixme" in text:
        return "NOISE"

    return "DEV_COMMENT"


TEST_CASES = [
    # ✅ Correct license
    ("// Licensed under Apache License 2.0", "LICENSE"),
    ("/* SPDX-License-Identifier: MIT */", "LICENSE"),
    ("// SPDX-License-Identifier: GPL-2.0", "LICENSE"),

    # ❌ False positives
    ("// LicenseManager handles authentication", "DEV_COMMENT"),
    ("// permissionGranted flag controls access", "DEV_COMMENT"),

    # ❌ False negatives
    ("// Distributed without warranty", "LICENSE"),
    ("// See apache.org/licenses/LICENSE-2.0", "LICENSE"),

    # ✅ Normal comments
    ("// Initialize cache before request", "DEV_COMMENT"),

    # ✅ Noise
    ("// TODO: fix this later", "NOISE"),

    # ❌ Tricky case
    ("// CopyrightYear = 2024", "DEV_COMMENT"),
]


def run():
    failures = []
    false_pos = []
    false_neg = []

    for i, (comment, expected) in enumerate(TEST_CASES, 1):
        predicted = classify(comment)

        if predicted != expected:
            failures.append((i, comment, expected, predicted))

            if predicted == "LICENSE" and expected != "LICENSE":
                false_pos.append(comment)

            if predicted != "LICENSE" and expected == "LICENSE":
                false_neg.append(comment)

    print("\n=== Failure Benchmark Report ===")
    print(f"Total cases: {len(TEST_CASES)}")
    print(f"Failures   : {len(failures)}\n")

    for f in failures:
        print(f"[Case {f[0]}]")
        print(f"Comment   : {f[1]}")
        print(f"Expected  : {f[2]}")
        print(f"Predicted : {f[3]}")
        print("-" * 50)

    print("\n--- Summary (OLD keyword-based) ---")
    print(f"False Positives: {len(false_pos)}")
    print(f"False Negatives: {len(false_neg)}")

    print("\nInsight: Keyword-based methods fail due to lack of context understanding.")

    # --- NEW: comparison with enhanced classifier ---
    try:
        from nirjas.comment_classifier import classify_comment

        new_failures = []
        for i, (comment, expected) in enumerate(TEST_CASES, 1):
            predicted = classify_comment(comment).value
            if predicted != expected:
                new_failures.append((i, comment, expected, predicted))

        print(f"\n\n=== Enhanced Classifier Results (NEW phrase-based) ===")
        print(f"Total cases: {len(TEST_CASES)}")
        print(f"Failures   : {len(new_failures)}\n")

        if new_failures:
            for f in new_failures:
                print(f"[Case {f[0]}]")
                print(f"Comment   : {f[1]}")
                print(f"Expected  : {f[2]}")
                print(f"Predicted : {f[3]}")
                print("-" * 50)
        else:
            print("ALL CASES PASSED - no false positives or false negatives.")

        print(f"\n--- Comparison ---")
        print(f"Old failures: {len(failures)}")
        print(f"New failures: {len(new_failures)}")
        print(f"Improvement : {len(failures) - len(new_failures)} cases fixed")

    except ImportError:
        print("\n[SKIP] Enhanced classifier not available.")


if __name__ == "__main__":
    run()