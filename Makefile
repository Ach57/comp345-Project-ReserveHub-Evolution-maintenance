# ReserveHub — Local Development Helpers
#
# Usage:
#   make setup      One-time setup for a new developer machine
#   make check      Verify the environment is healthy
#   make db-reset   Re-import schema + seed data (destructive)
#   make open       Open the app in the browser
#
# Environment overrides (prefix any target):
#   XAMPP_HTDOCS=/opt/lampp/htdocs make setup
#   DB_PASS=secret make setup

SHELL := /usr/bin/env bash
.DEFAULT_GOAL := help

SCRIPTS_DIR := scripts

.PHONY: setup db-reset check sync open help

setup: ## One-time setup: copy repo to htdocs + api/db.php + DB import
	@chmod +x $(SCRIPTS_DIR)/setup.sh
	@$(SCRIPTS_DIR)/setup.sh

db-reset: ## Re-import sql/local_setup.sql — resets ALL data (prompts for confirmation)
	@chmod +x $(SCRIPTS_DIR)/db-reset.sh
	@$(SCRIPTS_DIR)/db-reset.sh

check: ## Verify XAMPP, htdocs copy, and DB connection
	@chmod +x $(SCRIPTS_DIR)/check.sh
	@$(SCRIPTS_DIR)/check.sh

sync: ## Push local changes to htdocs (run after editing files)
	@rsync -a --delete --exclude='.git' . "$${XAMPP_HTDOCS:-/Applications/XAMPP/xamppfiles/htdocs}/reservehub/"

open: ## Open http://localhost/reservehub in the default browser
	@open http://localhost/reservehub

help: ## Show this help
	@awk 'BEGIN { FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n\nTargets:\n" } \
	     /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2 }' \
	     $(MAKEFILE_LIST)
	@echo ""
