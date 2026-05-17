.PHONY: build-free build-pro build-all clean

PLUGIN_NAME    := clandevs-smart-catalog-filters
PRO_PLUGIN_NAME := clandevs-smart-catalog-filters-pro
BUILD_DIR      := .build/free
PRO_BUILD_DIR  := .build/pro

# ──────────────────────────────────────────────
# Build free plugin zip for WordPress.org
# ──────────────────────────────────────────────
build-free:
	@echo "Building free plugin zip..."
	@rm -rf $(BUILD_DIR) $(PLUGIN_NAME).zip
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	@rsync -a --exclude-from='.distignore' --exclude='pro/' --exclude='.claude/' --exclude='Makefile' . $(BUILD_DIR)/$(PLUGIN_NAME)/
	@cd $(BUILD_DIR) && zip -r ../../$(PLUGIN_NAME).zip $(PLUGIN_NAME)/
	@rm -rf $(BUILD_DIR)
	@echo "Done: $(PLUGIN_NAME).zip ($$(du -h $(PLUGIN_NAME).zip | cut -f1))"

# ──────────────────────────────────────────────
# Build Pro plugin zip for Freemius
# ──────────────────────────────────────────────
build-pro:
	@echo "Building Pro plugin zip..."
	@rm -rf $(PRO_BUILD_DIR) $(PRO_PLUGIN_NAME).zip
	@mkdir -p $(PRO_BUILD_DIR)/$(PRO_PLUGIN_NAME)
	@rsync -a pro/ $(PRO_BUILD_DIR)/$(PRO_PLUGIN_NAME)/ --exclude='.git' --exclude='.gitignore'
	@cd $(PRO_BUILD_DIR) && zip -r ../../$(PRO_PLUGIN_NAME).zip $(PRO_PLUGIN_NAME)/
	@rm -rf $(PRO_BUILD_DIR)
	@echo "Done: $(PRO_PLUGIN_NAME).zip ($$(du -h $(PRO_PLUGIN_NAME).zip | cut -f1))"

# ──────────────────────────────────────────────
# Build both
# ──────────────────────────────────────────────
build-all: build-free build-pro

# ──────────────────────────────────────────────
# Clean build artifacts
# ──────────────────────────────────────────────
clean:
	@rm -rf .build $(PLUGIN_NAME).zip $(PRO_PLUGIN_NAME).zip
	@echo "Cleaned."
