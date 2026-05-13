package handler

import (
	"testing"

	"github.com/abcprintf/git-bridge/internal/config"
)

func TestSlugify(t *testing.T) {
	tests := []struct {
		name  string
		input string
		want  string
	}{
		{"ascii normal", "Fix login bug", "fix-login-bug"},
		{"thai only", "แก้ไขปัญหา", "แก้ไขปัญหา"},
		{"thai mixed ascii", "แก้ไขการ Login", "แก้ไขการ-login"},
		{"empty", "", ""},
		{"spaces only", "   ", ""},
		{"trim spaces", "  hello world  ", "hello-world"},
		{"special chars", "feat!@# special", "feat-special"},
		{"multiple separators", "a---b", "a-b"},
		{"leading trailing dash", "-hello-", "hello"},
		{"numbers", "issue 42 fix", "issue-42-fix"},
		{"long ascii truncated at 50 runes", "this-is-a-very-long-branch-name-that-exceeds-fifty-characters-limit", "this-is-a-very-long-branch-name-that-exceeds-fifty"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := slugify(tt.input)
			if got != tt.want {
				t.Errorf("slugify(%q) = %q, want %q", tt.input, got, tt.want)
			}
		})
	}
}

func TestBuildBranchName(t *testing.T) {
	tests := []struct {
		name    string
		issueID int
		summary string
		want    string
	}{
		{"ascii summary", 42, "Fix login bug", "issue/42-fix-login-bug"},
		{"thai summary", 10, "แก้ไขปัญหาระบบ", "issue/10-แก้ไขปัญหาระบบ"},
		{"empty summary falls back", 7, "", "issue/7"},
		{"whitespace summary falls back", 3, "   ", "issue/3"},
		{"special chars in summary", 99, "Bug: null pointer!", "issue/99-bug-null-pointer"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := buildBranchName(tt.issueID, tt.summary)
			if got != tt.want {
				t.Errorf("buildBranchName(%d, %q) = %q, want %q", tt.issueID, tt.summary, got, tt.want)
			}
		})
	}
}

func TestIsTriggerStatus(t *testing.T) {
	h := &Handler{
		cfg: &config.Config{
			TriggerStatuses: []string{"assigned", "in progress"},
		},
	}

	tests := []struct {
		status string
		want   bool
	}{
		{"assigned", true},
		{"in progress", true},
		{"resolved", false},
		{"new", false},
		{"", false},
	}

	for _, tt := range tests {
		got := h.isTriggerStatus(tt.status)
		if got != tt.want {
			t.Errorf("isTriggerStatus(%q) = %v, want %v", tt.status, got, tt.want)
		}
	}
}
