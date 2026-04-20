.PHONY: install start stop clean reinstall

install:
	@echo "Starting DDEV..."
	ddev start
	@echo "Installing Composer dependencies..."
	ddev composer install
	@echo "Importing database..."
	ddev import-db --file=./database/base.sql.gz
	@echo "Clearing caches..."
	ddev drush cr
	@echo ""
	@echo "✅ Done! Visit: https://drupal11-base.ddev.site"

start:
	ddev start

stop:
	ddev stop

clean:
	ddev delete --omit-snapshot --yes
	rm -rf vendor web/core web/modules/contrib web/themes/contrib

reinstall: clean install
