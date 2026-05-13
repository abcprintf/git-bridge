package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/abcprintf/git-bridge/internal/config"
	"github.com/abcprintf/git-bridge/internal/factory"
	"github.com/abcprintf/git-bridge/internal/handler"
	"github.com/abcprintf/git-bridge/internal/middleware"
)

func main() {
	cfg := config.Load()

	// โหลด project mapping จาก projects.json
	projects, err := config.LoadProjects(cfg.ProjectsFile)
	if err != nil {
		log.Fatalf("[git-bridge] load projects: %v", err)
	}
	log.Printf("[git-bridge] loaded %d project mapping(s) from %s", len(projects), cfg.ProjectsFile)

	// สร้าง provider map
	providers, err := factory.BuildProviderMap(projects)
	if err != nil {
		log.Fatalf("[git-bridge] build providers: %v", err)
	}

	// Log สรุป mapping
	for id, p := range providers {
		log.Printf("[git-bridge]   mantis project %d → %s", id, p.Name())
	}

	h := handler.New(providers, projects, cfg)

	mux := http.NewServeMux()
	mux.Handle("POST /mantis-webhook",
		middleware.ValidateHMAC(cfg.WebhookSecret)(h.MantisWebhook),
	)
	mux.Handle("POST /create-branch",
		middleware.ValidateAPIToken(cfg.APIToken)(h.CreateBranch),
	)
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"status":"ok","projects":` + itoa(len(projects)) + `}`))
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

func itoa(n int) string {
	return fmt.Sprintf("%d", n)
}
