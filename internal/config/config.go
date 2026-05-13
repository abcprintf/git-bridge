package config

import (
	"log"
	"os"
	"strings"
)

type Config struct {
	Port            string
	WebhookSecret   string
	APIToken        string
	TriggerStatuses []string
	ProjectsFile    string // path ของ projects.json
}

func Load() *Config {
	return &Config{
		Port:          getEnv("PORT", "8080"),
		WebhookSecret: requireEnv("WEBHOOK_SECRET"),
		APIToken:      requireEnv("API_TOKEN"),
		TriggerStatuses: splitTrim(
			getEnv("TRIGGER_STATUSES", "assigned,in progress"),
		),
		ProjectsFile: getEnv("PROJECTS_FILE", "/etc/git-bridge/projects.json"),
	}
}

func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func requireEnv(key string) string {
	v := os.Getenv(key)
	if v == "" {
		log.Fatalf("[config] required env var %q is not set", key)
	}
	return v
}

func splitTrim(s string) []string {
	parts := strings.Split(s, ",")
	for i, p := range parts {
		parts[i] = strings.TrimSpace(strings.ToLower(p))
	}
	return parts
}
