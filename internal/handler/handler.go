package handler

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"regexp"
	"strings"

	"github.com/abcprintf/git-bridge/internal/config"
	"github.com/abcprintf/git-bridge/internal/provider"
)

type Handler struct {
	// map[mantisProjectID]Provider
	providers map[int]provider.Provider
	// map[mantisProjectID]baseBranch
	baseBranches map[int]string
	cfg          *config.Config
}

func New(
	providers map[int]provider.Provider,
	projects map[int]config.ProjectConfig,
	cfg *config.Config,
) *Handler {
	branches := make(map[int]string, len(projects))
	for id, p := range projects {
		branches[id] = p.BaseBranch
	}
	return &Handler{
		providers:    providers,
		baseBranches: branches,
		cfg:          cfg,
	}
}

// ─────────────────────────────────────────
// MantisBT Webhook Payload
// ─────────────────────────────────────────

type mantisIssue struct {
	ID      int    `json:"id"`
	Summary string `json:"summary"`
	Status  struct {
		Name string `json:"name"`
	} `json:"status"`
	Handler *struct {
		Name string `json:"name"`
	} `json:"handler"`
	Project struct {
		ID int `json:"id"`
	} `json:"project"`
}

type mantisWebhookPayload struct {
	Issue mantisIssue `json:"issue"`
}

// POST /mantis-webhook
func (h *Handler) MantisWebhook(w http.ResponseWriter, r *http.Request) {
	var payload mantisWebhookPayload
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		jsonError(w, "invalid json", http.StatusBadRequest)
		return
	}

	issue := payload.Issue
	if issue.ID == 0 {
		jsonError(w, "missing issue data", http.StatusBadRequest)
		return
	}

	statusLower := strings.ToLower(strings.TrimSpace(issue.Status.Name))
	if !h.isTriggerStatus(statusLower) {
		log.Printf("[webhook] issue #%d project=%d status=%q — skip", issue.ID, issue.Project.ID, issue.Status.Name)
		w.WriteHeader(http.StatusNoContent)
		return
	}

	h.doCreateBranch(w, issue.Project.ID, issue.ID, issue.Summary, "", "webhook")
}

// GET /project-status?mantis_project_id=<id>
func (h *Handler) ProjectStatus(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Query().Get("mantis_project_id")
	if idStr == "" {
		jsonError(w, "mantis_project_id is required", http.StatusBadRequest)
		return
	}
	var projectID int
	if _, err := fmt.Sscan(idStr, &projectID); err != nil || projectID <= 0 {
		jsonError(w, "mantis_project_id must be a positive integer", http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")

	p, ok := h.providers[projectID]
	if !ok {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]any{
			"configured": false,
			"project_id": projectID,
			"error":      fmt.Sprintf("mantis project_id=%d is not mapped to any git repo", projectID),
		})
		return
	}

	json.NewEncoder(w).Encode(map[string]any{
		"configured": true,
		"project_id": projectID,
		"provider":   p.Name(),
	})
}

// POST /create-branch
type createBranchRequest struct {
	IssueID    int    `json:"issue_id"`
	ProjectID  int    `json:"project_id"`
	Summary    string `json:"summary"`
	BranchName string `json:"branch_name,omitempty"` // optional — ถ้าส่งมาใช้เลย ไม่ต้อง generate
}

func (h *Handler) CreateBranch(w http.ResponseWriter, r *http.Request) {
	var req createBranchRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "invalid json", http.StatusBadRequest)
		return
	}
	if req.IssueID <= 0 {
		jsonError(w, "issue_id required", http.StatusBadRequest)
		return
	}
	if req.ProjectID <= 0 {
		jsonError(w, "project_id required", http.StatusBadRequest)
		return
	}

	h.doCreateBranch(w, req.ProjectID, req.IssueID, req.Summary, req.BranchName, "button")
}

// ─────────────────────────────────────────
// Core logic
// ─────────────────────────────────────────

func (h *Handler) doCreateBranch(w http.ResponseWriter, projectID, issueID int, summary, customBranch, source string) {
	p, ok := h.providers[projectID]
	if !ok {
		log.Printf("[%s] mantis project_id=%d not found in project map", source, projectID)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusUnprocessableEntity)
		json.NewEncoder(w).Encode(map[string]any{
			"error":      fmt.Sprintf("mantis project_id=%d is not mapped to any git repo", projectID),
			"project_id": projectID,
		})
		return
	}

	baseBranch := h.baseBranches[projectID]
	// ถ้า user กำหนด branch name เองจาก modal → ใช้เลย, ไม่ต้อง generate
	branchName := customBranch
	if branchName == "" {
		branchName = buildBranchName(issueID, summary)
	}

	branch, alreadyExists, err := p.CreateBranch(branchName, baseBranch)
	if err != nil {
		log.Printf("[%s][%s] project=%d issue #%d error: %v", source, p.Name(), projectID, issueID, err)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]any{"error": err.Error()})
		return
	}

	w.Header().Set("Content-Type", "application/json")

	if alreadyExists {
		log.Printf("[%s][%s] project=%d issue #%d branch %q already exists", source, p.Name(), projectID, issueID, branchName)
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]any{
			"status":      "already_exists",
			"branch_name": branchName,
			"provider":    p.Name(),
		})
		return
	}

	log.Printf("[%s][%s] project=%d issue #%d branch %q created", source, p.Name(), projectID, issueID, branch.Name)
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]any{
		"status":      "created",
		"branch_name": branch.Name,
		"web_url":     branch.WebURL,
		"repo_url":    branch.RepoURL,
		"provider":    p.Name(),
	})
}

// ─────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────

// invalidBranchCharRe matches characters not allowed in branch names.
// \p{L} = Unicode letters, \p{M} = combining marks (Thai vowels/tone marks), \p{N} = digits
var invalidBranchCharRe = regexp.MustCompile(`[^\p{L}\p{M}\p{N}]+`)

func buildBranchName(issueID int, summary string) string {
	slug := slugify(summary)
	if slug == "" {
		return fmt.Sprintf("issue/%d", issueID)
	}
	return fmt.Sprintf("issue/%d-%s", issueID, slug)
}

func slugify(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	result := invalidBranchCharRe.ReplaceAllString(s, "-")
	result = strings.Trim(result, "-")
	runes := []rune(result)
	if len(runes) > 50 {
		result = string(runes[:50])
		// backtrack to last word boundary only when we cut mid-word
		if runes[50] != '-' {
			if idx := strings.LastIndexByte(result, '-'); idx > 20 {
				result = result[:idx]
			}
		}
	}
	return result
}

func (h *Handler) isTriggerStatus(status string) bool {
	for _, s := range h.cfg.TriggerStatuses {
		if s == status {
			return true
		}
	}
	return false
}

func jsonError(w http.ResponseWriter, msg string, code int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	json.NewEncoder(w).Encode(map[string]any{"error": msg})
}
