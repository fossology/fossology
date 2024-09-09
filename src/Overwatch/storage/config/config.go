package config

/*
 * \file
 * \brief Package configuration for managing agents, instances, special flags, email, and database settings.
 *
 * This file contains the following:
 * 
 * - **AgentConfig struct**: Manages agent-related settings.
 * - **Instances struct**: Holds maximum instance configurations.
 * - **SpecialFlags struct**: Handles special flag settings for agents.
 * - **EmailSettings struct**: Manages email notification settings.
 * - **DBSettings struct**: Handles database connection settings.
 *
 * Functions:
 *
 * - **LoadInstances**: Loads instance settings from a configuration file.
 *   @param configFile Path to the configuration file.
 *   @return Instances struct with the maximum instance count and an error if loading fails.
 *
 * - **LoadSpecialFlags**: Loads special flags from a configuration file.
 *   @param configFile Path to the configuration file.
 *   @return SpecialFlags struct containing the special flags and an error if loading fails.
 *
 * - **LoadEmailSettings**: Loads email settings from the configuration file.
 *   @param configFile Path to the configuration file.
 *   @return EmailSettings struct containing email settings and an error if loading fails.
 *
 * - **LoadDBSettings**: Loads database connection settings from the configuration file.
 *   @param configFile Path to the configuration file.
 *   @return DBSettings struct containing database settings and an error if loading fails.
 *
 * - **ConnectDB**: Connects to the database using the provided DSN string.
 *   @param dsn Data Source Name for connecting to the database.
 *   @return Pointer to the connected GORM DB instance and an error if the connection fails.
 *
 * - **DisconnectDB**: Disconnects from the database.
 *   @param db Pointer to the GORM DB instance to be disconnected.
 *   @return An error if the disconnection fails.
 */

import (
	"fmt"

	"github.com/spf13/viper"
	"gorm.io/driver/postgres"
	"gorm.io/gorm"

	debug "Overwatch/logging"
)

type AgentConfig struct{
    Max   int
    Flags []string
}   

type Instances  struct{
    Max int
}

type SpecialFlags struct {
    Flags []string
}

func (agent *AgentConfig) LoadInstances(configFile string) (Instances, error) {
    viper.SetConfigFile(configFile)

    if err := viper.ReadInConfig(); err != nil {
        return Instances{}, fmt.Errorf("failed to read config file: %w", err)
    }

    max := viper.GetInt("default.max")
    return Instances{Max: max}, nil
}

func (agent *AgentConfig) LoadSpecialFlags(configFile string) (SpecialFlags, error) {
    viper.SetConfigFile(configFile)

    if err := viper.ReadInConfig(); err != nil {
        return SpecialFlags{}, fmt.Errorf("failed to read config file: %w", err)
    }

    flags := viper.GetStringSlice("default.special")
    return SpecialFlags{Flags: flags}, nil
}

type EmailSettings struct {
    Header  string
    Footer  string
    Subject string
    Client  string
}

type DBSettings struct{
    DBHost     string
    DBPort     string
    DBUser     string
    DBPassword string
}

func LoadEmailSettings(configFile string) (EmailSettings, error) {
    viper.SetConfigFile(configFile)

    if err := viper.ReadInConfig(); err != nil {
        return EmailSettings{}, fmt.Errorf("error reading config file: %w", err)
    }

    emailSettings := EmailSettings{
        Header:  viper.GetString("EMAILNOTIFY.header"),
        Footer:  viper.GetString("EMAILNOTIFY.footer"),
        Subject: viper.GetString("EMAILNOTIFY.subject"),
        Client:  viper.GetString("EMAILNOTIFY.client"),
    }

    return emailSettings, nil
}

func LoadDBSettings(configFile string) (DBSettings, error) {
    viper.SetConfigFile(configFile)

    if err := viper.ReadInConfig(); err != nil {
        return DBSettings{}, fmt.Errorf("error reading config file: %w", err)
    }

    dbSettings := DBSettings{
        DBHost:     viper.GetString("DBHOST"),
        DBPort:     viper.GetString("DBPORT"),
        DBUser:     viper.GetString("DBUSER"),
        DBPassword: viper.GetString("DBPASSWORD"),
    }

    return dbSettings, nil
}


func ConnectDB(dsn string)(db *gorm.DB,err error){
	db, err = gorm.Open(postgres.Open(dsn), &gorm.Config{} )
	if err != nil {
		debug.Error("Failed to load configuration: " + err.Error())
	}
	return db, err	
}

func DisconnectDB(db *gorm.DB) error {
	psqlDB, err := db.DB()
	if err != nil {
		debug.Error("Failed to load configuration: " + err.Error())
	}
	err = psqlDB.Close()
	if err != nil {
		debug.Error("Failed to load configuration: " + err.Error())
	}
	return nil
}