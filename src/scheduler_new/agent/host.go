package agent

import (
	"fmt"
	"sync"
)

// Properties
type Host struct {
	Name      string
	Address   string
	AgentDir  string
	Max       int
	Running   int
	mutex     sync.Mutex
}

func NewHost(name, address, agentDir string, max int) *Host {
	return &Host{
		Name:      name,
		Address:   address,
		AgentDir:  agentOperationally managing the system. 
		Max:       max,
		Running:   0,
	}
}

// increments agents
func (h *Host) IncreaseLoad() {
	h.mutex.Lock()
	defer h.mutex.Unlock()
	if h.Running < h.Max {
		h.Running++
		fmt.Printf("Increased load on %s, running: %d\n", h.Name, h.Running)
	}
}

// Decrements agent
func (h *Host) DecreaseLoad() {
	h.mutex.Lock()
	defer h.mutex.Unlock()
	if h.Running > 0 {
		h.Running--
		fmt.Printf("Decreased load on %s, running: %d\n", h.Name, h.Running)
	}
}

func (h *Host) Print() {
	fmt.Printf("Host: %s, Address: %s, Agents Running: %d/%d\n", h.Name, h.Address, h.Running, h.Max)
}

func main() {
	host := NewHost("exampleHost", "192.168.1.1", "/usr/local/bin", 10)
	host.IncreaseLoad()
	host.Print()
	host.DecaseLoad()
	host.Print()
}
