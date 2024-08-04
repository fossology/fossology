package notify

import (
	"fmt"
	"net/smtp"

	store "aaditya-singh/gozilla/src/storage/database"
)

// Mail interface for notification
type EmailNotifier interface {
	SendEmail(to, subject, body string) error
	NotifyJobCompletion(jobQueueID int) error
}

// EmailService implements the EmailNotifier interface
type EmailService struct {
	DBService *store.DBService
}

// SendEmail sends an email using the SMTP configuration
func (service *EmailService) SendEmail(to, subject, body string) error {
	config, err := service.DBService.GetSMTPConfig()
	if err != nil {
		return err
	}

	auth := smtp.PlainAuth("", config["SMTPAuthUser"], config["SMTPAuthPasswd"], config["SMTPHostName"])

	msg := "From: " + config["SMTPFrom"] + "\n" +
		"To: " + to + "\n" +
		"Subject: " + subject + "\n\n" +
		body

	err = smtp.SendMail(config["SMTPHostName"]+":"+config["SMTPPort"], auth, config["SMTPFrom"], []string{to}, []byte(msg))
	if err != nil {
		return fmt.Errorf("failed to send email: %v", err)
	}
	return nil
}

// sends an email notification upon job completion
func (service *EmailService) NotifyJobCompletion(jobQueueID int) error {
	email, name, notify, err := service.DBService.GetJobDetails(jobQueueID)
	if err != nil {
		return err
	}

	if !notify {
		return nil // No notification needed
	}

	subject := fmt.Sprintf("Job %d Completed", jobQueueID)
	body := fmt.Sprintf("Hello %s,\n\nYour job with ID %d has been completed successfully.\n\nBest Regards,\nYour Team", name, jobQueueID)
	return service.SendEmail(email, subject, body)
}
