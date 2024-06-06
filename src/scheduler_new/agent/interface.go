package agent

import (
	"fmt"
	"net"
	"sync"
	"time"
)

type Interface struct {
	Connection net.Conn
	mutex      sync.Mutex
}

func NewInterface(address string) *Interface {
	conn, err := net.Dial("tcp", address)
	if err != nil {
		fmt.Printf("Failed to connect: %s\n", err)
		return nil
	}
	return &Interface{Connection: conn}
}

func (i *Interface) Listen() {
	defer i.Connection.Close()
	buffer := make([]byte, 1024)
	for {
		n, err := i.Connection.Read(buffer)
		if err != nil {
			fmt.Printf("Read error: %s\n", err)
			return
		}
		fmt.Printf("Received: %s\n", string(buffer[:n]))
	}
}

func (i *Interface) Close() {
	i.mutex.Lock()
	defer i.mutex.Unlock()
	if i.Connection != nil {
		i.Connection.Close()
		i.Connection = nil
		fmt.Println("Interface connection closed.")
	}
}

type agentScheduler struct {
	Interface *Interface
}

func (s *agentScheduler) InitializeInterface(address string) {
	s.Interface = NewInterface(address)
	go s.Interface.Listen()
}

func (s *agentScheduler) DestroyInterface() {
	if s.Interface != nil {
		s.Interface.Close()
		s.Interface = nil
	}
}

func intface() {
	scheduler := &agentScheduler{}
	// port can be dynamic
	scheduler.InitializeInterface("localhost:8080")
	time.Sleep(10 * time.Second) 
	scheduler.DestroyInterface()
}
