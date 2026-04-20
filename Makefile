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
	@echo "Enabling Radix and subtheme..."
	ddev drush en radix --yes
	ddev drush theme:enable YOUR_SUBTHEME_NAME --yes
	ddev drush config:set system.theme default YOUR_SUBTHEME_NAME --yes
	@echo "Clearing caches..."
	ddev drush cr
	@echo ""
	@echo "✅ Done! Visit: https://drupal11-base.ddev.site"
