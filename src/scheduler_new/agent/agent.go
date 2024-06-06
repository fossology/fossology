package agent

import (
	"sync"
	"fmt"
	// "log"
	// "os"
)
//  extraction of pid *required



type metaAgent struct {
	name    string
	cmd     string
	max     int
	spc     int
	count   int
	// mutex inclusion: goroutine access variable at one time
	mutex   sync.Mutex
}

type agentinfo struct {
	pid        int
	host       *host
	owner      *job
	status     agentStatus
	// error?
	agenttype  *agentType
	mutex      sync.Mutex
}

// managing agents & thier pid
type scheduler struct {
	agents map[int]*agentinfo  
	jobList []*job
}

type agentType struct {
	name  string
	valid bool
}

type host struct {
	name string
}

type job struct {
	id int
}

type agentStatus int

const (
	// states of an agents
	AG_SPAWNED agentStatus = iota
	AG_RUNNING
	AG_PAUSED
)

// Initialisation of agent
func agentInit(scheduler *scheduler, host *host, owner *job) *agentinfo {
	ag := &agentinfo{
		host: host,
		owner: owner,
		status: AG_SPAWNED,
	}
	scheduler.agents[ag.pid] = ag
	return ag
}

// Termination of agent 
func agentDestroy(ag *agentinfo) {
	delete(ag.owner.scheduler.agents, ag.pid)
}

// Creation of Event
func agentCreateEvent(scheduler *scheduler, ag *AgentInfo) {
	fmt.Println("Agent successfully spawned")
	ag.status = AG_SPAWNED
}

// Termination of an Event
func agentDeathEvent(scheduler *scheduler, pid int) {
	if ag, ok := scheduler.agents[pid]; ok {
		fmt.Printf("Agent %d has died\n", pid)
		agentDestroy(ag)
	}
}

// Ready State of an Event
func agentReadyEvent(scheduler *scheduler, ag *agentinfo) {
	fmt.Println("Agent is ready and running")
	ag.status = AG_RUNNING
}

func agent() {
	scheduler := &scheduler{agents: make(map[int]*agentinfo)}
	host := &host{name: "localhost"}
	job := &job{id: 1}

	ag := agentInit(scheduler, host, job)
	agentCreateEvent(scheduler, ag)
	agentReadyEvent(scheduler, ag)
	agentDeathEvent(scheduler, ag.pid)
}
