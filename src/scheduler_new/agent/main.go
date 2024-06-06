package agent

import (
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"
	// "time"
)

type TaskManager struct {
	Daemon       bool
	LogDir       string
	SysConfigDir string
}

func (tm *TaskManager) Initialize() {
	fmt.Println("Task Manager initializing...")
	// logging, database, email settings 
}

func (tm *TaskManager) StartEventLoop() {
	fmt.Println("Entering the event loop...")
	// Event related task 
}

func (tm *TaskManager) HandleSignals() {
	c := make(chan os.Signal, 1)
	signal.Notify(c, syscall.SIGINT, syscall.SIGTERM, syscall.SIGHUP, syscall.SIGQUIT)
	go func() {
		for sig := range c {
			fmt.Printf("Received %s, shutting down.\n", sig)
			os.Exit(0)
		}
	}()
}

func main() {
	daemon := flag.Bool("daemon", false, "Run task manager as a daemon")
	logDir := flag.String("logdir", "./logs", "Directory for log files")
	sysConfigDir := flag.String("sysconfigdir", "/etc/taskmanager", "Directory for system configuration")
	flag.Parse()

	taskManager := &TaskManager{
		Daemon:       *daemon,
		LogDir:       *logDir,
		SysConfigDir: *sysConfigDir,  
	}

	taskManager.Initialize()
	taskManager.HandleSignals()

	if taskManager.Daemon {
		fmt.Println("Running as a daemon...")
	}

	taskManager.StartEventLoop()

	// Wait for termination
	select {}
}
