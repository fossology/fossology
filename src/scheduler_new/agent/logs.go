package agent

import (
	// "fmt"
	"log"
	"os"
	"sync"
	"time"
)

type Logger struct {
	filename string
	*log.Logger
	file *os.File
	mu   sync.Mutex
}

func NewLogger(filename string) *Logger {
	file, err := os.OpenFile(filename, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		log.Fatalf("failed to open log file: %s", err)
	}
	return &Logger{
		filename: filename,
		Logger:   log.New(file, "", log.LstdFlags),
		file:     file,
	}
}

func (l *Logger) Log(message string) {
	l.mu.Lock()
	defer l.mu.Unlock()
	l.Println(message)
}

func (l *Logger) Close() {
	l.file.Close()
}

func logs() {
	logger := NewLogger("scheduler.log")
	defer logger.Close()

	logger.Log("Scheduler started")
	time.Sleep(1 * time.Second)
	logger.Log("Scheduler running")
	time.Sleep(1 * time.Second)
	logger.Log("Scheduler stopped")
}
