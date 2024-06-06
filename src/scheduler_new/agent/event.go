package agent

import (
	"fmt"
	"sync"
)

type Event struct {
	function   func(*Scheduler, interface{})
	argument   interface{}
	name       string
	sourceName string
	sourceLine uint16
}

type EventLoop struct {
	queue       chan *Event
	terminated  bool
	mu          sync.Mutex
}

func NewEventLoop() *EventLoop {
	return &EventLoop{
		queue:      make(chan *Event, 10), // Example 
		terminated: false,
	}
}

func (el *EventLoop) Start() {
	go func() {
		for event := range el.queue {
			if el.terminated {
				break
			}
			event.function(nil, event.argument) 
			fmt.Println("Event processed:", event.name)
		}
	}()
}

func (el *EventLoop) AddEvent(event *Event) {
	el.queue <- event
}

func (el *EventLoop) Stop() {
	el.terminated = true
	close(el.queue)
}

func event() {
	eventLoop := NewEventLoop()
	eventLoop.Start()

	eventLoop.AddEvent(&Event{
		function: func(s *Scheduler, arg interface{}) {
			fmt.Println("Executing event with argument:", arg)
		},
		argument: "Sample Event",
		name:     "TestEvent",
	})

	eventLoop.Stop()
}
