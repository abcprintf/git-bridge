package config

import (
	"encoding/json"
	"fmt"
	"os"
	"strings"
)

// ProjectConfig คือ mapping ของ MantisBT project → Git repo
type ProjectConfig struct {
	MantisProjectID int    `json:"mantis_project_id"`
	Provider        string `json:"provider"` // "gitlab" หรือ "github"
	BaseBranch      string `json:"base_branch,omitempty"` // default "main"

	// GitLab
	GitLabURL       string `json:"gitlab_url,omitempty"`
	GitLabToken     string `json:"gitlab_token,omitempty"`
	GitLabProjectID string `json:"gitlab_project_id,omitempty"`

	// GitHub / GHES
	GitHubAPIURL string `json:"github_api_url,omitempty"` // default "https://api.github.com"
	GitHubToken  string `json:"github_token,omitempty"`
	GitHubOwner  string `json:"github_owner,omitempty"`
	GitHubRepo   string `json:"github_repo,omitempty"`
}

// LoadProjects อ่าน projects.json และ return map[mantisProjectID]ProjectConfig
func LoadProjects(path string) (map[int]ProjectConfig, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("open %s: %w", path, err)
	}
	defer f.Close()

	var projects []ProjectConfig
	if err := json.NewDecoder(f).Decode(&projects); err != nil {
		return nil, fmt.Errorf("parse %s: %w", path, err)
	}

	result := make(map[int]ProjectConfig, len(projects))
	for _, p := range projects {
		if err := validateProject(p); err != nil {
			return nil, fmt.Errorf("project mantis_id=%d: %w", p.MantisProjectID, err)
		}
		if p.BaseBranch == "" {
			p.BaseBranch = "main"
		}
		if p.Provider == "github" && p.GitHubAPIURL == "" {
			p.GitHubAPIURL = "https://api.github.com"
		}
		result[p.MantisProjectID] = p
	}

	if len(result) == 0 {
		return nil, fmt.Errorf("%s is empty — add at least one project mapping", path)
	}

	return result, nil
}

func validateProject(p ProjectConfig) error {
	if p.MantisProjectID <= 0 {
		return fmt.Errorf("mantis_project_id must be > 0")
	}

	switch strings.ToLower(p.Provider) {
	case "gitlab":
		if p.GitLabURL == "" || p.GitLabToken == "" || p.GitLabProjectID == "" {
			return fmt.Errorf("gitlab requires: gitlab_url, gitlab_token, gitlab_project_id")
		}
	case "github":
		if p.GitHubToken == "" || p.GitHubOwner == "" || p.GitHubRepo == "" {
			return fmt.Errorf("github requires: github_token, github_owner, github_repo")
		}
	default:
		return fmt.Errorf("provider must be 'gitlab' or 'github', got %q", p.Provider)
	}
	return nil
}
