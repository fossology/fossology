#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Copyright (C) 2025  Rajul Jha <rajuljha49@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
"""

import os
import json
import argparse
import subprocess

def run_atarashi(file_path, agent, similarity, verbose):
    """
    Runs atarashi CLI for a single file and returns parsed JSON output.
    """
    env = os.environ.copy()
    env["PYTHONWARNINGS"] = "ignore::UserWarning"
    env["PYTHONPATH"] = "/home/fossy/pythondeps/"

    command = [
        os.path.expanduser("~/pythondeps/bin/atarashi"),
        "-a", agent
    ]
    if similarity:
        command.extend(["-s", similarity])
    if verbose:
        command.append("-v")
    command.append(file_path)

    try:
        result = subprocess.run(
            command,
            env=env,
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        return json.loads(result.stdout)
    except subprocess.CalledProcessError as e:
        print(f"[Atarashi] Error running on {file_path}: {e.stderr}")
        return {"file": file_path, "error": e.stderr.strip()}
    except json.JSONDecodeError:
        print(f"[Atarashi] Failed to parse JSON for {file_path}")
        return {"file": file_path, "error": "Invalid JSON output"}

def process_files(file_location, outputFile, agent, similarity, verbose):
    """
    Process list of files and scan each with Atarashi.
    """
    with open(file_location, "r") as f:
        files = [line.strip() for line in f.readlines() if line.strip()]

    with open(outputFile, "w") as out:
        out.write("[")
        first = True
        for filepath in files:
            result = run_atarashi(filepath, agent, similarity, verbose)

            if not first:
                out.write(",\n")
            else:
                first = False

            json.dump(result, out)
        out.write("\n]")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Run Atarashi scanner on multiple files")
    parser.add_argument("--agent", required=True, choices=["tfidf", "Ngram", "DLD", "wordFrequencySimilarity"], help="Atarashi agent to use")
    parser.add_argument("--similarity", choices=["CosineSim", "scoreSim", "DiceSim", "BigramCosineSim"],
                        help="Similarity function (only for tfidf/ngram)")
    parser.add_argument("--verbose", type=int, help="Set output as verbose")
    parser.add_argument("file_location", type=str, help="File containing list of files to scan")
    parser.add_argument("outputFile", type=str, help="Path to JSON output file")

    args = parser.parse_args()

    process_files(args.file_location, args.outputFile, args.agent, args.similarity, args.verbose)
