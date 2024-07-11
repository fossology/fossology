package config

import (
	"fmt"

	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

// extract from files!
var dsn = ""

// gorm.db ==> struct to perform crud operation
// gorm.config ==> configure gorm.db for enabling sql logging or other utilities 

func ConnectDB(dsn string)(*gorm.DB, error){
	db, err := gorm.Open(postgres.Open(dsn), &gorm.Config{} )
	if err != nil {
		return nil, fmt.Errorf("failed: Connection To database %v", err)
	}
	return db, err
}

func DisconnectDB(db *gorm.DB) error {
	psqlDB, err := db.DB()
	if err != nil {
		return fmt.Errorf("failed: Disconnect to Database %v",err)
	}
	err = psqlDB.Close()
	if err != nil {
		return fmt.Errorf("failed: Closing Database Connection %v", err)
	}
	return nil
}
