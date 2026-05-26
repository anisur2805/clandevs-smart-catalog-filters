.PHONY: build-free clean

PLUGIN_NAME    := clandevs-smart-catalog-filters
BUILD_DIR      := .build/free

# ──────────────────────────────────────────────
# Build free plugin zip for WordPress.org
# ──────────────────────────────────────────────
build-free:
	@echo "Building free plugin zip..."
	@rm -rf $(BUILD_DIR) $(PLUGIN_NAME).zip
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	@rsync -a --exclude-from='.distignore' --exclude='.claude/' --exclude='Makefile' . $(BUILD_DIR)/$(PLUGIN_NAME)/
	@cd $(BUILD_DIR) && zip -r ../../$(PLUGIN_NAME).zip $(PLUGIN_NAME)/
	@rm -rf $(BUILD_DIR)
	@echo "Done: $(PLUGIN_NAME).zip ($$(du -h $(PLUGIN_NAME).zip | cut -f1))"

# ──────────────────────────────────────────────
# Clean build artifacts
# ──────────────────────────────────────────────
clean:
	@rm -rf .build $(PLUGIN_NAME).zip
	@echo "Cleaned."
