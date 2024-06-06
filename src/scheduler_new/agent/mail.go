package agent

import (
	"fmt"
	"strings"
)

// Information about single agent 
type AgentInfo struct {
	ID     int
	Agent  string
	Status bool 
}

func FormatEmailText(agents []AgentInfo, fossyURL string) string {
	var builder strings.Builder
	builder.WriteString("Agents run:\n")
	builder.WriteString("    Job ID =>      Agent Name =>     Status => Link\n")

	for _, agent := range agents {
		status := "COMPLETED"
		link := fmt.Sprintf("http://%s?mod=showjobs&job=%d", fossyURL, agent.ID)
		if !agent.Status {
			status = "FAILED"
			link = fmt.Sprintf("%10s => %s", status, link)
		}
		builder.WriteString(fmt.Sprintf("%10d => %15s => %s => %s\n", agent.ID, agent.Agent, status, link))
	}

	return builder.String()
}

func mail() {
	// Example 
	agents := []AgentInfo{
		{ID: 1, Agent: "Agent1", Status: true},
		{ID: 2, Agent: "Agent2", Status: false},
	}
	fossyURL := "example.com"

	emailContent := FormatEmailText(agents, fossyURL)
	fmt.Println(emailContent)
}
