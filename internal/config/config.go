package config

import (
	"log"
	"os"
	"strings"
)

type Config struct {
	Port            string
	BaseBranch      string
	TriggerStatuses []string
	WebhookSecret   string
	APIToken        string

	// Provider: "gitlab" หรือ "github"
	Provider string

	// GitLab
	GitLabURL       string
	GitLabToken     string
	GitLabProjectID string

	// GitHub / GitHub Enterprise
	GitHubAPIURL string // "https://api.github.com" หรือ GHES URL
	GitHubToken  string
	GitHubOwner  string // org หรือ username
	GitHubRepo   string
}

func Load() *Config {
	p := strings.ToLower(getEnv("GIT_PROVIDER", "gitlab"))
	if p != "gitlab" && p != "github" {
		log.Fatalf("[config] GIT_PROVIDER must be 'gitlab' or 'github', got %q", p)
	}

	cfg := &Config{
		Port:       getEnv("PORT", "8080"),
		Provider:   p,
		BaseBranch: getEnv("BASE_BRANCH", "main"),
		TriggerStatuses: splitTrim(
			getEnv("TRIGGER_STATUSES", "assigned,in progress"),
		),
		WebhookSecret: requireEnv("WEBHOOK_SECRET"),
		APIToken:      requireEnv("API_TOKEN"),
	}

	switch p {
	case "gitlab":
		cfg.GitLabURL       = requireEnv("GITLAB_URL")
		cfg.GitLabToken     = requireEnv("GITLAB_TOKEN")
		cfg.GitLabProjectID = requireEnv("GITLAB_PROJECT_ID")

	case "github":
		cfg.GitHubAPIURL = getEnv("GITHUB_API_URL", "https://api.github.com")
		cfg.GitHubToken  = requireEnv("GITHUB_TOKEN")
		cfg.GitHubOwner  = requireEnv("GITHUB_OWNER")
		cfg.GitHubRepo   = requireEnv("GITHUB_REPO")
	}

	return cfg
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
