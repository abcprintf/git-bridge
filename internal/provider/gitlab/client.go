package gitlab

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"

	"github.com/igenco/git-bridge/internal/provider"
)

type Client struct {
	baseURL   string
	token     string
	projectID string
	http      *http.Client
}

func NewClient(baseURL, token, projectID string) *Client {
	return &Client{
		baseURL:   strings.TrimRight(baseURL, "/"),
		token:     token,
		projectID: projectID,
		http:      &http.Client{Timeout: 15 * time.Second},
	}
}

func (c *Client) Name() string { return "gitlab" }

type createBranchReq struct {
	Branch string `json:"branch"`
	Ref    string `json:"ref"`
}

type glBranch struct {
	Name   string `json:"name"`
	WebURL string `json:"web_url"`
}

type glError struct {
	Message interface{} `json:"message"`
}

func (c *Client) CreateBranch(branchName, ref string) (*provider.Branch, bool, error) {
	apiURL := fmt.Sprintf("%s/api/v4/projects/%s/repository/branches",
		c.baseURL,
		url.PathEscape(c.projectID),
	)

	body, _ := json.Marshal(createBranchReq{Branch: branchName, Ref: ref})
	req, err := http.NewRequest("POST", apiURL, bytes.NewBuffer(body))
	if err != nil {
		return nil, false, fmt.Errorf("build request: %w", err)
	}
	req.Header.Set("PRIVATE-TOKEN", c.token)
	req.Header.Set("Content-Type", "application/json")

	resp, err := c.http.Do(req)
	if err != nil {
		return nil, false, fmt.Errorf("gitlab request: %w", err)
	}
	defer resp.Body.Close()
	respBody, _ := io.ReadAll(resp.Body)

	switch resp.StatusCode {
	case http.StatusCreated:
		var b glBranch
		json.Unmarshal(respBody, &b)
		// GitLab WebURL: https://gitlab.igenco.dev/group/project/-/tree/branch
		// RepoURL:       https://gitlab.igenco.dev/group/project
		repoURL := repoURLFromGitLab(b.WebURL, b.Name)
		return &provider.Branch{Name: b.Name, WebURL: b.WebURL, RepoURL: repoURL}, false, nil

	case http.StatusBadRequest:
		var glErr glError
		json.Unmarshal(respBody, &glErr)
		msg := fmt.Sprintf("%v", glErr.Message)
		if strings.Contains(msg, "already exists") || strings.Contains(msg, "Branch already") {
			return nil, true, nil
		}
		return nil, false, fmt.Errorf("gitlab 400: %s", respBody)

	case http.StatusUnauthorized:
		return nil, false, fmt.Errorf("gitlab: invalid token — check scope: write_repository")

	case http.StatusNotFound:
		return nil, false, fmt.Errorf("gitlab: project %q not found or no access", c.projectID)

	default:
		return nil, false, fmt.Errorf("gitlab %d: %s", resp.StatusCode, respBody)
	}
}

// repoURLFromGitLab ตัด "/-/tree/<branch>" ออกจาก GitLab web URL
// https://gitlab.igenco.dev/group/project/-/tree/issue/42 → https://gitlab.igenco.dev/group/project
func repoURLFromGitLab(webURL, branchName string) string {
	suffix := "/-/tree/" + branchName
	if idx := strings.LastIndex(webURL, suffix); idx >= 0 {
		return webURL[:idx]
	}
	return webURL
}
