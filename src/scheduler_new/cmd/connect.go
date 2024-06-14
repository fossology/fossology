package cmd

import (
	"os"
	"net"
	"fmt"
	"time"
	"bufio"
	"strings"
)

type config struct {
	host string
	port int
}

func (sh *config) SetHost() {
    var err error
    sh.host, err = os.Hostname()
    if err != nil {
        fmt.Printf("Error retrieving system hostname: %v\n", err)
        return
    }
}

func (sp *config) SetPort() {
    listener, err := net.Listen("tcp", "localhost:0")
    if err != nil {
        fmt.Printf("Error retrieving port: %v\n", err)
        return
    }
    defer listener.Close()

    sp.port = listener.Addr().(*net.TCPAddr).Port
}

func scheduler(host string, port int) net.Conn {
	conn, err := net.Dial("tcp",fmt.Sprintf("%s:%d", host, port))
	if err != nil {
		fmt.Printf("Error connecting to %s on port %s: %v\n", host, port, err)
		os.Exit(1)
	}
	return conn
}

func handleCommands(conn net.Conn) {
	reader := bufio.NewReader(os.Stdin)
	fmt.Println("Enter commands (type 'exit' to quit):")
	for {
		fmt.Print(">> ")
		input, _ := reader.ReadString('\n')
		input = strings.TrimSpace(input)

		if input == "exit" {
			break
		}

		_, err := conn.Write([]byte(input + "\n"))
		if err != nil {
			fmt.Printf("Failed to send command: %v\n", err)
			continue
		}

		response := make([]byte, 1024)
		n, err := conn.Read(response)
		if err != nil {
			fmt.Printf("Failed to read response: %v\n", err)
			continue
		}

		fmt.Println("Response:", string(response[:n]))
	}
}

func connect(){
	server := &config{}
	server.SetHost()
	server.SetPort()
	listener, err := net.Listen("tcp", fmt.Sprintf(":%d", server.port))
    if err != nil {
        fmt.Printf("Error listening: %v\n", err)
        return
    }
    defer listener.Close()
	conn, err := listener.Accept()
    if err != nil {
        fmt.Printf("Error accepting connection: %v\n", err)
        return
    }
	time.Sleep(1 * time.Second)
	conn = scheduler(server.host, server.port)
	handleCommands(conn)
	conn.Close()
}

