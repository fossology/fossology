package agent

import (
	"bufio"
	"fmt"
	"net"
	"os"
	"strings"
)

func connectToScheduler(host, port string) net.Conn {
	conn, err := net.Dial("tcp", host+":"+port)
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

func cli() {
	host := "localhost" 
	// Dynamic allocation of port 
	port := ""     

	conn := connectToScheduler(host, port)
	defer conn.Close()

	handleCommands(conn)
}
