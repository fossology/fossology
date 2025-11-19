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
import logging
from multiprocessing import Pool, Value, Manager
import threading
import psutil
import queue
import select

script_directory = os.path.dirname(os.path.abspath(__file__))
os.environ["SCANCODE_CACHE"] = os.path.join(script_directory, '.cache')

from scancode import api

SCANCODE_PARALLEL = 1
SCANCODE_NICE = 10
SCANCODE_MIN_MEMORY_PER_PROCESS = 1024
SCANCODE_MAX_TASKS = 1000
HEARTBEAT_INTERVAL = 60

BYTES_TO_MB = 1024 * 1024 
KB_TO_MB = 1024 
USABLE_MEMORY_RATIO = 0.8  
DEFAULT_FALLBACK_MEMORY_MB = 2048  
TERMINATION_CHECK_INTERVAL_MS = 50  
DEFAULT_SCAN_TIMEOUT_SECONDS = 15  
STDIN_POLL_TIMEOUT_SECONDS = 0.5  
WORKER_WATCHDOG_INTERVAL_SECONDS = 2  
POOL_STARTUP_DELAY_SECONDS = 0.5  

logger = logging.getLogger('scancode_processor')
log_file_handler = None 

def setup_logging(log_file_path=None, verbose=False):
    """Setup comprehensive logging for the scancode agent"""
    global logger, log_file_handler
    
    for handler in logger.handlers[:]:
        logger.removeHandler(handler)
        handler.close()
    
    log_level = logging.DEBUG if verbose else logging.INFO
    logger.setLevel(log_level)
    
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setLevel(logging.INFO)
    console_formatter = logging.Formatter('%(levelname)s: %(message)s')
    console_handler.setFormatter(console_formatter)
    logger.addHandler(console_handler)
    
    if log_file_path:
        try:
            log_file_handler = logging.FileHandler(log_file_path, mode='a')
            log_file_handler.setLevel(logging.DEBUG) 
            file_formatter = logging.Formatter(
                '%(asctime)s - %(name)s - %(levelname)s - %(funcName)s:%(lineno)d - %(message)s'
            )
            log_file_handler.setFormatter(file_formatter)
            logger.addHandler(log_file_handler)
            logger.info(f"Logging to file: {log_file_path}")
        except Exception as e:
            logger.warning(f"Could not setup file logging to {log_file_path}: {e}")
    
    logger.propagate = False 
    return logger

files_processed = Value('i', 0)
files_total = Value('i', 0)
heartbeat_active = False

active_pool = None
parent_pid = os.getpid()
manager = None
shared_data = None
termination_requested = False
termination_event = threading.Event()
stdin_closed = False  
stdin_monitor_thread = None 

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

def monitor_stdin():
    """
    Monitor stdin for closure
    This runs in a separate thread which sets termination flags when stdin closes
    """
    global stdin_closed, termination_requested, termination_event, shared_data
    
    try:
        while True:
            if sys.stdin.closed:
                stdin_closed = True
                break
                
            ready, _, _ = select.select([sys.stdin], [], [], STDIN_POLL_TIMEOUT_SECONDS)
            
            if ready:
                try:
                    line = sys.stdin.readline()
                    if not line:  
                        stdin_closed = True
                        break
                except:
                    stdin_closed = True
                    break
            
            if termination_requested or termination_event.is_set():
                break
    
    except Exception as e:
        logger.error(f"Stdin monitor error: {e}")
        stdin_closed = True
    
    finally:
        if stdin_closed:
            logger.critical("STDIN CLOSED")
            termination_requested = True
            termination_event.set()
            if shared_data:
                shared_data['terminate'] = True

def start_stdin_monitor():
    """Start the stdin monitoring thread"""
    global stdin_monitor_thread
    
    stdin_monitor_thread = threading.Thread(target=monitor_stdin, daemon=True)
    stdin_monitor_thread.start()
    logger.info("Started stdin monitor")

def get_available_memory():
    """Get available system memory in MB"""
    try:
        mem = psutil.virtual_memory()
        available_mb = mem.available / BYTES_TO_MB
        return int(available_mb)
    except Exception:
        try:
            with open('/proc/meminfo', 'r') as f:
                for line in f:
                    if line.startswith('MemAvailable:'):
                        return int(line.split()[1]) // KB_TO_MB
        except:
            return DEFAULT_FALLBACK_MEMORY_MB

def calculate_optimal_processes(requested_processes, min_memory_per_process=512):
    """Calculate optimal number of processes based on available memory"""
    available_memory = get_available_memory()
    usable_memory = int(available_memory * USABLE_MEMORY_RATIO)
    max_processes_by_memory = max(1, usable_memory // min_memory_per_process)
    optimal_processes = min(requested_processes, max_processes_by_memory)
    memory_per_process = usable_memory // optimal_processes
    
    logger.info(f"Memory Analysis:")
    logger.info(f"  Available system memory: {available_memory} MB")
    logger.info(f"  Usable memory (80%): {usable_memory} MB")
    logger.info(f"  Requested processes: {requested_processes}")
    logger.info(f"  Optimal processes: {optimal_processes}")
    logger.info(f"  Memory per process: {memory_per_process} MB")
    
    if optimal_processes < requested_processes:
        logger.warning(f"  Reduced processes from {requested_processes} to {optimal_processes} due to memory constraints")
    
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
        time.sleep(WORKER_WATCHDOG_INTERVAL_SECONDS)
        if not check_parent_alive(parent_pid):
            logger.critical(f"Worker {worker_pid}: Parent died")
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
        signal.signal(signal.SIGHUP, lambda s, f: sys.exit(0))
        
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
        
        logger.info(f"Worker {my_pid} initialized (parent={parent_pid}, pgid={os.getpgrp()})")
        
    except Exception as e:
        logger.error(f"Worker init failed: {e}")
        sys.exit(1)

def cleanup_handler(signum, frame):
    """Handle termination signals"""
    global heartbeat_active, active_pool, shared_data, manager, termination_requested, termination_event, stdin_closed
    
    logger.critical(f"\nReceived signal {signum} ({'SIGHUP' if signum == signal.SIGHUP else 'SIGTERM' if signum == signal.SIGTERM else 'SIGINT' if signum == signal.SIGINT else str(signum)}), cleaning up...")
    
    heartbeat_active = False
    signal.alarm(0)
    termination_requested = True
    termination_event.set()
    stdin_closed = True
    
    if shared_data:
        shared_data['terminate'] = True
    
    if active_pool:
        try:
            logger.info("Terminating worker pool")
            active_pool.terminate()
            active_pool.join(timeout=1)
        except:
            pass
        
        if shared_data:
            for key, pid in list(shared_data.items()):
                if key.startswith('worker_'):
                    try:
                        logger.warning(f"Killing worker {pid}")
                        os.killpg(os.getpgid(pid), signal.SIGKILL)
                    except:
                        try:
                            os.kill(pid, signal.SIGKILL)
                        except:
                            pass
    
    if manager:
        try:
            manager.shutdown()
        except:
            pass
    
    sys.exit(0)

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
        
        logger.info(f"PROGRESS: {processed}/{total} files ({progress_percent:.1f}%){eta_str}{mem_str}")
        
        if not is_termination_requested():
            signal.alarm(HEARTBEAT_INTERVAL)

heartbeat_handler.start_time = None

def setup_heartbeat_monitoring(total_files):
    """Set up heartbeat monitoring"""
    global heartbeat_active
    
    with files_total.get_lock():
        files_total.value = total_files
    
    heartbeat_active = True
    heartbeat_handler.start_time = time.time()
    signal.signal(signal.SIGALRM, heartbeat_handler)
    signal.alarm(HEARTBEAT_INTERVAL)

def cleanup_heartbeat_monitoring():
    """Clean up heartbeat monitoring"""
    global heartbeat_active
    
    heartbeat_active = False
    signal.alarm(0)

def is_termination_requested():
    """Check if termination has been requested using multiple methods"""
    global termination_requested, shared_data, termination_event, stdin_closed
    
    if stdin_closed:
        return True
        
    if termination_event.is_set():
        return True
    
    if termination_requested:
        return True
    
    if shared_data and shared_data.get('terminate', False):
        return True
    
    return False

class ScanTimeoutError(Exception):
    """Raised when a scan operation times out"""
    def __init__(self, operation, filename, timeout_seconds):
        self.operation = operation
        self.filename = filename
        self.timeout_seconds = timeout_seconds
        super().__init__(f"Timeout scanning {operation} for {filename} after {timeout_seconds}s")

class TerminationRequestedError(Exception):
    """Raised when termination is requested during an operation"""
    def __init__(self, operation, filename=None):
        self.operation = operation
        self.filename = filename
        message = f"Termination requested during {operation}"
        if filename:
            message += f" for {filename}"
        super().__init__(message)

def run_with_timeout_and_termination_check(func, args, timeout=DEFAULT_SCAN_TIMEOUT_SECONDS, operation_name=None, filename=None):
    """
    Run a function with timeout and frequent termination checks.
    Raises specific exceptions for timeout and termination scenarios.
    """
    result_queue = queue.Queue()
    exception_queue = queue.Queue()
    
    if operation_name is None:
        operation_name = func.__name__.replace('get_', '').replace('api.', '')
    
    def target():
        try:
            result = func(*args)
            result_queue.put(result)
        except Exception as e:
            exception_queue.put(e)
    
    thread = threading.Thread(target=target)
    thread.daemon = True
    thread.start()
    
    check_interval = TERMINATION_CHECK_INTERVAL_MS / 1000.0 
    elapsed = 0.0
    
    while elapsed < timeout:
        thread.join(timeout=check_interval)
        
        if not thread.is_alive():
            break
            
        if is_termination_requested():
            raise TerminationRequestedError(operation_name, filename)
            
        elapsed += check_interval
    
    if thread.is_alive():
        raise ScanTimeoutError(operation_name, filename or "unknown", timeout)
    
    if not exception_queue.empty():
        raise exception_queue.get()
    
    if not result_queue.empty():
        return result_queue.get()

    raise RuntimeError(f"No result from {operation_name}")

def scan_single_file(args):
    """
    Processes a single file and returns the results.
    Uses exception-based error handling for cleaner code and better error reporting.
    """
    line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score, parent_pid = args
    
    if not check_parent_alive(parent_pid):
        logger.critical(f"Worker: Parent process died")
        sys.exit(1)
    
    filename = line.strip()
    result = {
        'file': filename,
        'licenses': [],
        'copyrights': [],
        'holders': [],
        'emails': [],
        'urls': []
    }

    try:
        if scan_copyrights:
            copyrights = run_with_timeout_and_termination_check(
                api.get_copyrights, 
                (result['file'],), 
                timeout=DEFAULT_SCAN_TIMEOUT_SECONDS,
                operation_name="copyrights",
                filename=filename
            )
            updated_copyrights, updated_holders = update_copyright(copyrights)
            result['copyrights'] = updated_copyrights
            result['holders'] = updated_holders

        if scan_licenses:
            licenses = run_with_timeout_and_termination_check(
                api.get_licenses, 
                (result['file'], True, min_score), 
                timeout=DEFAULT_SCAN_TIMEOUT_SECONDS,
                operation_name="licenses", 
                filename=filename
            )
            updated_licenses = update_license(licenses)
            result['licenses'] = updated_licenses

        if scan_emails:
            emails = run_with_timeout_and_termination_check(
                api.get_emails, 
                (result['file'],), 
                timeout=DEFAULT_SCAN_TIMEOUT_SECONDS,
                operation_name="emails",
                filename=filename
            )
            updated_emails = update_emails(emails)
            result['emails'] = updated_emails

        if scan_urls:
            urls = run_with_timeout_and_termination_check(
                api.get_urls, 
                (result['file'],), 
                timeout=DEFAULT_SCAN_TIMEOUT_SECONDS,
                operation_name="urls",
                filename=filename
            )
            updated_urls = update_urls(urls)
            result['urls'] = updated_urls

        with files_processed.get_lock():
            files_processed.value += 1
        
        return result

    except TerminationRequestedError as e:
        logger.debug(f"Termination requested: {e}")
        with files_processed.get_lock():
            files_processed.value += 1
        return None
    
    except ScanTimeoutError as e:
        logger.warning(f"Scan timeout: {e}")
        with files_processed.get_lock():
            files_processed.value += 1
        return result
    
    except Exception as e:
        logger.error(f"Scan error for file '{filename}': {e}")
        with files_processed.get_lock():
            files_processed.value += 1
        return result

def process_files_sequential(file_location, outputFile, scan_copyrights, scan_licenses, 
                            scan_emails, scan_urls, min_score):
    """
    Sequential processing function with FOSSology-compliant termination handling
    """
    global manager, shared_data, termination_requested, termination_event
    
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
        logger.warning(f"Warning: {e}")
        shared_data = None
    
    try:
        with open(file_location, "r") as locations:
            with open(outputFile, "w") as json_file:
                json_file.write('[')
                first_iteration = True
                
                for line_number, line in enumerate(locations, 1):
                    if is_termination_requested():
                        logger.warning(f"FOSSology termination at file {line_number}")
                        break
                    
                    try:
                        args = (line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score, parent_pid)
                        result = scan_single_file(args)

                        if result is None:
                            if is_termination_requested():
                                logger.info("File processing terminated")
                                break
                            continue

                        if not first_iteration: 
                            json_file.write(',\n')  
                        else:
                            first_iteration = False

                        json.dump(result, json_file)
                        json_file.flush() 

                        if is_termination_requested():
                            break

                    except KeyboardInterrupt:
                        logger.warning("\nKeyboard interrupt")
                        break
                    except Exception as e:
                        if not is_termination_requested():
                            logger.error(f"An error occurred'{line.strip()}': {e}")
                        if is_termination_requested():
                            break
                        continue
                
                json_file.write('\n]')
                json_file.flush()
                logger.info("Sequential processing completed")
    
    except KeyboardInterrupt:
        logger.warning("\nSequential processing interrupted")
        sys.exit(0)
    
    finally:
        if manager:
            try:
                manager.shutdown()
            except:
                pass
        cleanup_heartbeat_monitoring()

def process_files_parallel(file_location, outputFile, scan_copyrights, scan_licenses, 
                          scan_emails, scan_urls, min_score, num_processes=SCANCODE_PARALLEL):
    """Process files in parallel with FOSSology-compliant worker management"""
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
        time.sleep(POOL_STARTUP_DELAY_SECONDS)
        
        worker_pids = [v for k, v in shared_data.items() if k.startswith('worker_')]
        logger.info(f"Registered workers: {worker_pids}")
        
        chunk_size = max(1, len(scan_args) // (optimal_processes * 4))
        
        with open(outputFile, "w") as json_file:
            json_file.write('[')
            first_iteration = True
            
            for i in range(0, len(scan_args), chunk_size):
                if is_termination_requested():
                    logger.warning("FOSSology termination detected")
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
                        
                        if is_termination_requested():
                            break
                            
                except Exception as e:
                    if not is_termination_requested():
                        logger.error(f"Error in chunk: {e}")
                    if is_termination_requested():
                        break
                    continue
            
            json_file.write('\n]')
    
    except KeyboardInterrupt:
        logger.warning("\nParallel processing interrupted")
        if shared_data:
            shared_data['terminate'] = True
        sys.exit(0)
    
    except Exception as e:
        if not is_termination_requested():
            logger.error(f"Fatal error: {e}")
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
        logger.info(f"Requesting parallel processing with {SCANCODE_PARALLEL} processes")
        process_files_parallel(file_location, outputFile, scan_copyrights, 
                              scan_licenses, scan_emails, scan_urls, min_score, SCANCODE_PARALLEL)
    else:
        logger.info("Processing files sequentially")
        process_files_sequential(file_location, outputFile, scan_copyrights, 
                                scan_licenses, scan_emails, scan_urls, min_score)

if __name__ == "__main__":
  signal.signal(signal.SIGTERM, cleanup_handler)
  signal.signal(signal.SIGINT, cleanup_handler)
  signal.signal(signal.SIGHUP, cleanup_handler)  
  start_stdin_monitor()
  parser = argparse.ArgumentParser(description="Process a file specified by its location.")
  parser.add_argument("-c", "--scan-copyrights", action="store_true", help="Scan for copyrights")
  parser.add_argument("-l", "--scan-licenses", action="store_true", help="Scan for licenses")
  parser.add_argument("-e", "--scan-emails", action="store_true", help="Scan for emails")
  parser.add_argument("-u", "--scan-urls", action="store_true", help="Scan for urls")
  parser.add_argument("-m", "--min-score", dest="min_score", type=int, default=0, help="Minimum score for a license to be included in the results")
  parser.add_argument("--parallel", type=int, default=1, help="Number of parallel processes")
  parser.add_argument("--nice-level", type=int, default=10, help="Process nice level (0-19)")
  parser.add_argument("--max-tasks", type=int, default=1000, help="Max tasks per worker process")
  parser.add_argument("--heartbeat-interval", type=int, default=60, help="Heartbeat interval in seconds")
  parser.add_argument("--log-file", type=str, help="Path to log file for debugging output")
  parser.add_argument("--verbose", "-v", action="store_true", help="Enable verbose logging")
  parser.add_argument('file_location', type=str, help='Path to the file you want to process')
  parser.add_argument('outputFile', type=str, help='Path to the file you want save results to')

  args = parser.parse_args()

  setup_logging(args.log_file, args.verbose)

  scan_copyrights = args.scan_copyrights
  scan_licenses = args.scan_licenses
  scan_emails = args.scan_emails
  scan_urls = args.scan_urls
  min_score = args.min_score
  file_location = args.file_location
  outputFile = args.outputFile
        
  SCANCODE_PARALLEL = args.parallel if args.parallel else 1
  SCANCODE_NICE = args.nice_level if args.nice_level else 10
  SCANCODE_MIN_MEMORY_PER_PROCESS = 1024
  SCANCODE_MAX_TASKS = args.max_tasks if args.max_tasks else 1000
  HEARTBEAT_INTERVAL = args.heartbeat_interval if args.heartbeat_interval else 60

  logger.info(f"ScanCode Processor starting with:")
  logger.info(f"  Scan options: copyrights={scan_copyrights}, licenses={scan_licenses}, emails={scan_emails}, urls={scan_urls}")
  logger.info(f"  Parallel processes: {SCANCODE_PARALLEL}")

  process_files(file_location, outputFile, scan_copyrights, scan_licenses, 
                    scan_emails, scan_urls, min_score)
