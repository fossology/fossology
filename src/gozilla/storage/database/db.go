package database

import (
	"fmt"
	"time"

	"gorm.io/gorm"
	setting "aaditya-singh/gozilla/src/storage/config"
)

const (
	checkSchedulerTables = `
		SELECT column_name FROM information_schema.columns
		WHERE table_catalog = '%s'
		AND table_schema = 'public'
		AND table_name = %s;`

	uRLCheckout = `
		SELECT conf_value FROM sysconfig
		WHERE variablename = 'FOSSologyURL';`

	selectUploadFK = `
		SELECT job_upload_fk FROM job, jobqueue
		WHERE jq_job_fk = job_pk
		AND jq_pk = %d;`

	uploadCommon = `
		SELECT * FROM jobqueue
		LEFT JOIN job ON jq_job_fk = job_pk
		WHERE job.job_upload_fk = %d;`

	folderName = `
		SELECT folder_name, folder_pk FROM folder
		LEFT JOIN foldercontents ON folder_pk = foldercontents.parent_fk
		LEFT JOIN job ON child_id = job_upload_fk
		LEFT JOIN jobqueue ON jq_job_fk = job_pk
		WHERE jq_pk = %d;`

	parentFolderName = `
		SELECT folder_name, folder_pk FROM folder
		INNER JOIN foldercontents ON folder_pk=foldercontents.parent_fk
		WHERE child_id = %d AND foldercontents_mode = 1;`

	uploadName = `
		SELECT upload_filename FROM upload
		LEFT JOIN job ON upload_pk = job_upload_fk
		LEFT JOIN jobqueue ON jq_job_fk = job_pk
		WHERE jq_pk = %d;`

	uploadPK = `
		SELECT upload_fk, uploadtree_pk FROM uploadtree
		LEFT JOIN job ON upload_fk = job_upload_fk
		LEFT JOIN jobqueue ON jq_job_fk = job_pk
		WHERE parent IS NULL
		AND jq_pk = %d;`

	userEmail = `
		SELECT user_name, user_email, email_notify FROM users, upload
		WHERE user_pk = user_fk
		AND upload_pk = %d;`

	jobEmail = `
		SELECT user_name, user_email, email_notify FROM users, job, jobqueue
		WHERE user_pk = job_user_fk AND job_pk = jq_job_fk
		AND jq_pk = %d;`

	BasicCheckout = `
		SELECT jobqueue.* FROM jobqueue INNER JOIN job ON job_pk = jq_job_fk
		WHERE jq_starttime IS NULL AND jq_end_bits < 2
		AND NOT EXISTS(SELECT * FROM jobdepends, jobqueue jdep
		WHERE jdep_jq_fk=jobqueue.jq_pk
		AND jdep_jq_depends_fk=jdep.jq_pk
		AND NOT(jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2))
		ORDER BY job_priority DESC
		LIMIT 10;`

	jobInformation = `
		SELECT user_pk, job_priority, job_group_fk as group_pk FROM users
		LEFT JOIN job ON job_user_fk = user_pk
		WHERE job_pk = '%s';`

	jobStarted = `
		UPDATE jobqueue
		SET jq_starttime = now(),
		jq_schedinfo ='%s.%d',
		jq_endtext = 'Started'
		WHERE jq_pk = '%d';`

	jobComplete = `
		UPDATE jobqueue
		SET jq_endtime = now(),
		jq_end_bits = jq_end_bits | 1,
		jq_schedinfo = null,
		jq_endtext = 'Completed'
		WHERE jq_pk = '%d';`

	jobRestart = `
		UPDATE jobqueue
		SET jq_endtext = 'Restarted',
		jq_starttime = ( CASE
		WHEN jq_starttime = CAST('9999-12-31' AS timestamp with time zone)
		THEN null
		ELSE jq_starttime
		END )
		WHERE jq_pk = '%d';`

	jobFailed = `
		UPDATE jobqueue
		SET jq_endtime = now(),
		jq_end_bits = jq_end_bits | 2,
		jq_schedinfo = null,
		jq_endtext = '%s'
		WHERE jq_pk = '%d';`

	jobProcessed = `
		Update jobqueue
		SET jq_itemsprocessed = %d
		WHERE jq_pk = '%d';`

	jobPaused = `
		UPDATE jobqueue
		SET jq_endtext = 'Paused',
		jq_starttime = ( CASE
		WHEN jq_starttime IS NULL
		THEN CAST('9999-12-31' AS timestamp with time zone)
		ELSE jq_starttime
		END )
		WHERE jq_pk = '%d';`

	jobLog = `
		UPDATE jobqueue
		SET jq_log = '%s'
		WHERE jq_pk = '%d';`

	jobPriority = `
		UPDATE job
		SET job_priority = '%d'
		WHERE job_pk IN (
		SELECT jq_job_fk FROM jobqueue
		WHERE jq_pk = '%d');`

	jobRunnable = `
		SELECT * FROM jobqueue
		WHERE jq_starttime IS NULL AND jq_end_bits < 2
		AND NOT EXISTS(SELECT * FROM jobdepends, jobqueue jdep
		WHERE jdep_jq_fk=jobqueue.jq_pk
		AND jdep_jq_depends_fk=jdep.jq_pk
		AND NOT(jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2))
		AND jq_job_fk = (SELECT jq_job_fk FROM jobqueue queue WHERE queue.jq_pk = %d);`

	jobEndBits = `
		SELECT jq_pk, jq_end_bits FROM jobqueue
		WHERE jq_job_fk = (
		SELECT jq_job_fk FROM jobqueue
		WHERE jq_pk = %d
		);`

	jobReset = `
		UPDATE jobqueue
		SET jq_starttime=null,
		jq_endtext=null,
		jq_schedinfo=null
		WHERE jq_endtime is NULL;`

	jobInfo = `
		SELECT * FROM jobqueue
		WHERE jq_job_fk = (
		SELECT jq_job_fk FROM jobqueue
		WHERE jq_pk = %d
		);`

	dependentQueue = `SELECT jq.*
        FROM jobqueue jq
        JOIN jobdepends jd ON jq.jq_pk = jd.jdep_jq_fk
        ORDER BY jq.priority;`

	independentQueue = `SELECT jq.*
        FROM jobqueue jq
        WHERE jq.jq_pk NOT IN (SELECT jdep_jq_fk FROM jobdepends)
        ORDER BY jq.priority;`

	smtpConfig = `
		SELECT conf_value, variablename FROM sysconfig
		WHERE variablename LIKE 'SMTP%';`
)

// interface to run sql queries
type DBQuery interface {
	IndependentQueue() ([]Job, error)
	DependentQueue() ([]Job, error)
	JobStatus(jobQueueID int, status string, schedulerInfo string) error
	GetFolder(jobQueueID int) (string, error)
	CheckJobStatus(jobQueueID int) (int, error)
	CheckTables(tableName string) error
	URLCheckout() (string, error)
	SelectUploadFK(jobQueueID int) (int, error)
	UploadCommon(uploadID int) ([]Job, error)
	JobSQLInformation(jobID string) (int, int, int, error)
	JobSQLAnyRunnable(jobQueueID int) ([]Job, error)
	JobSQLJobEndBits(jobQueueID int) ([]Job, error)
	JobSQLResetQueue() error
	JobSQLLog(jobQueueID int, log string) error
	JobSQLPriority(jobQueueID int, priority int) error
	GetJob(jobQueueID int) (*Job, error)
	GetSMTPConfig() (map[string]string, error)
	GetUploadDetails(uploadID int) (string, string, bool, error)
	GetJobDetails(jobQueueID int) (string, string, bool, error)
}

// Service for execution of database
type DBService struct {
	DB *gorm.DB
}

func Open(dsn string) (service *DBService, err error) {
	db, err := setting.ConnectDB(dsn)
	if err != nil {
		return nil, err
	}
	return &DBService{DB: db}, nil
}

func (service *DBService) Close() error {
	return setting.DisconnectDB(service.DB)
}

type Job struct {
	JobPK          int       `gorm:"column:jq_pk"`
	GroupFK        int       `gorm:"column:group_fk"`
	Assignee       int       `gorm:"column:assignee"`
	StatusFK       int       `gorm:"column:status_fk"`
	StatusComment  string    `gorm:"column:status_comment"`
	Priority       int       `gorm:"column:priority"`
	JobName        string    `gorm:"column:job_name"`
	JobQueued      time.Time `gorm:"column:job_queued"`
	JobUploadFK    int       `gorm:"column:job_upload_fk"`
	JobUserFK      int       `gorm:"column:job_user_fk"`
	JobGroupFK     int       `gorm:"column:job_group_fk"`
	JobEmailNotify bool      `gorm:"column:job_email_notify"`
}

// Extract dependent Job Queue
func (service *DBService) DependentQueue() ([]Job, error) {
	var jobs []Job
	err := service.DB.Raw(dependentQueue).Scan(&jobs).Error
	return jobs, err
}

// Extract independent Job Queue
func (service *DBService) IndependentQueue() ([]Job, error) {
	var jobs []Job
	err := service.DB.Raw(independentQueue).Scan(&jobs).Error
	return jobs, err
}

// Check job status
func (service *DBService) CheckJobStatus(jobQueueID int) (int, error) {
	var ret int
	var jobEndBits int
	stmt := fmt.Sprintf(jobEndBits, jobQueueID)
	err := service.DB.Raw(stmt).Row().Scan(&jobEndBits)
	if err != nil {
		return 0, err
	}

	if jobEndBits&(1<<1) != 0 {
		ret = 2 // job has failed
	} else {
		stmt = fmt.Sprintf(jobRunnable, jobQueueID)
		rows, err := service.DB.Raw(stmt).Rows()
		if err != nil {
			return 0, err
		}
		defer rows.Close()
		if rows.Next() {
			ret = 0 // job is not finished
		} else {
			ret = 1 // job has finished
		}
	}
	return ret, nil
}

// Update job queue
func (service *DBService) JobQueue(jobQueueID int, status string, schedulerInfo string) error {
	var stmt string
	switch status {
	case "started":
		stmt = fmt.Sprintf(jobStarted, schedulerInfo, jobQueueID)
	case "completed":
		stmt = fmt.Sprintf(jobComplete, jobQueueID)
	case "failed":
		stmt = fmt.Sprintf(jobFailed, "Job has failed", jobQueueID)
	case "paused":
		stmt = fmt.Sprintf(jobPaused, jobQueueID)
	default:
		return fmt.Errorf("invalid status: %s", status)
	}
	
	// Execute the status update
	if err := service.DB.Exec(stmt).Error; err != nil {
		return err
	}

	// Check the updated job status
	updatedStatus, err := service.CheckJobStatus(jobQueueID)
	if err != nil {
		return err
	}

	switch updatedStatus {
	case 0:
		fmt.Println("Job is not finished")
	case 1:
		fmt.Println("Job has finished")
	case 2:
		fmt.Println("Job has failed")
	}

	return nil
}


// Fetching folder name
func (service *DBService) GetFolder(jobQueueID int) (string, error) {
	var folderName string
	err := service.DB.Raw(folderName, jobQueueID).Scan(&folderName).Error
	return folderName, err
}

// Check tables in the database
func (service *DBService) CheckTables(tableName string) error {
	stmt := fmt.Sprintf(checkSchedulerTables, "fossology", tableName)
	var columns []string
	err := service.DB.Raw(stmt).Scan(&columns).Error
	if err != nil {
		return err
	}
	for _, column := range columns {
		fmt.Println("Column:", column)
	}
	return nil
}

// Get URL configuration
func (service *DBService) URLCheckout() (string, error) {
	var url string
	err := service.DB.Raw(url).Scan(&url).Error
	return url, err
}

// Get upload foreign key for a given job queue ID
func (service *DBService) SelectUploadFK(jobQueueID int) (int, error) {
	var uploadFK int
	stmt := fmt.Sprintf(selectUploadFK, jobQueueID)
	err := service.DB.Raw(stmt).Scan(&uploadFK).Error
	return uploadFK, err
}

// Get upload common information for a given upload ID
func (service *DBService) UploadCommon(uploadID int) ([]Job, error) {
	var jobs []Job
	stmt := fmt.Sprintf(uploadCommon, uploadID)
	err := service.DB.Raw(stmt).Scan(&jobs).Error
	return jobs, err
}

// Get job SQL information for a given job ID
func (service *DBService) JobSQLInformation(jobID string) (int, int, int, error) {
	var result struct {
		UserPK      int `gorm:"column:user_pk"`
		JobPriority int `gorm:"column:job_priority"`
		GroupPK     int `gorm:"column:group_pk"`
	}
	stmt := fmt.Sprintf(jobInfo, jobID)
	err := service.DB.Raw(stmt).Scan(&result).Error
	return result.UserPK, result.JobPriority, result.GroupPK, err
}


// Get any runnable jobs for a given job queue ID
func (service *DBService) JobSQLAnyRunnable(jobQueueID int) ([]Job, error) {
	var jobs []Job
	stmt := fmt.Sprintf(jobRunnable, jobQueueID)
	err := service.DB.Raw(stmt).Scan(&jobs).Error
	return jobs, err
}

// Get job end bits for a given job queue ID
func (service *DBService) JobSQLJobEndBits(jobQueueID int) ([]Job, error) {
	var jobs []Job
	stmt := fmt.Sprintf(jobEndBits, jobQueueID)
	err := service.DB.Raw(stmt).Scan(&jobs).Error
	return jobs, err
}

// Reset job queue
func (service *DBService) JobSQLResetQueue() error {
	err := service.DB.Exec(jobReset).Error
	return err
}

// Log job information 
func (service *DBService) JobSQLLog(jobQueueID int, log string) error {
	stmt := fmt.Sprintf(jobLog, log, jobQueueID)
	err := service.DB.Exec(stmt).Error
	return err
}

// changing job priority
func (service *DBService) JobSQLPriority(jobQueueID int, priority int) error {
	stmt := fmt.Sprintf(jobPriority, priority, jobQueueID)
	err := service.DB.Exec(stmt).Error
	return err
}

// Fetch a job by jobQueueID
func (service *DBService) GetJob(jobQueueID int) (*Job, error) {
	var job Job
	err := service.DB.Raw("SELECT * FROM jobqueue WHERE jq_pk = ?", jobQueueID).Scan(&job).Error
	if err != nil {
		return nil, err
	}
	return &job, nil
}

// configuring smtp
func (service *DBService) GetSMTPConfig() (map[string]string, error) {
	var smtpConfigs []struct {
		ConfValue    string `gorm:"column:conf_value"`
		ConfInfo string `gorm:"column:variablename"`
	}
	err := service.DB.Raw(smtpConfig).Scan(&smtpConfigs).Error
	if err != nil {
		return nil, err
	}

	config := make(map[string]string)
	for _, smtpConfig := range smtpConfigs {
		config[smtpConfig.ConfInfo] = smtpConfig.ConfValue
	}
	return config, nil
}

// Fetch email details from upload
func (service *DBService) GetUploadDetails(uploadID int) (string, string, bool, error) {
	var emailInfo struct {
		UserName    string `gorm:"column:user_name"`
		UserEmail   string `gorm:"column:user_email"`
		EmailNotify bool   `gorm:"column:email_notify"`
	}
	err := service.DB.Raw(userEmail, uploadID).Scan(&emailInfo).Error
	if err != nil {
		return "", "", false, err
	}
	return emailInfo.UserEmail, emailInfo.UserName, emailInfo.EmailNotify, nil
}

// Fetch email details from jobqueue
func (service *DBService) GetJobDetails(jobQueueID int) (string, string, bool, error) {
	// Check if the job status is "completed"
	jobStatus, err := service.CheckJobStatus(jobQueueID)
	if err != nil {
		return "", "", false, err
	}

	// Only proceed if the job status is "completed"
	if jobStatus == 1 {
		var emailInfo struct {
			UserName    string `gorm:"column:user_name"`
			UserEmail   string `gorm:"column:user_email"`
			EmailNotify bool   `gorm:"column:email_notify"`
		}
		err := service.DB.Raw(jobEmail, jobQueueID).Scan(&emailInfo).Error
		if err != nil {
			return "", "", false, err
		}
		return emailInfo.UserEmail, emailInfo.UserName, emailInfo.EmailNotify, nil
	}

	return "", "", false, fmt.Errorf("job is not completed")
}