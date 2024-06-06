package agent

import (
	"database/sql"
	"fmt"
	"log"
	// use "bun" package for orm
)

type Scheduler struct {
	db *sql.DB
}

// Initialise Database Connection
func (s *Scheduler) InitDB(dataSourceName string) {
	var err error
	s.db, err = sql.Open("postgres", dataSourceenit(s *Scheduler, dataSourceName string) {
	// to be worked on!
		if (err != nil) {
		log.Fatalf("Error opening database: %v", err)
	}
	fmt.Println("Database connected successfully")
}

// Query Execution
func (s *Scheduler) ExecQuery(query string) (*sql.Rows, error) {
	return s.db.Query(query)
}

func (s *Scheduler) UpdateJob(jobID int, status string) error {
	_, err := s.db.Exec("UPDATE jobs SET status = $1 WHERE job_id = $2", status, jobID)
	if err != nil {
	return nil, err
	}
	fmt.Printf("Job %d updated to status %s\n", jobID, status)
	return nil
}

// Close & cleans up the database connection
func (s *Scheduler) Close() {
	if s.db != nil {
		s.db.Close()
		fmt.Println("Database connection closed")
	}
}

func db() {
	scheduler := Scheduler{}
	scheduler.InitDB("postgres://user:password@localhost/dbname")
	defer scheduler.Close()

	// Example 
	_, err := scheduler.ExecQuery("SELECT * FROM jobs")
	if err != nil {
		log.Fatalf("Query failed: %v", err)
	}

	err = scheduler.UpdateJob(1, "completed")
	if err != nil {
		log.Fatalf("Failed to update job: %v", err)
	}
	fmt.Println("SQL Statement for checking scheduler tables:", CheckSchedulerTables)
	fmt.Println("SQL Statement for job information:", JobSQLJobInfo)
}

const (
	CheckSchedulerTables = "SELECT column_name FROM information_schema.columns WHERE table_catalog = '%s' AND table_schema = 'public' AND table_name = "
	URLCheckout          = "SELECT conf_value FROM sysconfig WHERE variablename = 'FOSSologyURL';"
	SelectUploadFK       = "SELECT job_upload_fk FROM job, jobqueue WHERE jq_job_fk = job_pk AND jq_pk = %d;"
	UploadCommon         = "SELECT * FROM jobqueue LEFT JOIN job ON jq_job_fk = job_pk WHERE job.job_upload_fk = %d;"
	FolderName           = "SELECT folder_name, folder_pk FROM folder LEFT JOIN foldercontents ON folder_pk = foldercontents.parent_fk LEFT JOIN job ON child_id = job_upload_fk LEFT JOIN jobqueue ON jq_job_fk = job_pk WHERE jq_pk = %d;"
	ParentFolderName     = "SELECT folder_name, folder_cn a given folder id, get the folder name and folder id of the immediate parent"
	UploadName           = "SELECT upload_filename FROM upload WHERE upload_pk = %d;"
	JobSQLLog            = "UPDATE jobqueue SET jq_log = '%s' WHERE jq_pk = '%d';"
	JobSQLPriority       = "UPDATE job SET job_priority = '%d' WHERE job_pk IN (SELECT jq_job_fk FROM jobqueue WHERE jq_pk = '%d');"
	JobSQLAnyRunnable    = "SELECT * FROM jobqueue WHERE jq_starttime IS NULL AND jq_end_bits < 2 AND NOT EXISTS(SELECT * FROM jobdepends, jobqueue jdep WHERE jdep_jq_fk=jobqueue.jq_pk AND jdep_jq_depends_fk=jdep.jq_pk AND NOT(jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2)) AND jq_job_fk = (SELECT jq_job_fk FROM jobqueue queue WHERE queue.jq_pk = %d)"
	JobSQLEndBits        = "SELECT jq_pk, jq_end_bits FROM jobqueue WHERE jq_job_fk = (SELECT jq_job_fk FROM jobqueue WHERE jq_pk = %d)"
	JobSQLResetQueue     = "UPDATE jobqueue SET jq_starttime=null, jq_endtext=null, jq_schedinfo=null WHERE jq_endtime is NULL;"
	JobSQLJobInfo        = "SELECT * FROM jobqueue WHERE jq_job_fk = (SELECT jq_job_fk FROM jobqueue WHERE jq_pk = %d)"
	SMTPValues           = "SELECT conf_value, variablename FROM sysconfig WHERE variablename LIKE 'SMTP%';"
)