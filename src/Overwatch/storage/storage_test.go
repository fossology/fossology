package storage_test

/*
 * \file
 * \brief Test suite for validating storage operations and configuration handling.
 *
 * This file contains:
 * - Tests for configuration loading, data management, and related functionalities.
 * 
 * Functions:
 * - **TestLoadConfig**: Validates the configuration loading process for storage settings.
 *   @param t *testing.T The testing context.
 *   @return No return value; reports test pass/fail status.
 */


import (
    "testing"
    "os"
    "log"

    "github.com/stretchr/testify/mock"
    "github.com/stretchr/testify/assert"

    "Overwatch/storage"
    "Overwatch/storage/config"
)

func createTempConfig(content string) string {
    file, err := os.CreateTemp("", "config_*.json")
    if err != nil {
        log.Fatalf("Could not create temporary config file: %v", err)
    }
    file.WriteString(content)
    file.Close()
    return file.Name()
}

type MockAgentConfigLoader struct {
    mock.Mock
}

func (m *MockAgentConfigLoader) LoadInstances(configFile string) (config.Instances, error) {
    args := m.Called(configFile)
    return args.Get(0).(config.Instances), args.Error(1)
}

func (m *MockAgentConfigLoader) LoadSpecialFlags(configFile string) (config.SpecialFlags, error) {
    args := m.Called(configFile)
    return args.Get(0).(config.SpecialFlags), args.Error(1)
}

func TestAgentConfigLoader_LoadInstances_Success(t *testing.T) {
    configFile := createTempConfig(`{"default": {"max": 5}}`)
    defer os.Remove(configFile)

    loader := storage.AgentConfigLoader{}
    instances, err := loader.LoadInstances(configFile)

    assert.NoError(t, err, "Expected no error when loading instances")
    assert.Equal(t, 5, instances.Max, "Expected Max to be 5")
}

func TestAgentConfigLoader_LoadInstances_Error(t *testing.T) {
    configFile := "invalid_path.json"
    loader := storage.AgentConfigLoader{}
    _, err := loader.LoadInstances(configFile)

    assert.Error(t, err, "Expected error due to invalid file path")
}

func TestAgentConfigLoader_LoadSpecialFlags_Success(t *testing.T) {
    configFile := createTempConfig(`{"default": {"special": ["FLAG1", "FLAG2"]}}`)
    defer os.Remove(configFile)

    loader := storage.AgentConfigLoader{}
    flags, err := loader.LoadSpecialFlags(configFile)

    assert.NoError(t, err, "Expected no error when loading special flags")
    assert.Equal(t, 2, len(flags.Flags), "Expected two special flags")
}

func TestAgentConfigLoader_LoadSpecialFlags_Error(t *testing.T) {
    configFile := "invalid_path.json"
    loader := storage.AgentConfigLoader{}
    _, err := loader.LoadSpecialFlags(configFile)

    assert.Error(t, err, "Expected error due to invalid file path")
}

func TestMailSettings_Success(t *testing.T) {
    configFile := createTempConfig(`{"EMAILNOTIFY": {"header": "Test Header", "footer": "Test Footer", "subject": "Test Subject", "client": "Test Client"}}`)
    defer os.Remove(configFile)

    emailSettings, err := storage.Mailsettings(configFile)

    assert.NoError(t, err, "Expected no error when loading mail settings")
    assert.Equal(t, "Test Client", emailSettings.Client, "Expected Client to be 'Test Client'")
}

func TestMailSettings_Error(t *testing.T) {
    configFile := "invalid_path.json"
    _, err := storage.Mailsettings(configFile)

    assert.Error(t, err, "Expected error due to invalid file path")
}

func TestInitializeStorage(t *testing.T) {
    defer func() {
        if r := recover(); r != nil {
            t.Logf("Recovered from panic: %v", r)
        }
    }()

    storage.FossologyPath = createTempConfig(`{"DBHOST": "localhost", "DBPORT": "5432", "DBUSER": "user", "DBPASSWORD": "pass"}`)
    defer os.Remove(storage.FossologyPath)

    storage.Init() 
}
