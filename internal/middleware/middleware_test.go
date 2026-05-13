package middleware

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func okHandler(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
}

// ─────────────────────────────────────────
// ValidateHMAC
// ─────────────────────────────────────────

func sign(secret, body string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(body))
	return "sha256=" + hex.EncodeToString(mac.Sum(nil))
}

func TestValidateHMAC_Valid(t *testing.T) {
	body := `{"issue":{"id":1}}`
	secret := "topsecret"

	req := httptest.NewRequest("POST", "/", strings.NewReader(body))
	req.Header.Set("X-Hub-Signature-256", sign(secret, body))
	w := httptest.NewRecorder()

	ValidateHMAC(secret)(okHandler).ServeHTTP(w, req)

	if w.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", w.Code)
	}
}

func TestValidateHMAC_InvalidSignature(t *testing.T) {
	req := httptest.NewRequest("POST", "/", strings.NewReader(`{}`))
	req.Header.Set("X-Hub-Signature-256", "sha256=badhash")
	w := httptest.NewRecorder()

	ValidateHMAC("secret")(okHandler).ServeHTTP(w, req)

	if w.Code != http.StatusUnauthorized {
		t.Errorf("expected 401, got %d", w.Code)
	}
}

func TestValidateHMAC_MissingHeader(t *testing.T) {
	req := httptest.NewRequest("POST", "/", strings.NewReader(`{}`))
	w := httptest.NewRecorder()

	ValidateHMAC("secret")(okHandler).ServeHTTP(w, req)

	if w.Code != http.StatusUnauthorized {
		t.Errorf("expected 401, got %d", w.Code)
	}
}

// ─────────────────────────────────────────
// ValidateAPIToken
// ─────────────────────────────────────────

func TestValidateAPIToken_Valid(t *testing.T) {
	req := httptest.NewRequest("POST", "/", strings.NewReader(`{}`))
	req.Header.Set("X-Api-Token", "mytoken")
	w := httptest.NewRecorder()

	ValidateAPIToken("mytoken")(okHandler).ServeHTTP(w, req)

	if w.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", w.Code)
	}
}

func TestValidateAPIToken_Invalid(t *testing.T) {
	req := httptest.NewRequest("POST", "/", strings.NewReader(`{}`))
	req.Header.Set("X-Api-Token", "wrongtoken")
	w := httptest.NewRecorder()

	ValidateAPIToken("mytoken")(okHandler).ServeHTTP(w, req)

	if w.Code != http.StatusUnauthorized {
		t.Errorf("expected 401, got %d", w.Code)
	}
}

func TestValidateAPIToken_Missing(t *testing.T) {
	req := httptest.NewRequest("POST", "/", strings.NewReader(`{}`))
	w := httptest.NewRecorder()

	ValidateAPIToken("mytoken")(okHandler).ServeHTTP(w, req)

	if w.Code != http.StatusUnauthorized {
		t.Errorf("expected 401, got %d", w.Code)
	}
}
