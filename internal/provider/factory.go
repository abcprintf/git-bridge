package provider

import (
	"fmt"
	"strings"

	"github.com/igenco/git-bridge/internal/config"
	"github.com/igenco/git-bridge/internal/provider/github"
	"github.com/igenco/git-bridge/internal/provider/gitlab"
)

// NewFromProjectConfig สร้าง Provider จาก ProjectConfig
func NewFromProjectConfig(p config.ProjectConfig) (Provider, error) {
	switch strings.ToLower(p.Provider) {
	case "gitlab":
		return gitlab.NewClient(p.GitLabURL, p.GitLabToken, p.GitLabProjectID), nil
	case "github":
		return github.NewClient(p.GitHubAPIURL, p.GitHubToken, p.GitHubOwner, p.GitHubRepo), nil
	default:
		return nil, fmt.Errorf("unknown provider: %q", p.Provider)
	}
}

// BuildProviderMap สร้าง map[mantisProjectID]Provider จาก projects config
func BuildProviderMap(projects map[int]config.ProjectConfig) (map[int]Provider, error) {
	m := make(map[int]Provider, len(projects))
	for id, pc := range projects {
		p, err := NewFromProjectConfig(pc)
		if err != nil {
			return nil, fmt.Errorf("project mantis_id=%d: %w", id, err)
		}
		m[id] = p
	}
	return m, nil
}
