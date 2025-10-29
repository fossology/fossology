package storage

/*
 * \file
 * \brief Manages storage operations, configuration loading, and data handling within the application.
 *
 * This file contains:
 * - Functions for loading configurations, managing data storage, and handling related tasks.
 * 
 * Functions:
 * - **LoadConfig**: Loads storage-related configuration settings.
 *   @param configFile Path to the configuration file.
 *   @return Configuration struct with loaded settings and error if loading fails.
 */


import (
	"fmt"
	"log"

	debug "Overwatch/logging"
	config "Overwatch/storage/config"
	data "Overwatch/storage/data"
)

// path required & encrypt?
var FossologyPath = ""
var AgentPath = ""

var Jobqueue data.JobQueue

type Instances = config.Instances
type SpecialFlags = config.SpecialFlags

type AgentConfigLoader struct {
    agentConfig config.AgentConfig
}

type DBSettings struct {
    DBHost     string
    DBPort     string
    DBUser     string
    DBPassword string
}

func (loader AgentConfigLoader) LoadInstances(configFile string) (config.Instances, error) {
    return loader.agentConfig.LoadInstances(configFile)
}

func (loader AgentConfigLoader) LoadSpecialFlags(configFile string) (config.SpecialFlags, error) {
    return loader.agentConfig.LoadSpecialFlags(configFile)
}


func Init() {
    debug.Info("Initializing storage service")

    dbConfig, err := config.LoadDBSettings(FossologyPath)
    if err != nil {
        log.Fatalf("Failed to load DB settings: %v", err)
    }
    
    dsn := fmt.Sprintf("host=%s user=%s password=%s dbname=%s port=%s sslmode=disable TimeZone=UTC",
        dbConfig.DBHost, dbConfig.DBUser, dbConfig.DBPassword, "your_db_name", dbConfig.DBPort)
    
    db,err := config.ConnectDB(dsn)
	if err != nil{
		debug.Error("")
	}
	debug.Info("Connected to database successfully")

    defer func() {
        config.DisconnectDB(db)
        debug.Info("Disconnected from database")
    }()
    
    debug.Info("Connected to database successfully")	
}

func Mailsettings(fossologyPath string) (config.EmailSettings, error) {
    EmailConfig, err := config.LoadEmailSettings(fossologyPath)  // Load email settings from config
    if err != nil {
        log.Fatalf("Failed to load email settings: %v", err)
        return config.EmailSettings{}, err
    }

    debug.Info(fmt.Sprintf("Email Client: %s", EmailConfig.Client))

    return EmailConfig, nil
}

type Tableinfo interface {
	GetUrl() (string, error)
	DescribeTable(tableCatalog, tableName string) ([]string, error)
}

type Uploadinfo interface {
	GetUploadId(jobqueueId int) (int, error)
	GetUploadCommon(uploadID int) ([]data.JobQueue, error)
	GetUploadName(jobQueueID int) (string, error)
	GetUploadPK(jobQueueID int) (int, int, error)
	GetUploadsize(sizeInBytes int) (int, error)
}

type Mailinfo interface {
	GetSMTPConfig() (map[string]string, error)
	GetUserEmail(uploadID int) (string, string, bool, error)
	Mailsettings(fossologyPath string) (config.EmailSettings, error)
}

type Folderinfo interface {
	GetFolderName(jobQueueID int) (string, int, error)
	GetParentFolderName(childID int) (string, int, error)
}

type Queueinfo interface {
	GetIndependentJobs() ([]data.JobQueue, error)
	GetDependentJobs(jobQueueID int) ([]data.JobQueue, error)
	GetCountofIndepJobs() (int64, error)
	GetCountofdepJobs(jobQueueID int) (int64, error)
}

type Jobinfo interface {
	GetJobInfo(jobQueueID int) ([]data.JobQueue, error)
	JobPriority(jobQueueID int, priority int) error
	JobStarted(jobQueueID int, schedInfo string) error
	JobProcessed(jobQueueID int, itemsProcessed int) error
	JobPaused(jobQueueID int) error
	JobFailed(jobQueueID int, endText string) error
	JobRestart(jobQueueID int) error
	JobReset() error
	JobComplete(jobQueueID int) error
	JobEndBits(jobQueueID int) ([]data.JobQueue, error)
	JobLog(jobQueueID int, log string) error
}

type Userinfo interface {
	GetUserEmail(uploadID int) (string, string, bool, error)
	GetSMTPConfig() (map[string]string, error)
}

type AgentInfo interface {
    LoadInstances(configFile string) (Instances, error)
    LoadSpecialFlags(configFile string) (SpecialFlags, error)
}

type ConfigInterface interface {
    LoadDBSettings(configFile string) (DBSettings, error)
}