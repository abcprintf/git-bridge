package handler

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"regexp"
	"strings"
	"unicode"

	"github.com/igenco/git-bridge/internal/config"
	"github.com/igenco/git-bridge/internal/provider"
)

type Handler struct {
	provider provider.Provider
	cfg      *config.Config
}

func New(p provider.Provider, cfg *config.Config) *Handler {
	return &Handler{provider: p, cfg: cfg}
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
		log.Printf("[webhook] issue #%d status=%q — skip", issue.ID, issue.Status.Name)
		w.WriteHeader(http.StatusNoContent)
		return
	}

	h.doCreateBranch(w, issue.ID, issue.Summary, "webhook")
}

// POST /create-branch
type createBranchRequest struct {
	IssueID int    `json:"issue_id"`
	Summary string `json:"summary"`
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

	h.doCreateBranch(w, req.IssueID, req.Summary, "button")
}

// ─────────────────────────────────────────
// Core logic — shared ระหว่าง webhook และ button
// ─────────────────────────────────────────

func (h *Handler) doCreateBranch(w http.ResponseWriter, issueID int, summary, source string) {
	branchName := buildBranchName(issueID, summary)

	branch, alreadyExists, err := h.provider.CreateBranch(branchName, h.cfg.BaseBranch)
	if err != nil {
		log.Printf("[%s][%s] issue #%d error: %v", source, h.provider.Name(), issueID, err)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]any{"error": err.Error()})
		return
	}

	w.Header().Set("Content-Type", "application/json")

	if alreadyExists {
		log.Printf("[%s][%s] issue #%d branch %q already exists", source, h.provider.Name(), issueID, branchName)
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]any{
			"status":      "already_exists",
			"branch_name": branchName,
			"provider":    h.provider.Name(),
		})
		return
	}

	log.Printf("[%s][%s] issue #%d branch %q created", source, h.provider.Name(), issueID, branch.Name)
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]any{
		"status":      "created",
		"branch_name": branch.Name,
		"web_url":     branch.WebURL,
		"provider":    h.provider.Name(),
	})
}

// ─────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────

var nonAlphanumRe = regexp.MustCompile(`[^a-z0-9]+`)

func buildBranchName(issueID int, summary string) string {
	slug := slugify(summary)
	if slug == "" {
		return fmt.Sprintf("issue/%d", issueID)
	}
	return fmt.Sprintf("issue/%d-%s", issueID, slug)
}

func slugify(s string) string {
	var b strings.Builder
	for _, r := range strings.ToLower(s) {
		if r < unicode.MaxASCII {
			b.WriteRune(r)
		}
	}
	result := nonAlphanumRe.ReplaceAllString(b.String(), "-")
	result = strings.Trim(result, "-")
	if len(result) > 50 {
		result = result[:50]
		if idx := strings.LastIndex(result, "-"); idx > 20 {
			result = result[:idx]
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
