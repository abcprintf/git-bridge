package github

import (
	"net/http"
	"net/http/httptest"
	"testing"
)

// mockGitHub สร้าง test server ที่ตอบ GET (getRefSHA) และ POST (createRef)
func mockGitHub(getSHA int, getSHABody string, postStatus int, postBody string) *httptest.Server {
	return httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.WriteHeader(getSHA)
			w.Write([]byte(getSHABody))
		} else {
			w.WriteHeader(postStatus)
			w.Write([]byte(postBody))
		}
	}))
}

func TestCreateBranch_Created(t *testing.T) {
	srv := mockGitHub(
		http.StatusOK, `{"object":{"sha":"abc123def456"}}`,
		http.StatusCreated, `{"ref":"refs/heads/issue/42-test"}`,
	)
	defer srv.Close()

	c := NewClient(srv.URL, "token", "owner", "repo")
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
	if branch.WebURL == "" {
		t.Error("branch.WebURL should not be empty")
	}
}

func TestCreateBranch_AlreadyExists(t *testing.T) {
	srv := mockGitHub(
		http.StatusOK, `{"object":{"sha":"abc123"}}`,
		http.StatusUnprocessableEntity, `{"message":"Reference already exists","errors":[{"code":"already_exists"}]}`,
	)
	defer srv.Close()

	c := NewClient(srv.URL, "token", "owner", "repo")
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

func TestCreateBranch_BaseBranchNotFound(t *testing.T) {
	srv := mockGitHub(http.StatusNotFound, `{}`, 0, ``)
	defer srv.Close()

	c := NewClient(srv.URL, "token", "owner", "repo")
	_, _, err := c.CreateBranch("issue/42-test", "nonexistent")

	if err == nil {
		t.Fatal("expected error when base branch not found")
	}
}

func TestCreateBranch_Unauthorized(t *testing.T) {
	srv := mockGitHub(
		http.StatusOK, `{"object":{"sha":"abc123"}}`,
		http.StatusUnauthorized, `{"message":"Bad credentials"}`,
	)
	defer srv.Close()

	c := NewClient(srv.URL, "token", "owner", "repo")
	_, _, err := c.CreateBranch("issue/42-test", "main")

	if err == nil {
		t.Fatal("expected error for 401")
	}
}
