package github

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"

	"github.com/abcprintf/git-bridge/internal/provider"
)

// Client รองรับทั้ง github.com และ GitHub Enterprise (GHES)
type Client struct {
	baseURL string // "https://api.github.com" หรือ "https://github.example.com/api/v3"
	token   string // Personal Access Token หรือ Fine-grained token
	owner   string // org หรือ username
	repo    string // repository name
	http    *http.Client
}

func NewClient(baseURL, token, owner, repo string) *Client {
	return &Client{
		baseURL: strings.TrimRight(baseURL, "/"),
		token:   token,
		owner:   owner,
		repo:    repo,
		http:    &http.Client{Timeout: 15 * time.Second},
	}
}

func (c *Client) Name() string { return "github" }

// ─────────────────────────────────────────
// GitHub Branch Creation:
// Step 1: GET /repos/:owner/:repo/git/ref/heads/:base  → SHA
// Step 2: POST /repos/:owner/:repo/git/refs            → create branch ref
// ─────────────────────────────────────────

type ghRef struct {
	Object struct {
		SHA string `json:"sha"`
	} `json:"object"`
}

type createRefReq struct {
	Ref string `json:"ref"` // "refs/heads/<branch-name>"
	SHA string `json:"sha"`
}

type ghRefResponse struct {
	Ref string `json:"ref"`
	URL string `json:"url"`
}

type ghError struct {
	Message string `json:"message"`
	Errors  []struct {
		Code string `json:"code"`
	} `json:"errors"`
}

func (c *Client) CreateBranch(branchName, ref string) (*provider.Branch, bool, error) {
	// Step 1: หา SHA ของ base branch
	sha, err := c.getRefSHA(ref)
	if err != nil {
		return nil, false, fmt.Errorf("get base branch SHA: %w", err)
	}

	// Step 2: สร้าง branch
	return c.createRef(branchName, sha)
}

func (c *Client) getRefSHA(ref string) (string, error) {
	apiURL := fmt.Sprintf("%s/repos/%s/%s/git/ref/heads/%s",
		c.baseURL, c.owner, c.repo, ref,
	)

	req, _ := http.NewRequest("GET", apiURL, nil)
	c.setHeaders(req)

	resp, err := c.http.Do(req)
	if err != nil {
		return "", fmt.Errorf("github request: %w", err)
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(resp.Body)

	if resp.StatusCode == http.StatusNotFound {
		return "", fmt.Errorf("github: base branch %q not found in %s/%s", ref, c.owner, c.repo)
	}
	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("github %d: %s", resp.StatusCode, body)
	}

	var ghref ghRef
	json.Unmarshal(body, &ghref)
	if ghref.Object.SHA == "" {
		return "", fmt.Errorf("github: empty SHA for ref %q", ref)
	}
	return ghref.Object.SHA, nil
}

func (c *Client) createRef(branchName, sha string) (*provider.Branch, bool, error) {
	apiURL := fmt.Sprintf("%s/repos/%s/%s/git/refs",
		c.baseURL, c.owner, c.repo,
	)

	payload := createRefReq{
		Ref: "refs/heads/" + branchName,
		SHA: sha,
	}
	body, _ := json.Marshal(payload)

	req, _ := http.NewRequest("POST", apiURL, bytes.NewBuffer(body))
	c.setHeaders(req)

	resp, err := c.http.Do(req)
	if err != nil {
		return nil, false, fmt.Errorf("github request: %w", err)
	}
	defer resp.Body.Close()
	respBody, _ := io.ReadAll(resp.Body)

	switch resp.StatusCode {
	case http.StatusCreated:
		var repoURL, webURL string
		if strings.Contains(c.baseURL, "api.github.com") {
			repoURL = fmt.Sprintf("https://github.com/%s/%s", c.owner, c.repo)
		} else {
			host := strings.Replace(c.baseURL, "/api/v3", "", 1)
			repoURL = fmt.Sprintf("%s/%s/%s", host, c.owner, c.repo)
		}
		webURL = repoURL + "/tree/" + branchName
		return &provider.Branch{Name: branchName, WebURL: webURL, RepoURL: repoURL}, false, nil

	case http.StatusUnprocessableEntity:
		// 422 = branch already exists
		var ghErr ghError
		json.Unmarshal(respBody, &ghErr)
		for _, e := range ghErr.Errors {
			if e.Code == "already_exists" {
				return nil, true, nil
			}
		}
		if strings.Contains(ghErr.Message, "already exists") {
			return nil, true, nil
		}
		return nil, false, fmt.Errorf("github 422: %s", respBody)

	case http.StatusUnauthorized:
		return nil, false, fmt.Errorf("github: invalid token — check scope: contents:write")

	case http.StatusNotFound:
		return nil, false, fmt.Errorf("github: repo %s/%s not found or no access", c.owner, c.repo)

	default:
		return nil, false, fmt.Errorf("github %d: %s", resp.StatusCode, respBody)
	}
}

func (c *Client) setHeaders(req *http.Request) {
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Accept", "application/vnd.github+json")
	req.Header.Set("X-GitHub-Api-Version", "2022-11-28")
	req.Header.Set("Content-Type", "application/json")
}
