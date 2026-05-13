package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/igenco/git-bridge/internal/config"
	"github.com/igenco/git-bridge/internal/handler"
	"github.com/igenco/git-bridge/internal/middleware"
	"github.com/igenco/git-bridge/internal/provider"
	"github.com/igenco/git-bridge/internal/provider/github"
	"github.com/igenco/git-bridge/internal/provider/gitlab"
)

func main() {
	cfg := config.Load()

	var p provider.Provider
	switch cfg.Provider {
	case "gitlab":
		p = gitlab.NewClient(cfg.GitLabURL, cfg.GitLabToken, cfg.GitLabProjectID)
	case "github":
		p = github.NewClient(cfg.GitHubAPIURL, cfg.GitHubToken, cfg.GitHubOwner, cfg.GitHubRepo)
	}

	log.Printf("[git-bridge] provider=%s", cfg.Provider)

	h := handler.New(p, cfg)

	mux := http.NewServeMux()
	mux.Handle("POST /mantis-webhook",
		middleware.ValidateHMAC(cfg.WebhookSecret)(h.MantisWebhook),
	)
	mux.Handle("POST /create-branch",
		middleware.ValidateAPIToken(cfg.APIToken)(h.CreateBranch),
	)
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"status":"ok","provider":"` + cfg.Provider + `"}`))
	})

	srv := &http.Server{
		Addr:         ":" + cfg.Port,
		Handler:      middleware.Logger(mux),
		ReadTimeout:  10 * time.Second,
		WriteTimeout: 10 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	go func() {
		log.Printf("[git-bridge] listening on :%s", cfg.Port)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("[git-bridge] fatal: %v", err)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Println("[git-bridge] shutting down...")
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	srv.Shutdown(ctx)
}
