package config_test

/*
 * \file
 * \brief Test suite for validating the configuration loading and management processes.
 *
 * This file contains:
 * - Tests to ensure proper configuration loading and error handling.
 * 
 * Functions:
 * - **TestLoadAppConfig**: Tests the loading of application configuration settings.
 *   @param t *testing.T The testing context.
 *   @return No return value; reports test pass/fail status.
 */

import (
    "testing"
    "os"

    "Overwatch/storage/config"
)

func createTempConfig(content string) string {
    file, _ := os.CreateTemp("", "config_*.json")
    file.WriteString(content)
    file.Close()
    return file.Name()
}

func TestLoadInstances_ValidFile(t *testing.T) {
    configFile := createTempConfig(`{"default": {"max": 10}}`)
    defer os.Remove(configFile)

    agent := config.AgentConfig{}
    instances, err := agent.LoadInstances(configFile)

    if err != nil {
        t.Fatalf("Expected no error, got %v", err)
    }
    if instances.Max != 10 {
        t.Errorf("Expected Max to be 10, got %d", instances.Max)
    }
}

func TestLoadInstances_InvalidFile(t *testing.T) {
    configFile := "nonexistent_file.json"
    agent := config.AgentConfig{}
    _, err := agent.LoadInstances(configFile)

    if err == nil {
        t.Fatal("Expected error, got none")
    }
}

func TestLoadSpecialFlags_ValidFile(t *testing.T) {
    configFile := createTempConfig(`{"default": {"special": ["FLAG1", "FLAG2"]}}`)
    defer os.Remove(configFile)

    agent := config.AgentConfig{}
    flags, err := agent.LoadSpecialFlags(configFile)

    if err != nil {
        t.Fatalf("Expected no error, got %v", err)
    }
    if len(flags.Flags) != 2 {
        t.Errorf("Expected 2 flags, got %d", len(flags.Flags))
    }
}

func TestLoadEmailSettings_ValidFile(t *testing.T) {
    configFile := createTempConfig(`{"EMAILNOTIFY": {"header": "Header", "footer": "Footer", "subject": "Subject", "client": "Client"}}`)
    defer os.Remove(configFile)

    emailSettings, err := config.LoadEmailSettings(configFile)

    if err != nil {
        t.Fatalf("Expected no error, got %v", err)
    }
    if emailSettings.Client != "Client" {
        t.Errorf("Expected Client to be 'Client', got %s", emailSettings.Client)
    }
}

func TestLoadDBSettings_ValidFile(t *testing.T) {
    configFile := createTempConfig(`{"DBHOST": "localhost", "DBPORT": "5432", "DBUSER": "user", "DBPASSWORD": "pass"}`)
    defer os.Remove(configFile)

    dbSettings, err := config.LoadDBSettings(configFile)

    if err != nil {
        t.Fatalf("Expected no error, got %v", err)
    }
    if dbSettings.DBHost != "localhost" {
        t.Errorf("Expected DBHost to be 'localhost', got %s", dbSettings.DBHost)
    }
}

func TestConnectDB_DisconnectDB(t *testing.T) {
    dsn := "host=localhost user=youruser password=yourpassword dbname=yourdb port=5432 sslmode=disable TimeZone=UTC"
    db, err := config.ConnectDB(dsn)
    if err != nil {
        t.Fatalf("Expected no error connecting to DB, got %v", err)
    }
    err = config.DisconnectDB(db)
    if err != nil {
        t.Fatalf("Expected no error disconnecting from DB, got %v", err)
    }
}

func TestConnectDB_Failure(t *testing.T) {
    dsn := "invalid_dsn_string"
    _, err := config.ConnectDB(dsn)
    if err == nil {
        t.Fatal("Expected error, got none")
    }
}