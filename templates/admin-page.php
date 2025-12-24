<?php
/**
 * Admin Page Template
 *
 * @package BPAS
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'bpas_settings', [] );
?>

<style>
    /* Fallback styles in case CSS file doesn't load */
    #bpasAffiliateSubmit .bpas-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ccc; }
    #bpasAffiliateSubmit .bpas-tab { padding: 10px 20px; text-decoration: none; color: #666; border-bottom: 3px solid transparent; }
    #bpasAffiliateSubmit .bpas-tab.active { color: #146EF5; border-bottom-color: #146EF5; font-weight: bold; }
    #bpasAffiliateSubmit .bpas-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
    #bpasAffiliateSubmit .bpas-form-group { margin-bottom: 15px; }
    #bpasAffiliateSubmit .bpas-form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    #bpasAffiliateSubmit .bpas-form-group input { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    #bpasAffiliateSubmit .bpas-btn { background: #146EF5; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
</style>

<div id="bpasAffiliateSubmit" class="wrap">
	<div class="bpas-header">
		<div>
			<h1><?php esc_html_e( 'Freemius Affiliate Submit', 'affiliate-submit' ); ?></h1>
			<span class="bpas-version">v<?php echo esc_html( \BPAS\Admin::VERSION ); ?></span>
			<?php if ( class_exists( 'Freemius_Api' ) ) : 
				$reflector = new ReflectionClass( 'Freemius_Api' );
				$version = defined( 'Freemius_Api_Base::VERSION' ) ? Freemius_Api_Base::VERSION : 'unknown';
			?>
				<div style="font-size: 10px; color: #999; margin-top: 5px;">
					SDK v<?php echo esc_html( $version ); ?> loaded from: <?php echo esc_html( basename( dirname( dirname( $reflector->getFileName() ) ) ) ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="bpas-tabs">
		<a href="?page=bpas-affiliate-submit&tab=submit" class="bpas-tab <?php echo $active_tab === 'submit' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Submit Affiliate', 'affiliate-submit' ); ?>
		</a>
		<a href="?page=bpas-affiliate-submit&tab=settings" class="bpas-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'affiliate-submit' ); ?>
		</a>
	</div>

	<?php if ( $active_tab === 'settings' ) : ?>
		<!-- Settings Tab -->
		<div class="bpas-card">
			<div class="bpas-card-header">
				<h2><?php esc_html_e( 'Freemius API Credentials', 'affiliate-submit' ); ?></h2>
			</div>
			<div class="bpas-card-body">
				<form method="post" action="options.php">
					<?php settings_fields( 'bpas_settings' ); ?>
					
					<div class="bpas-form-group">
						<label for="bpas-dev-id"><?php esc_html_e( 'Developer ID', 'affiliate-submit' ); ?></label>
						<input type="text" id="bpas-dev-id" name="bpas_settings[dev_id]" 
						       value="<?php echo esc_attr( $settings['dev_id'] ?? '' ); ?>" required>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-public-key"><?php esc_html_e( 'Public Key', 'affiliate-submit' ); ?></label>
						<input type="text" id="bpas-public-key" name="bpas_settings[public_key]" 
						       value="<?php echo esc_attr( $settings['public_key'] ?? '' ); ?>" required>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-secret-key"><?php esc_html_e( 'Secret Key', 'affiliate-submit' ); ?></label>
						<input type="password" id="bpas-secret-key" name="bpas_settings[secret_key]" 
						       value="<?php echo esc_attr( $settings['secret_key'] ?? '' ); ?>" required>
					</div>

					<button type="submit" class="bpas-btn">
						<?php esc_html_e( 'Save Settings', 'affiliate-submit' ); ?>
					</button>

					<button type="button" id="bpas-test-connection" class="bpas-btn bpas-btn-secondary" style="margin-left: 10px;">
						<?php esc_html_e( 'Test Connection', 'affiliate-submit' ); ?>
					</button>

					<button type="button" id="bpas-clear-sync" class="bpas-btn bpas-btn-secondary" style="margin-left: 10px; border-color: #ccc; color: #666;">
						<?php esc_html_e( 'Clear Clock Sync', 'affiliate-submit' ); ?>
					</button>

					<span id="bpas-test-spinner" class="spinner" style="float: none; margin-left: 5px;"></span>
					<div id="bpas-test-result" style="margin-top: 15px;"></div>
				</form>
			</div>
		</div>

	<?php else : ?>
		<!-- Submit Tab -->
		<?php if ( empty( $settings['dev_id'] ) || empty( $settings['public_key'] ) || empty( $settings['secret_key'] ) ) : ?>
			<div class="bpas-alert error">
				<?php esc_html_e( 'Please configure API credentials in the Settings tab first.', 'affiliate-submit' ); ?>
			</div>
		<?php else : ?>
			
			<div class="bpas-card">
				<div class="bpas-card-header">
					<h2><?php esc_html_e( 'Affiliate Details', 'affiliate-submit' ); ?></h2>
				</div>
				<div class="bpas-card-body">
					<div class="bpas-form-group">
						<label for="bpas-email"><?php esc_html_e( 'Affiliate Email', 'affiliate-submit' ); ?> *</label>
						<input type="email" id="bpas-email" required>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-name"><?php esc_html_e( 'Affiliate Name', 'affiliate-submit' ); ?> *</label>
						<input type="text" id="bpas-name" required>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-domain"><?php esc_html_e( 'Domain', 'affiliate-submit' ); ?></label>
						<input type="text" id="bpas-domain" placeholder="example.com">
						<p class="bpas-description"><?php esc_html_e( 'The primary domain where the affiliate will promote your products', 'affiliate-submit' ); ?></p>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-paypal-email"><?php esc_html_e( 'PayPal Email', 'affiliate-submit' ); ?></label>
						<input type="email" id="bpas-paypal-email" placeholder="paypal@email.com">
					</div>

					<div class="bpas-form-group">
						<label for="bpas-additional-domains"><?php esc_html_e( 'Additional Domains', 'affiliate-submit' ); ?></label>
						<input type="text" id="bpas-additional-domains" placeholder="affiliate-2nd-site.com, affiliate-3rd-site.com">
						<p class="bpas-description"><?php esc_html_e( 'Comma-separated list of additional domains.', 'affiliate-submit' ); ?></p>
					</div>

					<div class="bpas-form-group">
						<label><?php esc_html_e( 'Promotional Methods', 'affiliate-submit' ); ?></label>
						<div style="display: flex; flex-direction: column; gap: 8px; margin-top: 5px;">
							<label style="font-weight: normal; display: flex; align-items: center; gap: 8px;">
								<input type="checkbox" class="bpas-promotional-method" value="social_media">
								<?php esc_html_e( 'Social Media', 'affiliate-submit' ); ?>
							</label>
							<label style="font-weight: normal; display: flex; align-items: center; gap: 8px;">
								<input type="checkbox" class="bpas-promotional-method" value="mobile_apps">
								<?php esc_html_e( 'Mobile Apps', 'affiliate-submit' ); ?>
							</label>
						</div>
						<p class="bpas-description"><?php esc_html_e( 'Select the methods the affiliate will use to promote your products.', 'affiliate-submit' ); ?></p>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-stats-description"><?php esc_html_e( 'Stats Description', 'affiliate-submit' ); ?></label>
						<textarea id="bpas-stats-description" rows="3" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
						<p class="bpas-description"><?php esc_html_e( 'Optional: Stats data about reach (e.g., monthly PVs, followers).', 'affiliate-submit' ); ?></p>
					</div>

					<div class="bpas-form-group">
						<label for="bpas-promotion-method-description"><?php esc_html_e( 'Promotion Method Description', 'affiliate-submit' ); ?></label>
						<textarea id="bpas-promotion-method-description" rows="3" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
						<p class="bpas-description"><?php esc_html_e( 'Optional: Explain how the affiliate plans to promote your products.', 'affiliate-submit' ); ?></p>
					</div>
				</div>
			</div>

			<div class="bpas-card">
				<div class="bpas-card-header">
					<h2><?php esc_html_e( 'Select Products', 'affiliate-submit' ); ?></h2>
				</div>
				<div class="bpas-card-body">
					
					<div id="bpas-loading" class="bpas-loading">
						<div class="bpas-spinner"></div>
						<span><?php esc_html_e( 'Loading products...', 'affiliate-submit' ); ?></span>
					</div>

					<div id="bpas-filters" class="bpas-filters" style="display: none;">
						<div class="bpas-filter-group">
							<label><?php esc_html_e( 'Store:', 'affiliate-submit' ); ?></label>
							<select id="bpas-filter-store">
								<option value=""><?php esc_html_e( 'All Stores', 'affiliate-submit' ); ?></option>
							</select>
						</div>
						<div class="bpas-filter-group">
							<label><?php esc_html_e( 'Type:', 'affiliate-submit' ); ?></label>
							<select id="bpas-filter-type">
								<option value=""><?php esc_html_e( 'All Types', 'affiliate-submit' ); ?></option>
								<option value="free"><?php esc_html_e( 'Free Only', 'affiliate-submit' ); ?></option>
								<option value="premium"><?php esc_html_e( 'Premium Only', 'affiliate-submit' ); ?></option>
								<option value="has_term_id"><?php esc_html_e( 'Has Term ID', 'affiliate-submit' ); ?></option>
								<option value="no_term_id"><?php esc_html_e( 'Has No Term ID', 'affiliate-submit' ); ?></option>
							</select>
						</div>
						<button type="button" id="bpas-reset-filters" class="bpas-btn bpas-btn-secondary bpas-btn-small">
							<?php esc_html_e( 'Reset Filters', 'affiliate-submit' ); ?>
						</button>
					</div>

					<table id="bpas-plugins-table" class="bpas-table" style="display: none;">
						<thead>
							<tr>
								<th style="width: 50px;">
									<input type="checkbox" id="bpas-select-all">
								</th>
								<th class="bpas-sortable" data-sort="id" style="width: 80px;">
									<?php esc_html_e( 'ID', 'affiliate-submit' ); ?>
									<span class="dashicons dashicons-sort"></span>
								</th>
								<th class="bpas-sortable" data-sort="name">
									<?php esc_html_e( 'Product Name', 'affiliate-submit' ); ?>
									<span class="dashicons dashicons-sort"></span>
								</th>
								<th style="width: 120px;"><?php esc_html_e( 'Affiliation', 'affiliate-submit' ); ?></th>
								<th style="width: 200px;"><?php esc_html_e( 'Terms ID', 'affiliate-submit' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Status', 'affiliate-submit' ); ?></th>
							</tr>
						</thead>
						<tbody id="bpas-plugins-tbody"></tbody>
					</table>

					<div id="bpas-log" class="bpas-log" style="display: none; margin-top: 20px;"></div>

					<div style="margin-top: 20px;">
						<button type="button" id="bpas-submit-btn" class="bpas-btn bpas-btn-success">
							<?php esc_html_e( 'Add to Selected Products', 'affiliate-submit' ); ?>
						</button>
					</div>
				</div>
			</div>

		<?php endif; ?>
	<?php endif; ?>
</div>
