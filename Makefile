.PHONY: install start stop clean reinstall

install:
	@echo "Starting DDEV..."
	ddev start
	@echo "Installing Composer dependencies..."
	ddev composer install
	@echo "Installing Drupal..."
	ddev drush site:install standard \
		--account-name=admin \
		--account-pass=admin \
		--site-name="Drupal 11 Base" \
		--db-url=mysql://db:db@db/db \
		--yes
	@echo "Enabling custom modules..."
	ddev drush en data_source datatables --yes
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
