package distributor

import (
	"fmt"
	
	store "aaditya-singh/gozilla/src/storage/database"
)

// handling queue, when it should be created.
func Queue(query store.DBQuery) {
	// handle independent jobs
	indepJobs, err := query.IndependentQueue()
	if err != nil {
		fmt.Printf("Error fetching independent job queue: %v\n", err)
	} else {
		fmt.Printf("Independent jobs: %+v\n", indepJobs)
	}

	// handle dependent Jobs
	depJobs, err := query.DependentQueue()
	if err != nil {
		fmt.Printf("Error fetching dependent job queue: %v\n", err)
	} else {
		fmt.Printf("Dependent jobs: %+v\n", depJobs)
	}
}
