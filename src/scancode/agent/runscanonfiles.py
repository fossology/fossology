#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Copyright (C) 2023  Sushant Kumar (sushantmishra02102002@gmail.com)

SPDX-License-Identifier: GPL-2.0-only
"""

import os
import json
import argparse
import signal
import sys
import time
import resource
import multiprocessing as mp
from multiprocessing import Pool, Value, Manager
from pathlib import Path
import threading
import psutil
import queue
import concurrent.futures

script_directory = os.path.dirname(os.path.abspath(__file__))
os.environ["SCANCODE_CACHE"] = os.path.join(script_directory, '.cache')

from scancode import api

SCANCODE_PARALLEL = 1
SCANCODE_NICE = 10
SCANCODE_MIN_MEMORY_PER_PROCESS = 1024
SCANCODE_MAX_TASKS = 1000
SCANCODE_HEARTBEAT_INTERVAL = 60

files_processed = Value('i', 0)
files_total = Value('i', 0)
heartbeat_active = False

active_pool = None
parent_pid = os.getpid()
manager = None
shared_data = None
termination_requested = False 
termination_event = threading.Event() 

def update_license(licenses):
  """
  Extracts relevant information from the 'licenses' data.
  Parameters:
    licenses (dict): A dictionary containing license information.
  Returns:
    list: A list of dictionaries containing relevant license information.
  """
  updated_licenses = []
  keys_to_extract_from_licenses = ['license_expression_spdx', 'score', 'license_expression', 'rule_url', 'start_line', 'matched_text']

  for license in licenses:
    for matches in license.get("matches", []):
      updated_licenses.append({
        key: matches[key] for key in keys_to_extract_from_licenses if key in matches
      })

  return updated_licenses

def update_copyright(copyrights):
  """
  Extracts relevant information from the 'copyrights' data.
  Parameters:
    copyrights (dict): A dictionary containing copyright information.
  Returns:
    tuple: A tuple of two lists. The first list contains updated copyright information,
    and the second list contains updated holder information.
  """
  updated_copyrights = []
  updated_holders = []
  keys_to_extract_from_copyrights = ['copyright', 'start_line']
  keys_to_extract_from_holders = ['holder', 'start_line']
  key_mapping = {
    'start_line': 'start',
    'copyright': 'value',
    'holder': 'value'
  }

  for key, value in copyrights.items():
    if key == 'copyrights':
      for copyright in value:
        updated_copyrights.append({key_mapping.get(key, key): copyright[key] for key in keys_to_extract_from_copyrights if key in copyright})
    if key == 'holders':
      for holder in value:
        updated_holders.append({key_mapping.get(key, key): holder[key] for key in keys_to_extract_from_holders if key in holder})
  return updated_copyrights, updated_holders

def update_emails(emails):
  """
  Extracts relevant information from the 'emails' data.
  Parameters:
    emails (dict): A dictionary containing email information.
  Returns:
    list: A list of dictionaries containing relevant email information.
  """
  updated_emails = []
  keys_to_extract_from_emails = ['email', 'start_line']
  key_mapping = {
    'start_line': 'start',
    'email': 'value'
  }

  for key, value in emails.items():
    if key == 'emails':
      for email in value:
        updated_emails.append({key_mapping.get(key, key): email[key] for key in keys_to_extract_from_emails if key in email})

  return updated_emails

def update_urls(urls):
  """
  Extracts relevant information from the 'urls' data.
  Parameters:
    urls (dict): A dictionary containing url information.
  Returns:
    list: A list of dictionaries containing relevant url information.
  """
  updated_urls = []
  keys_to_extract_from_urls = ['url', 'start_line']
  key_mapping = {
    'start_line': 'start',
    'url': 'value'
  }

  for key, value in urls.items():
    if key == 'urls':
      for url in value:
        updated_urls.append({key_mapping.get(key, key): url[key] for key in keys_to_extract_from_urls if key in url})

  return updated_urls

def scan(line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score):
  """
  Processes a single file and returns the results.
  Parameters:
    line (str): A line from the file containing the list of files to scan.
    scan_copyrights (bool):
    scan_licenses (bool):
    scan_emails (bool):
    scan_urls (bool):
  """
  result = {'file': line.strip()}
  result['licenses'] = []
  result['copyrights'] = []
  result['holders'] = []
  result['emails'] = []
  result['urls'] = []

  if scan_copyrights:
    copyrights = api.get_copyrights(result['file'])
    updated_copyrights, updated_holders = update_copyright(copyrights)
    result['copyrights'] = updated_copyrights
    result['holders'] = updated_holders

  if scan_licenses:
    licenses = api.get_licenses(result['file'], include_text=True, min_score=min_score)
    updated_licenses = update_license(licenses.get("license_detections", []))
    result['licenses'] = updated_licenses

  if scan_emails:
    emails = api.get_emails(result['file'])
    updated_emails = update_emails(emails)
    result['emails'] = updated_emails

  if scan_urls:
    urls = api.get_urls(result['file'])
    updated_urls = update_urls(urls)
    result['urls'] = updated_urls

  return result

def get_available_memory():
    """Get available system memory in MB"""
    try:
        mem = psutil.virtual_memory()
        available_mb = mem.available / (1024 * 1024)
        return int(available_mb)
    except Exception:
        try:
            with open('/proc/meminfo', 'r') as f:
                for line in f:
                    if line.startswith('MemAvailable:'):
                        return int(line.split()[1]) // 1024
        except:
            return 2048

def calculate_optimal_processes(requested_processes, min_memory_per_process=512):
    """Calculate optimal number of processes based on available memory"""
    available_memory = get_available_memory()
    usable_memory = int(available_memory * 0.8)
    max_processes_by_memory = max(1, usable_memory // min_memory_per_process)
    optimal_processes = min(requested_processes, max_processes_by_memory)
    memory_per_process = usable_memory // optimal_processes
    
    print(f"Memory Analysis:")
    print(f"  Available system memory: {available_memory} MB")
    print(f"  Usable memory (80%): {usable_memory} MB")
    print(f"  Requested processes: {requested_processes}")
    print(f"  Optimal processes: {optimal_processes}")
    print(f"  Memory per process: {memory_per_process} MB")
    
    if optimal_processes < requested_processes:
        print(f"  WARNING: Reduced processes from {requested_processes} to {optimal_processes} due to memory constraints")
    
    return optimal_processes, memory_per_process

def check_parent_alive(parent_pid):
    """Check if parent process is still alive"""
    try:
        os.kill(parent_pid, 0)
        return True
    except ProcessLookupError:
        return False
    except PermissionError:
        return True

def worker_watchdog(parent_pid, worker_pid):
    """Background thread in worker to monitor parent and self-terminate if parent dies"""
    while True:
        time.sleep(2)
        if not check_parent_alive(parent_pid):
            print(f"Worker {worker_pid}: Parent died, terminating...", flush=True)
            try:
                os.killpg(os.getpgid(worker_pid), signal.SIGKILL)
            except:
                os._exit(1)

def init_worker_process(parent_pid, shared_dict):
    """Initialize worker process with parent monitoring"""
    try:
        my_pid = os.getpid()
        os.setpgrp()
        
        current_nice = os.nice(0)
        if current_nice < SCANCODE_NICE:
            os.nice(SCANCODE_NICE - current_nice)
        
        signal.signal(signal.SIGTERM, lambda s, f: sys.exit(0))
        signal.signal(signal.SIGINT, signal.SIG_IGN)
        signal.signal(signal.SIGALRM, signal.SIG_IGN)
        
        watchdog = threading.Thread(
            target=worker_watchdog, 
            args=(parent_pid, my_pid),
            daemon=True
        )
        watchdog.start()
        
        shared_dict[f'worker_{my_pid}'] = my_pid
        
        try:
            import ctypes
            libc = ctypes.CDLL("libc.so.6")
            PR_SET_PDEATHSIG = 1
            libc.prctl(PR_SET_PDEATHSIG, signal.SIGKILL)
        except:
            pass
        
        print(f"Worker {my_pid} initialized (parent={parent_pid}, pgid={os.getpgrp()})", flush=True)
        
    except Exception as e:
        print(f"Worker init failed: {e}", flush=True)
        sys.exit(1)

def cleanup_handler(signum, frame):
    """Handle termination signals - enhanced for sequential mode"""
    global heartbeat_active, active_pool, shared_data, manager, termination_requested, termination_event
    
    print(f"\nReceived signal {signum}, cleaning up...", flush=True)
    
    heartbeat_active = False
    signal.alarm(0)
    termination_requested = True 
    termination_event.set()  
    
    if shared_data:
        shared_data['terminate'] = True
    
    if active_pool:
        try:
            active_pool.terminate()
            active_pool.join(timeout=1)
        except:
            pass
        
        if shared_data:
            for key, pid in list(shared_data.items()):
                if key.startswith('worker_'):
                    try:
                        os.killpg(os.getpgid(pid), signal.SIGKILL)
                    except:
                        try:
                            os.kill(pid, signal.SIGKILL)
                        except:
                            pass
    else:
        print("Sequential mode termination requested", flush=True)
    
    if manager:
        try:
            manager.shutdown()
        except:
            pass
    
    print("Exiting due to signal", flush=True)
    sys.exit(130 if signum == signal.SIGINT else 143)

def heartbeat_handler(signum, frame):
    """Handle SIGALRM for heartbeat reporting"""
    global heartbeat_active
    
    if heartbeat_active:
        with files_processed.get_lock():
            processed = files_processed.value
        with files_total.get_lock():
            total = files_total.value
        
        if total > 0:
            progress_percent = (processed / total) * 100
            remaining = total - processed
            
            elapsed_time = time.time() - getattr(heartbeat_handler, 'start_time', time.time())
            if processed > 0 and elapsed_time > 0:
                rate = processed / elapsed_time
                eta_seconds = remaining / rate if rate > 0 else 0
                eta_str = f" | ETA: {int(eta_seconds//60)}m {int(eta_seconds%60)}s"
            else:
                eta_str = ""
        else:
            progress_percent = 0
            eta_str = ""
        
        try:
            mem_mb = psutil.Process().memory_info().rss / (1024 * 1024)
            mem_str = f" | Memory: {mem_mb:.1f} MB"
        except:
            mem_str = ""
        
        print(f"PROGRESS: {processed}/{total} files ({progress_percent:.1f}%){eta_str}{mem_str}", flush=True)
        signal.alarm(SCANCODE_HEARTBEAT_INTERVAL)

heartbeat_handler.start_time = None

def setup_heartbeat_monitoring(total_files):
    """Set up heartbeat monitoring"""
    global heartbeat_active
    
    with files_total.get_lock():
        files_total.value = total_files
    
    heartbeat_active = True
    heartbeat_handler.start_time = time.time()
    signal.signal(signal.SIGALRM, heartbeat_handler)
    signal.alarm(SCANCODE_HEARTBEAT_INTERVAL)

def cleanup_heartbeat_monitoring():
    """Clean up heartbeat monitoring"""
    global heartbeat_active
    
    heartbeat_active = False
    signal.alarm(0)

def is_termination_requested():
    """Check if termination has been requested using multiple methods"""
    global termination_requested, shared_data, termination_event
    
    if termination_event.is_set():
        return True
    
    if termination_requested:
        return True
    
    if shared_data and shared_data.get('terminate', False):
        return True
    
    return False

class TimeoutError(Exception):
    pass

def run_with_timeout_and_termination_check(func, args, timeout=30):
    """
    Run a function with timeout and frequent termination checks.
    This is the key fix for sequential mode.
    """
    result_queue = queue.Queue()
    exception_queue = queue.Queue()
    
    def target():
        try:
            result = func(*args)
            result_queue.put(result)
        except Exception as e:
            exception_queue.put(e)
    
    thread = threading.Thread(target=target)
    thread.daemon = True
    thread.start()
    
    check_interval = 0.1
    elapsed = 0.0
    
    while elapsed < timeout:
        thread.join(timeout=check_interval)
        
        if not thread.is_alive():
            break
            
        if is_termination_requested():
            print(f"Termination requested during {func.__name__}, aborting...", flush=True)
            return None
            
        elapsed += check_interval
    
    if thread.is_alive():
        print(f"Timeout in {func.__name__} after {timeout}s", flush=True)
        return None
    
    if not exception_queue.empty():
        raise exception_queue.get()
    
    if not result_queue.empty():
        return result_queue.get()
    
    return None

def scan_single_file(args):
    """
    Processes a single file and returns the results.
    Enhanced with frequent termination checks for sequential processing
    """
    line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score, parent_pid = args
    
    if not check_parent_alive(parent_pid):
        print(f"Worker: Parent process died, exiting", flush=True)
        sys.exit(1)
    
    if is_termination_requested():
        print("Termination requested before file processing", flush=True)
        return None
    
    result = {'file': line.strip()}
    result['licenses'] = []
    result['copyrights'] = []
    result['holders'] = []
    result['emails'] = []
    result['urls'] = []

    try:
        if scan_copyrights:
            if is_termination_requested():
                return None
            
            print(f"Scanning copyrights for {result['file'][:50]}...", flush=True)
            copyrights = run_with_timeout_and_termination_check(
                api.get_copyrights, (result['file'],), timeout=30
            )
            if copyrights is None:
                if is_termination_requested():
                    return None
                print(f"Timeout scanning copyrights for {result['file']}")
            else:
                updated_copyrights, updated_holders = update_copyright(copyrights)
                result['copyrights'] = updated_copyrights
                result['holders'] = updated_holders

        if scan_licenses:
            if is_termination_requested():
                return None
            
            print(f"Scanning licenses for {result['file'][:50]}...", flush=True)
            licenses = run_with_timeout_and_termination_check(
                api.get_licenses, 
                (result['file'], True, min_score), 
                timeout=30
            )
            if licenses is None:
                if is_termination_requested():
                    return None
                print(f"Timeout scanning licenses for {result['file']}")
            else:
                updated_licenses = update_license(licenses)
                result['licenses'] = updated_licenses

        if scan_emails:
            if is_termination_requested():
                return None
            
            print(f"Scanning emails for {result['file'][:50]}...", flush=True)
            emails = run_with_timeout_and_termination_check(
                api.get_emails, (result['file'],), timeout=30
            )
            if emails is None:
                if is_termination_requested():
                    return None
                print(f"Timeout scanning emails for {result['file']}")
            else:
                updated_emails = update_emails(emails)
                result['emails'] = updated_emails

        if scan_urls:
            if is_termination_requested():
                return None
            
            print(f"Scanning URLs for {result['file'][:50]}...", flush=True)
            urls = run_with_timeout_and_termination_check(
                api.get_urls, (result['file'],), timeout=30
            )
            if urls is None:
                if is_termination_requested():
                    return None
                print(f"Timeout scanning urls for {result['file']}")
            else:
                updated_urls = update_urls(urls)
                result['urls'] = updated_urls

        with files_processed.get_lock():
            files_processed.value += 1
        
        return result

    except Exception as e:
        print(f"An error occurred for file '{line.strip()}': {e}")
        with files_processed.get_lock():
            files_processed.value += 1
        return result

def process_files_sequential(file_location, outputFile, scan_copyrights, scan_licenses, 
                            scan_emails, scan_urls, min_score):
    """
    Sequential processing function with proper termination handling
    """
    global manager, shared_data, termination_requested, termination_event
    
    print("Starting sequential processing with enhanced termination handling", flush=True)
    
    termination_requested = False
    termination_event.clear()
    
    with open(file_location, "r") as locations:
        file_count = sum(1 for line in locations)
    
    setup_heartbeat_monitoring(file_count)
    
    try:
        manager = Manager()
        shared_data = manager.dict()
        shared_data['terminate'] = False
    except Exception as e:
        print(f"Warning: Could not create shared manager: {e}")
        shared_data = None
    
    try:
        with open(file_location, "r") as locations:
            with open(outputFile, "w") as json_file:
                json_file.write('[')
                first_iteration = True
                
                for line_number, line in enumerate(locations, 1):
                    if is_termination_requested():
                        print(f"Termination requested at file {line_number}, stopping sequential processing...", flush=True)
                        break
                    
                    print(f"Processing file {line_number}/{file_count}: {line.strip()[:50]}...", flush=True)
                    
                    try:
                        args = (line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score, parent_pid)
                        result = scan_single_file(args)

                        if result is None:
                            if is_termination_requested():
                                print("File processing terminated", flush=True)
                                break
                            continue

                        if not first_iteration: 
                            json_file.write(',\n')  
                        else:
                            first_iteration = False

                        json.dump(result, json_file)
                        json_file.flush() 

                        if is_termination_requested():
                            print("Termination requested after file write", flush=True)
                            break

                    except KeyboardInterrupt:
                        print("\nKeyboard interrupt in file processing loop", flush=True)
                        termination_requested = True
                        termination_event.set()
                        break
                    except Exception as e:
                        print(f"An error occurred for file '{line.strip()}': {e}")
                        if is_termination_requested():
                            break
                        continue
                
                json_file.write('\n]')
                json_file.flush()
                print("Sequential processing completed", flush=True)
    
    except KeyboardInterrupt:
        print("\nSequential processing interrupted, cleaning up...")
        termination_requested = True
        termination_event.set()
        if shared_data:
            shared_data['terminate'] = True
        sys.exit(130)
    
    finally:
        if manager:
            try:
                manager.shutdown()
            except:
                pass
        cleanup_heartbeat_monitoring()

def process_files_parallel(file_location, outputFile, scan_copyrights, scan_licenses, 
                          scan_emails, scan_urls, min_score, num_processes=SCANCODE_PARALLEL):
    """Process files in parallel with robust worker management"""
    global active_pool, manager, shared_data
    
    with open(file_location, "r") as locations:
        file_lines = locations.readlines()
    
    optimal_processes, memory_per_process = calculate_optimal_processes(
        num_processes, 
        SCANCODE_MIN_MEMORY_PER_PROCESS
    )
    
    setup_heartbeat_monitoring(len(file_lines))
    
    scan_args = [(line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score, parent_pid) 
                 for line in file_lines]
    
    pool = None
    
    try:
        manager = Manager()
        shared_data = manager.dict()
        shared_data['terminate'] = False
        
        pool = Pool(
            processes=optimal_processes,
            initializer=init_worker_process,
            initargs=(parent_pid, shared_data),
            maxtasksperchild=SCANCODE_MAX_TASKS
        )
        
        active_pool = pool
        time.sleep(0.5)
        
        worker_pids = [v for k, v in shared_data.items() if k.startswith('worker_')]
        print(f"Registered workers: {worker_pids}", flush=True)
        
        chunk_size = max(1, len(scan_args) // (optimal_processes * 4))
        
        with open(outputFile, "w") as json_file:
            json_file.write('[')
            first_iteration = True
            
            for i in range(0, len(scan_args), chunk_size):
                if shared_data.get('terminate', False):
                    print("Termination flag set, stopping...", flush=True)
                    break
                
                chunk = scan_args[i:i + chunk_size]
                
                try:
                    chunk_results = pool.map(scan_single_file, chunk)
                    
                    for result in chunk_results:
                        if result:
                            if not first_iteration:
                                json_file.write(',\n')
                            else:
                                first_iteration = False
                            
                            json.dump(result, json_file)
                            json_file.flush()
                            
                except Exception as e:
                    print(f"Error in chunk: {e}", flush=True)
                    continue
            
            json_file.write('\n]')
    
    except KeyboardInterrupt:
        print("\nInterrupted, cleaning up...")
        if shared_data:
            shared_data['terminate'] = True
        sys.exit(130)
    
    except Exception as e:
        print(f"Fatal error: {e}", flush=True)
        if shared_data:
            shared_data['terminate'] = True
    
    finally:
        active_pool = None
        
        if pool:
            try:
                pool.terminate()
                pool.join(timeout=2)
            except:
                pass
        
        if shared_data:
            for key, pid in list(shared_data.items()):
                if key.startswith('worker_'):
                    try:
                        os.kill(pid, signal.SIGKILL)
                    except:
                        pass
        
        if manager:
            manager.shutdown()
        
        cleanup_heartbeat_monitoring()

def process_files(file_location, outputFile, scan_copyrights, scan_licenses, 
                  scan_emails, scan_urls, min_score):
    """Main entry point - decides between parallel and sequential processing"""
    if SCANCODE_PARALLEL > 1:
        print(f"Requesting parallel processing with {SCANCODE_PARALLEL} processes")
        process_files_parallel(file_location, outputFile, scan_copyrights, 
                              scan_licenses, scan_emails, scan_urls, min_score, SCANCODE_PARALLEL)
    else:
        print("Processing files sequentially")
        process_files_sequential(file_location, outputFile, scan_copyrights, 
                                scan_licenses, scan_emails, scan_urls, min_score)

if __name__ == "__main__":
  signal.signal(signal.SIGTERM, cleanup_handler)
  signal.signal(signal.SIGINT, cleanup_handler)
  signal.signal(signal.SIGHUP, cleanup_handler)
  
  parser = argparse.ArgumentParser(description="Process a file specified by its location.")
  parser.add_argument("-c", "--scan-copyrights", action="store_true", help="Scan for copyrights")
  parser.add_argument("-l", "--scan-licenses", action="store_true", help="Scan for licenses")
  parser.add_argument("-e", "--scan-emails", action="store_true", help="Scan for emails")
  parser.add_argument("-u", "--scan-urls", action="store_true", help="Scan for urls")
  parser.add_argument("-m", "--min-score", dest="min_score", type=int, default=0, help="Minimum score for a license to be included in the results")
  parser.add_argument("--parallel", type=int, default=1, help="Number of parallel processes (will be adjusted based on available memory)")
  parser.add_argument("--nice-level", type=int, default=10, help="Process nice level (0-19)")
  parser.add_argument("--max-tasks", type=int, default=1000, help="Max tasks per worker process")
  parser.add_argument("--heartbeat-interval", type=int, default=60, help="Heartbeat interval in seconds")
  parser.add_argument('file_location', type=str, help='Path to the file you want to process')
  parser.add_argument('outputFile', type=str, help='Path to the file you want save results to')

  args = parser.parse_args()
  scan_copyrights = args.scan_copyrights
  scan_licenses = args.scan_licenses
  scan_emails = args.scan_emails
  scan_urls = args.scan_urls
  min_score = args.min_score
  file_location = args.file_location
  outputFile = args.outputFile
  
  SCANCODE_PARALLEL = args.parallel
  SCANCODE_NICE = args.nice_level
  SCANCODE_MIN_MEMORY_PER_PROCESS = 1024
  SCANCODE_MAX_TASKS = args.max_tasks
  SCANCODE_HEARTBEAT_INTERVAL = args.heartbeat_interval

  try:
    process_files(file_location, outputFile, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score)
    print("Scan completed successfully", flush=True)
  except SystemExit:
    raise
  except Exception as e:
    print(f"Fatal error: {e}", flush=True)
    sys.exit(1)