package agent

import (
	"fmt"
	"sync"
)

type Job struct {
	ID       int
	Type     string
	Host     string
	Priority int
	Verbose  int
	Agents   []*Agent
	mutex    sync.Mutex
}


func NewJob(id int, jobType, host string, priority int) *Job {
	return &Job{
		ID:       id,
		Type:     jobType,
		Host:     host,
		Priority: priority,
		Agents:   []*Agent{},
	}
}

// add agent to a job
func (j *Job) AddAgent(agent *Agent) {
	j.mutex.Lock()
	defer j.mutex.Unlock()
	j.Agents = append(j.Agents, agent)
}

// remove agent from a job
func (j *Job) RemoveAgent(agent *Agent) {
	j.mutex.Lock()
	defer j.mutex.Unlock()
	for i, a := range j.Agents {
		if a == agent {
			j.Agents = append(j.Agents[:i], j.Agents[i+1:]...)
			break
		}
	}
}

// update verbose of a job
func (j *Job) UpdateVerbose(level int) {
	j.mutex.Lock()
	defer j.mutex.Unlock()
	j.Verbose = level
	fmt.Printf("Job %d verbose updated to %d\n", j.ID, level)
}

func (j *Job) PrintStatus() {
	fmt.Printf("Job ID: %d, Type: %s, Host: %s, Priority: %d, Agents: %d\n", j.ID, j.Type, j.Host, j.Priority, len(j.Agents))
}

type Agent struct {
	// Agent specific fields
}

func jobs() {
	job := NewJob(1, "ExampleJob", "localhost", 5)
	agent := &Agent{}
	job.AddAgent(agent)
	job.UpdateVerbose(3)
	job.PrintStatus()
	job.RemoveAgent(agent)
	job.PrintStatus()
}
