package data

/*
 * \file
 * \brief Package data for managing database services and interactions with job queues.
 *
 * This file contains the following:
 * 
 * - **DBService struct**: Manages database connections using GORM.
 * - **JobQueue struct**: Represents job queue information from the database.
 *
 * Functions:
 *
 * - **DescribeTable**: Describes a table's columns from the specified catalog and name.
 *   @param tableCatalog The catalog of the table.
 *   @param tableName The name of the table.
 *   @return A slice of column names and an error if the query fails.
 *
 * - **GetUrl**: Retrieves the system configuration URL.
 *   @return A URL string and an error if retrieval fails.
 *
 * - **GetUploadId**: Retrieves the upload ID associated with a specific job queue entry.
 *   @param jobqueueId The job queue primary key.
 *   @return The upload foreign key and an error if retrieval fails.
 *
 * - **GetUploadCommon**: Retrieves job queue entries associated with a given upload ID.
 *   @param uploadID The ID of the upload to retrieve job data for.
 *   @return A slice of JobQueue entries and an error if the query fails.
 *
 * - **GetUploadName**: Retrieves the name of the upload associated with a specific job queue.
 *   @param jobQueueID The job queue ID.
 *   @return The upload filename and an error if retrieval fails.
 *
 * - **JobLog**: Logs a message for a specific job queue.
 *   @param jobQueueID The ID of the job queue entry to log for.
 *   @param log The log message to record.
 *   @return An error if the update fails.
 *
 * - **JobPriority**: Updates the priority of a job based on the job queue ID.
 *   @param jobQueueID The job queue ID.
 *   @param priority The new priority to set.
 *   @return An error if the update fails.
 *
 * - **JobReset**: Resets job queue entries that are incomplete.
 *   @return An error if the reset operation fails.
 *
 * - **PauseJob**: Pauses a job based on the job queue ID.
 *   @param jobQueueID The
*/

import (
    "time"
    "strconv"

    "gorm.io/gorm"
    debug "Overwatch/logging"
)

type DBService struct {
    DB *gorm.DB
}

type JobQueue struct {
    JqPk            int       `gorm:"column:jq_pk"`
    JqJobFk         int       `gorm:"column:jq_job_fk"`
    JqType          string    `gorm:"column:jq_type"`
    JqArgs          string    `gorm:"column:jq_args"`
    JqStartTime     time.Time `gorm:"column:jq_starttime"`
    JqEndTime       time.Time `gorm:"column:jq_endtime"`
    JqEndText       string    `gorm:"column:jq_endtext"`
    JqEndBits       int16     `gorm:"column:jq_end_bits"`
    JqSchedInfo     string    `gorm:"column:jq_schedinfo"`
    JqItemProcessed int       `gorm:"column:jq_itemprocessed"`
    JqLog           string    `gorm:"column:jq_log"`
    JqRunOnPFile    string    `gorm:"column:jq_runonpfile"`
    JqHost          string    `gorm:"column:jq_host"`
    JqCmdArgs       string    `gorm:"column:jq_cmd_args"`
}


func (service *DBService) DescribeTable(tableCatalog, tableName string) ([]string, error) {
    var columns []string
    err := service.DB.Table("information_schema.columns").
        Select("column_name").
        Where(`table_catalog = ? AND table_schema ='public'AND table_name = ?`, tableCatalog, tableName).
        Pluck("column_name", &columns).Error
    if err != nil {
        debug.Error("Failed to describe table: " + err.Error())
    } else {
        debug.Info("Successfully retrieved table description for: " + tableName)
    }
    return columns, err
}

func (service *DBService) GetUrl() (string, error) {
    var url string
    err := service.DB.Table("sysconfig").
        Select("conf_value").
        Where(`variable_name = ?`, "fossologyURL").
        Pluck("conf_value", &url).Error
    if err != nil {
        debug.Error("Failed to get URL from sysconfig: " + err.Error())
    } else {
        debug.Info("Successfully retrieved URL: " + url)
    }
    return url, err
}

func (service *DBService) GetUploadId(jobqueueId int) (int, error) {
    var uploadFk int
    err := service.DB.Table("job").
        Select("job_upload_fk").
        Joins("Join jobqueue on job_pk = jq_job_fk").
        Where(`jq_pk = ?`, jobqueueId).
        Pluck("job_upload_fk", &uploadFk).Error
    if err != nil {
        debug.Error("Failed to get upload ID: " + err.Error())
    } else {
        debug.Info("Successfully retrieved upload ID: " + strconv.Itoa(uploadFk))
    }
    return uploadFk, err
}

func (service *DBService) GetUploadCommon(uploadID int) ([]JobQueue, error) {
    var jobs []JobQueue
    err := service.DB.Table("jobqueue").
        Select("*").
        Joins("LEFT JOIN job ON jq_job_fk = job_pk").
        Where("job.job_upload_fk = ?", uploadID).
        Find(&jobs).Error
    if err != nil {
        debug.Error("Failed to get upload common data: " + err.Error())
    } else {
        debug.Info("Successfully retrieved upload common data for upload ID: " + strconv.Itoa(uploadID))
    }
    return jobs, err
}

func (service *DBService) GetUploadName(jobQueueID int) (string, error) {
    var uploadName string
    err := service.DB.Table("upload").
        Select("upload_filename").
        Joins("LEFT JOIN job ON upload_pk = job_upload_fk").
        Joins("LEFT JOIN jobqueue ON jq_job_fk = job_pk").
        Where("jq_pk = ?", jobQueueID).
        Pluck("upload_filename", &uploadName).Error
    if err != nil {
        debug.Error("Failed to get upload name: " + err.Error())
    } else {
        debug.Info("Successfully retrieved upload name: " + uploadName)
    }
    return uploadName, err
}

func (service *DBService) GetUploadPK(jobQueueID int) (int, int, error) {
    var result struct {
        UploadFK     int `gorm:"column:upload_fk"`
        UploadTreePK int `gorm:"column:uploadtree_pk"`
    }
    err := service.DB.Table("uploadtree").
        Select("upload_fk, uploadtree_pk").
        Joins("LEFT JOIN job ON upload_fk = job_upload_fk").
        Joins("LEFT JOIN jobqueue ON jq_job_fk = job_pk").
        Where("parent IS NULL AND jq_pk = ?", jobQueueID).
        Scan(&result).Error
    if err != nil {
        debug.Error("Failed to get upload primary key: " + err.Error())
    } else {
        debug.Info("Successfully retrieved upload primary key for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return result.UploadFK, result.UploadTreePK, err
}

func (service *DBService) GetFolderName(jobQueueID int) (string, int, error) {
    var result struct {
        FolderName string `gorm:"column:folder_name"`
        FolderPK   int    `gorm:"column:folder_pk"`
    }
    err := service.DB.Table("folder").
        Select("folder_name, folder_pk").
        Joins("LEFT JOIN foldercontents ON folder_pk = foldercontents.parent_fk").
        Joins("LEFT JOIN job ON child_id = job_upload_fk").
        Joins("LEFT JOIN jobqueue ON jq_job_fk = job_pk").
        Where("jq_pk = ?", jobQueueID).
        Scan(&result).Error
    if err != nil {
        debug.Error("Failed to get folder name: " + err.Error())
    } else {
        debug.Info("Successfully retrieved folder name: " + result.FolderName)
    }
    return result.FolderName, result.FolderPK, err
}

func (service *DBService) GetParentFolderName(childID int) (string, int, error) {
    var result struct {
        FolderName string `gorm:"column:folder_name"`
        FolderPK   int    `gorm:"column:folder_pk"`
    }
    err := service.DB.Table("folder").
        Select("folder_name, folder_pk").
        Joins("INNER JOIN foldercontents ON folder_pk = foldercontents.parent_fk").
        Where("child_id = ? AND foldercontents_mode = 1", childID).
        Scan(&result).Error
    if err != nil {
        debug.Error("Failed to get parent folder name: " + err.Error())
    } else {
        debug.Info("Successfully retrieved parent folder name: " + result.FolderName)
    }
    return result.FolderName, result.FolderPK, err
}

func (service *DBService) GetUserEmail(uploadID int) (string, string, bool, error) {
    var result struct {
        UserName    string `gorm:"column:user_name"`
        UserEmail   string `gorm:"column:user_email"`
        EmailNotify bool   `gorm:"column:email_notify"`
    }
    err := service.DB.Table("users").
        Select("user_name, user_email, email_notify").
        Joins("JOIN upload ON user_pk = user_fk").
        Where("upload_pk = ?", uploadID).
        Scan(&result).Error
    if err != nil {
        debug.Error("Failed to get user email: " + err.Error())
    } else {
        debug.Info("Successfully retrieved user email: " + result.UserEmail)
    }
    return result.UserName, result.UserEmail, result.EmailNotify, err
}

func (service *DBService) GetIndependentJobs() ([]JobQueue, error) {
    var jobs []JobQueue
    err := service.DB.Table("jobqueue").
        Select("job_name, job_user_fk, job_priority, job_email_notify, job_group_fk").
        Joins("INNER JOIN job ON job_pk = jq_job_fk").
        Where("jq_starttime IS NULL AND jq_end_bits < 2").
        Where("NOT EXISTS(SELECT * FROM jobdepends, jobqueue jdep WHERE jdep_jq_fk = jobqueue.jq_pk AND jdep_jq_depends_fk = jdep.jq_pk AND NOT(jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2))").
        Order("job_priority DESC").
        Find(&jobs).Error
    if err != nil {
        debug.Error("Failed to get independent jobs: " + err.Error())
    } else {
        debug.Info("Successfully retrieved independent jobs")
    }
    return jobs, err
}

func (service *DBService) GetCountofIndepJobs() (int64, error) {
    var countIndepJobs int64
    err := service.DB.Table("jobqueue").
        Joins("INNER JOIN job ON job_pk = jq_job_fk").
        Where("jq_starttime IS NULL AND jq_end_bits < 2").
        Where("NOT EXISTS (SELECT * FROM jobdepends, jobqueue jdep WHERE jdep_jq_fk = jobqueue.jq_pk AND jdep_jq_depends_fk = jdep.jq_pk AND NOT (jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2))").
        Count(&countIndepJobs).Error
    if err != nil {
        debug.Error("Failed to count independent jobs: " + err.Error())
    } else {
        debug.Info("Successfully counted independent jobs: " + strconv.FormatInt(countIndepJobs, 10))
    }
    return countIndepJobs, err
}

func (service *DBService) GetDependentJobs(jobQueueID int) ([]JobQueue, error) {
    var jobs []JobQueue
    err := service.DB.Table("jobqueue").
        Select(" job_name, job_user_fk, job_priority, job_group_fk").
        Where("jq_starttime IS NULL AND jq_end_bits < 2").
        Where("NOT EXISTS(SELECT * FROM jobdepends, jobqueue jdep WHERE jdep_jq_fk = jobqueue.jq_pk AND jdep_jq_depends_fk = jdep.jq_pk AND NOT(jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2))").
        Where("jq_job_fk = (SELECT jq_job_fk FROM jobqueue queue WHERE queue.jq_pk = ?)", jobQueueID).
        Find(&jobs).Error
    if err != nil {
        debug.Error("Failed to get dependent jobs: " + err.Error())
    } else {
        debug.Info("Successfully retrieved dependent jobs for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return jobs, err
}

func (service *DBService) GetCountofdepJobs(jobQueueID int) (int64, error) {
    var countdepJobs int64
    err := service.DB.Table("jobqueue").
        Select("*").
        Where("jq_starttime IS NULL AND jq_end_bits < 2").
        Where("NOT EXISTS(SELECT * FROM jobdepends, jobqueue jdep WHERE jdep_jq_fk = jobqueue.jq_pk AND jdep_jq_depends_fk = jdep.jq_pk AND NOT(jdep.jq_endtime IS NOT NULL AND jdep.jq_end_bits < 2))").
        Where("jq_job_fk = (SELECT jq_job_fk FROM jobqueue queue WHERE queue.jq_pk = ?)", jobQueueID).
        Find(&countdepJobs).Error
    if err != nil {
        debug.Error("Failed to count dependent jobs: " + err.Error())
    } else {
        debug.Info("Successfully counted dependent jobs for jobQueueID: " + strconv.FormatInt(countdepJobs, 10))
    }
    return countdepJobs, err
}

func (service *DBService) JobEndBits(jobQueueID int) ([]JobQueue, error) {
    var jobs []JobQueue
    err := service.DB.Table("jobqueue").
        Select("jq_pk, jq_end_bits").
        Where("jq_job_fk = (SELECT jq_job_fk FROM jobqueue WHERE jq_pk = ?)", jobQueueID).
        Find(&jobs).Error
    if err != nil {
        debug.Error("Failed to get job end bits: " + err.Error())
    } else {
        debug.Info("Successfully retrieved job end bits for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return jobs, err
}

func (service *DBService) GetSMTPConfig() (map[string]string, error) {
    var smtpConfigs []struct {
        ConfValue    string `gorm:"column:conf_value"`
        VariableName string `gorm:"column:variablename"`
    }
    err := service.DB.Table("sysconfig").
        Select("conf_value, variablename").
        Where("variablename LIKE 'SMTP%'").
        Scan(&smtpConfigs).Error
    if err != nil {
        debug.Error("Failed to get SMTP config: " + err.Error())
        return nil, err
    }

    debug.Info("Successfully retrieved SMTP config")
    config := make(map[string]string)
    for _, smtpConfig := range smtpConfigs {
        config[smtpConfig.VariableName] = smtpConfig.ConfValue
    }
    return config, nil
}

func (service *DBService) GetJobInfo(jobQueueID int) ([]JobQueue, error) {
    var jobs []JobQueue
    err := service.DB.Table("jobqueue").
        Select("jq_end_bits").
        Where("jq_job_fk = (SELECT jq_job_fk FROM jobqueue WHERE jq_pk = ?)", jobQueueID).
        Find(&jobs).Error
    if err != nil {
        debug.Error("Failed to get job info: " + err.Error())
    } else {
        debug.Info("Successfully retrieved job info for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return jobs, err
}

func (service *DBService) GetUploadsize(sizeInBytes int) (int, error) {
    var size int
    err := service.DB.Table("pfile").
        Select("pfile.pfile_size").
        Joins("JOIN upload ON pfile.pfile_pk = upload.pfile_fk").
        Where("upload.pfile_fk = ?", sizeInBytes).
        Scan(&size).Error
    if err != nil {
        debug.Error("Failed to get upload size: " + err.Error())
        return 0, err
    }
    debug.Info("Successfully retrieved upload size: " + strconv.Itoa(size))
    return size, nil
}

func (service *DBService) JobStarted(jobQueueID int, schedInfo string) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Updates(map[string]interface{}{
            "jq_starttime":  gorm.Expr("NOW()"),
            "jq_schedinfo":  schedInfo,
            "jq_endtext":    "Started",
        }).Error
    if err != nil {
        debug.Error("Failed to update job as started: " + err.Error())
    } else {
        debug.Info("Job marked as started for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobComplete(jobQueueID int) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Updates(map[string]interface{}{
            "jq_endtime":    gorm.Expr("NOW()"),
            "jq_end_bits":   gorm.Expr("jq_end_bits | 1"),
            "jq_schedinfo":  nil,
            "jq_endtext":    "Completed",
        }).Error
    if err != nil {
        debug.Error("Failed to mark job as complete: " + err.Error())
    } else {
        debug.Info("Job marked as complete for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobRestart(jobQueueID int) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Update("jq_endtext", "Restarted").
        Update("jq_starttime", gorm.Expr(`CASE
            WHEN jq_starttime = CAST('9999-12-31' AS timestamp with time zone) THEN NULL
            ELSE jq_starttime
        END`)).Error
    if err != nil {
        debug.Error("Failed to restart job: " + err.Error())
    } else {
        debug.Info("Job restarted for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobFailed(jobQueueID int, endText string) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Updates(map[string]interface{}{
            "jq_endtime":    gorm.Expr("NOW()"),
            "jq_end_bits":   gorm.Expr("jq_end_bits | 2"),
            "jq_schedinfo":  nil,
            "jq_endtext":    endText,
        }).Error
    if err != nil {
        debug.Error("Failed to mark job as failed: " + err.Error())
    } else {
        debug.Info("Job marked as failed for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobProcessed(jobQueueID int, itemsProcessed int) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Update("jq_itemsprocessed", itemsProcessed).Error
    if err != nil {
        debug.Error("Failed to update items processed for jobQueueID: " + err.Error())
    } else {
        debug.Info("Items processed updated for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobPaused(jobQueueID int) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Update("jq_endtext", "Paused").
        Update("jq_starttime", gorm.Expr(`CASE
            WHEN jq_starttime IS NULL THEN CAST('9999-12-31' AS timestamp with time zone)
            ELSE jq_starttime
        END`)).Error
    if err != nil {
        debug.Error("Failed to pause job: " + err.Error())
    } else {
        debug.Info("Job paused for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobLog(jobQueueID int, log string) error {
    err := service.DB.Table("jobqueue").
        Where("jq_pk = ?", jobQueueID).
        Update("jq_log", log).Error
    if err != nil {
        debug.Error("Failed to log job for jobQueueID: " + err.Error())
    } else {
        debug.Info("Job log updated for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobPriority(jobQueueID int, priority int) error {
    err := service.DB.Exec(`
        UPDATE job
        SET job_priority = ?
        WHERE job_pk IN (
            SELECT jq_job_fk FROM jobqueue
            WHERE jq_pk = ?)`, priority, jobQueueID).Error
    if err != nil {
        debug.Error("Failed to update job priority: " + err.Error())
    } else {
        debug.Info("Job priority updated for jobQueueID: " + strconv.Itoa(jobQueueID))
    }
    return err
}

func (service *DBService) JobReset() error {
    err := service.DB.Table("jobqueue").
        Where("jq_endtime IS NULL").
        Updates(map[string]interface{}{
            "jq_starttime":  nil,
            "jq_endtext":    nil,
            "jq_schedinfo":  nil,
        }).Error
    if err != nil {
        debug.Error("Failed to reset jobs: " + err.Error())
    } else {
        debug.Info("Jobs reset successfully")
    }
    return err
}