package gitlab

import (
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestCreateBranch_Created(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusCreated)
		w.Write([]byte(`{"name":"issue/42-test","web_url":"https://gitlab.example.com/group/repo/-/tree/issue/42-test"}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "token", "123")
	branch, exists, err := c.CreateBranch("issue/42-test", "main")

	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if exists {
		t.Fatal("expected exists=false")
	}
	if branch.Name != "issue/42-test" {
		t.Errorf("branch.Name = %q, want %q", branch.Name, "issue/42-test")
	}
	if branch.RepoURL == "" {
		t.Error("branch.RepoURL should not be empty")
	}
}

func TestCreateBranch_AlreadyExists(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusBadRequest)
		w.Write([]byte(`{"message":"Branch already exists"}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "token", "123")
	branch, exists, err := c.CreateBranch("issue/42-test", "main")

	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if !exists {
		t.Fatal("expected exists=true")
	}
	if branch != nil {
		t.Error("expected branch=nil")
	}
}

func TestCreateBranch_Unauthorized(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "token", "123")
	_, _, err := c.CreateBranch("issue/42-test", "main")

	if err == nil {
		t.Fatal("expected error for 401")
	}
}

func TestCreateBranch_NotFound(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotFound)
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "token", "999")
	_, _, err := c.CreateBranch("issue/42-test", "main")

	if err == nil {
		t.Fatal("expected error for 404")
	}
}

func TestRepoURLFromGitLab(t *testing.T) {
	tests := []struct {
		webURL     string
		branchName string
		want       string
	}{
		{
			"https://gitlab.example.com/group/repo/-/tree/issue/42-test",
			"issue/42-test",
			"https://gitlab.example.com/group/repo",
		},
		{
			"https://gitlab.com/org/project/-/tree/main",
			"main",
			"https://gitlab.com/org/project",
		},
	}

	for _, tt := range tests {
		got := repoURLFromGitLab(tt.webURL, tt.branchName)
		if got != tt.want {
			t.Errorf("repoURLFromGitLab(%q, %q) = %q, want %q", tt.webURL, tt.branchName, got, tt.want)
		}
	}
}
