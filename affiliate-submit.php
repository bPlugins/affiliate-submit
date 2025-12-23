<?php
/**
 * Plugin Name: Affiliate Submit
 * Description: Bulk submit affiliate applications to all Freemius products.
 * Version: 1.4.8
 * Author: Antigravity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require Freemius SDK
require_once plugin_dir_path( __FILE__ ) . 'freemius-php-sdk/freemius/Freemius.php';

class FS_Affiliate_Submit {

	private $option_name = 'fs_as_settings';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_fs_as_add_affiliate', [ $this, 'ajax_add_affiliate' ] );
        add_action( 'wp_ajax_fs_as_fetch_plugins', [ $this, 'ajax_fetch_plugins' ] );
	}

	public function add_menu() {
		add_menu_page(
			'Affiliate Submit',
			'Affiliate Submit',
			'read',
			'fs-affiliate-submit-tool',
			[ $this, 'render_page' ],
			'dashicons-groups'
		);
	}

	public function register_settings() {
		register_setting( $this->option_name, $this->option_name );
	}

	public function render_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'submit';
		?>
		<div class="wrap">
			<h1>Freemius Affiliate Submit</h1>
            <?php settings_errors(); ?>
			<h2 class="nav-tab-wrapper">
				<a href="?page=fs-affiliate-submit-tool&tab=submit" class="nav-tab <?php echo $active_tab == 'submit' ? 'nav-tab-active' : ''; ?>">Submit Affiliate</a>
				<a href="?page=fs-affiliate-submit-tool&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
			</h2>

			<?php
			if ( $active_tab == 'settings' ) {
				$this->render_settings();
			} else {
				$this->render_submit();
			}
			?>
		</div>
		<?php
	}

	private function render_settings() {
		$options = get_option( $this->option_name );
        
        if ( isset( $_POST['test_connection'] ) ) {
            $this->test_connection( $_POST, $options );
        }
		?>
		<form method="post" action="options.php">
			<?php settings_fields( $this->option_name ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Developer ID</th>
					<td><input type="text" name="<?php echo $this->option_name; ?>[dev_id]" value="<?php echo esc_attr( $options['dev_id'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Public Key</th>
					<td><input type="text" name="<?php echo $this->option_name; ?>[public_key]" value="<?php echo esc_attr( $options['public_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Secret Key</th>
					<td><input type="password" name="<?php echo $this->option_name; ?>[secret_key]" value="<?php echo esc_attr( $options['secret_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Affiliate Program Terms ID</th>
					<td>
						<input type="text" name="<?php echo $this->option_name; ?>[terms_id]" value="<?php echo esc_attr( $options['terms_id'] ?? '' ); ?>" class="regular-text" />
						<p class="description">Optional: Enter the default Affiliate Program Terms ID. Find this in your Freemius Dashboard → Product → Affiliation (first tab). Leave empty to use default value of 1.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
        
        <form method="post" style="margin-top: 20px;">
            <h3>Test Connection</h3>
            <p>Click below to verify your credentials by fetching your product list.</p>
            <input type="hidden" name="test_connection" value="1" />
            <?php submit_button( 'Test Connection', 'secondary', 'test_connection', false ); ?>
        </form>
		<?php
	}

    private function test_connection( $data, $saved_options ) {
        $dev_id = trim( $saved_options['dev_id'] ?? '' );
        $pub = trim( $saved_options['public_key'] ?? '' );
        $sec = trim( $saved_options['secret_key'] ?? '' );

        if ( ! $dev_id || ! $pub || ! $sec ) {
            echo '<div class="notice notice-error"><p>Please save credentials first.</p></div>';
            return;
        }

        if ( ! is_numeric( $dev_id ) ) {
             echo '<div class="notice notice-error"><p>Error: Developer ID must be a number.</p></div>';
             return;
        }

        try {
            $client = new Freemius_Api( 'developer', $dev_id, $pub, $sec );
            
            // Debug: Show what path is being generated
            $path = '/plugins.json';
            $full_path = $client->CanonizePath( $path );
            echo '<div class="notice notice-info"><p>Debug: Requesting ' . esc_html( $full_path ) . '</p></div>';
            
            $response = $client->Api( $path );

            if ( isset( $response->error ) ) {
                echo '<div class="notice notice-error"><p>Connection Failed: ' . esc_html( $response->error->message ?? 'Unknown API Error' ) . '</p></div>';
                if ( isset( $response->error->code ) ) {
                     echo '<p>Error Code: ' . esc_html( $response->error->code ) . '</p>';
                }
            } elseif ( isset( $response->plugins ) || is_array( $response ) ) {
                $products = isset($response->plugins) ? $response->plugins : $response;
                $count = count( $products );
                echo '<div class="notice notice-success"><p>Connection Successful! Found ' . $count . ' plugins.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>Connection made, but unexpected response format.</p></div>';
                echo '<pre>' . esc_html( print_r( $response, true ) ) . '</pre>';
            }
        } catch ( Exception $e ) {
            echo '<div class="notice notice-error"><p>SDK Error: ' . esc_html( $e->getMessage() ) . '</p></div>';
        }
    }

	private function render_submit() {
		$options = get_option( $this->option_name );
		if ( empty( $options['dev_id'] ) || empty( $options['public_key'] ) || empty( $options['secret_key'] ) ) {
			echo '<div class="notice notice-error"><p>Please configure API credentials in the Settings tab first.</p></div>';
			return;
		}

		?>
		<form id="fs-affiliate-form" method="post">
			<?php wp_nonce_field( 'fs_as_submit' ); ?>
            
            <h3>Affiliate Details</h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Affiliate Email</th>
					<td><input type="email" id="aff-email" name="email" required class="regular-text" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Affiliate Name</th>
					<td><input type="text" id="aff-name" name="name" required class="regular-text" /></td>
				</tr>
                <tr valign="top">
					<th scope="row">Domain (Optional)</th>
					<td><input type="text" id="aff-domain" name="domain" class="regular-text" placeholder="e.g. example.com" /></td>
				</tr>
			</table>

            <h3>Select Products</h3>
            <p>Select the products you want to add this affiliate to.</p>
            
            <div id="plugin-list-loading">
                <p><span class="spinner is-active" style="float:none;"></span> Loading products...</p>
            </div>
            
            <div id="debug-container" style="display:none; margin-bottom:10px;"></div>

            <div id="filter-controls" style="display:none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                <strong>Filters:</strong>
                <label style="margin-left: 15px;">
                    Store:
                    <select id="filter-store" style="margin-left: 5px;">
                        <option value="">All Stores</option>
                    </select>
                </label>
                <label style="margin-left: 15px;">
                    Type:
                    <select id="filter-premium" style="margin-left: 5px;">
                        <option value="">All Types</option>
                        <option value="free">Free Only</option>
                        <option value="premium">Premium Only</option>
                    </select>
                </label>
                <button type="button" id="reset-filters" class="button button-small" style="margin-left: 15px;">Reset Filters</button>
            </div>

            <table class="wp-list-table widefat fixed striped" id="plugin-list-table" style="display:none;">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                        <th>Product Name</th>
                        <th>ID</th>
                        <th>Affiliation Status</th>
                        <th style="width: 180px;">Terms ID</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody id="plugin-list-body">
                    <!-- Plugins will be loaded here via AJAX -->
                </tbody>
            </table>

            <div id="submission-log" style="margin-top: 20px; padding: 10px; background: #f0f0f1; display: none; max-height: 200px; overflow-y: auto; border: 1px solid #ccc;"></div>

			<p class="submit">
                <button type="button" id="start-submission" class="button button-primary" disabled>Add to Selected Products</button>
                <span id="spinner" class="spinner" style="float: none;"></span>
            </p>
		</form>
        
        <script>
        jQuery(document).ready(function($){
            
            // Load Plugins via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fs_as_fetch_plugins',
                    _ajax_nonce: '<?php echo wp_create_nonce( "fs_as_ajax_nonce" ); ?>'
                },
                success: function(response) {
                    $('#plugin-list-loading').hide();
                    if (response.success) {
                        var plugins = response.data;
                        if (plugins.length > 0) {
                            $('#plugin-list-table').show();
                            $('#filter-controls').show();
                            $('#start-submission').prop('disabled', false);
                            
                            // Debug output
                            $('#debug-container').html('<details><summary><strong>Debug: Click to see API Response for first product</strong></summary><pre>' + JSON.stringify(plugins[0], null, 2) + '</pre></details>').show();

                            // Collect unique store IDs
                            var stores = {};
                            $.each(plugins, function(i, plugin){
                                if (plugin.store_id) {
                                    stores[plugin.store_id] = true;
                                }
                            });
                            
                            // Populate store filter dropdown
                            $.each(Object.keys(stores), function(i, storeId){
                                $('#filter-store').append('<option value="' + storeId + '">Store ' + storeId + '</option>');
                            });

                            $.each(plugins, function(i, plugin){
                                var has_affiliation = false;
                                if (plugin.has_affiliation) has_affiliation = true;
                                else if (plugin.is_affiliate_program_enabled) has_affiliation = true;
                                
                                var status_label = has_affiliation ? '<span style="color:green; font-weight:bold;">Enabled</span>' : '<span style="color:gray;">Unknown/Disabled</span>';
                                
                                // Load saved Terms ID from localStorage
                                var savedTermsId = localStorage.getItem('fs_terms_id_' + plugin.id) || '';
                                
                                // Determine if premium based on premium_releases_count
                                var isPremium = (plugin.premium_releases_count && plugin.premium_releases_count > 0) ? 'premium' : 'free';
                                
                                var row = '<tr id="plugin-row-' + plugin.id + '" data-store-id="' + (plugin.store_id || '') + '" data-is-premium="' + isPremium + '">' +
                                    '<th scope="row" class="check-column"><input type="checkbox" class="plugin-checkbox" name="plugin_ids[]" value="' + plugin.id + '" data-name="' + plugin.title + '" /></th>' +
                                    '<td>' + plugin.title + '</td>' +
                                    '<td>' + plugin.id + '</td>' +
                                    '<td>' + status_label + '</td>' +
                                    '<td>' +
                                        '<input type="number" class="terms-id-input" data-plugin-id="' + plugin.id + '" placeholder="Terms ID" value="' + savedTermsId + '" style="width: 80px; margin-right: 5px;" /> ' +
                                        '<button type="button" class="button button-small save-terms-btn" data-plugin-id="' + plugin.id + '">Save</button> ' +
                                        '<span class="save-status-' + plugin.id + '" style="color: green; display: none;">✓ Saved</span>' +
                                    '</td>' +
                                    '<td class="status-cell"></td>' +
                                    '</tr>';
                                $('#plugin-list-body').append(row);
                            });
                            
                            // Save Terms ID when Save button is clicked
                            $(document).on('click', '.save-terms-btn', function(){
                                var pluginId = $(this).data('plugin-id');
                                var termsId = $('.terms-id-input[data-plugin-id="' + pluginId + '"]').val();
                                var statusSpan = $('.save-status-' + pluginId);
                                
                                if (termsId) {
                                    localStorage.setItem('fs_terms_id_' + pluginId, termsId);
                                    statusSpan.text('✓ Saved').fadeIn().delay(2000).fadeOut();
                                } else {
                                    localStorage.removeItem('fs_terms_id_' + pluginId);
                                    statusSpan.text('✓ Cleared').fadeIn().delay(2000).fadeOut();
                                }
                            });
                            
                            // Filter functionality
                            function applyFilters() {
                                var storeFilter = $('#filter-store').val();
                                var premiumFilter = $('#filter-premium').val();
                                
                                $('#plugin-list-body tr').each(function(){
                                    var row = $(this);
                                    var storeId = row.data('store-id');
                                    var isPremium = row.data('is-premium');
                                    var showRow = true;
                                    
                                    // Store filter
                                    if (storeFilter && storeId != storeFilter) {
                                        showRow = false;
                                    }
                                    
                                    // Premium filter
                                    if (premiumFilter && isPremium != premiumFilter) {
                                        showRow = false;
                                    }
                                    
                                    if (showRow) {
                                        row.show();
                                    } else {
                                        row.hide();
                                    }
                                });
                            }
                            
                            $('#filter-store, #filter-premium').on('change', applyFilters);
                            
                            $('#reset-filters').on('click', function(){
                                $('#filter-store').val('');
                                $('#filter-premium').val('');
                                applyFilters();
                            });
                        } else {
                            $('#plugin-list-body').html('<tr><td colspan="5">No plugins found.</td></tr>');
                            $('#plugin-list-table').show();
                        }
                    } else {
                        $('#plugin-list-loading').html('<p style="color:red;">Error loading plugins: ' + (response.data || 'Unknown error') + '</p>').show();
                    }
                },
                error: function() {
                    $('#plugin-list-loading').html('<p style="color:red;">Server error loading plugins.</p>').show();
                }
            });

            // Select All Logic (Delegated)
            $(document).on('click', '#cb-select-all-1', function(){
                var checked = this.checked;
                $('.plugin-checkbox').each(function(){
                    this.checked = checked;
                });
            });

            // Submission Logic
            $('#start-submission').on('click', function(e){
                e.preventDefault();
                
                var email = $('#aff-email').val();
                var name = $('#aff-name').val();
                var domain = $('#aff-domain').val();
                var selected = $('.plugin-checkbox:checked');
                
                if (selected.length === 0) {
                    alert('Please select at least one product.');
                    return;
                }

                if (!email || !name) {
                    alert('Please fill in required fields.');
                    return;
                }

                $('#start-submission').prop('disabled', true);
                $('#spinner').addClass('is-active');
                $('#submission-log').show().html('<p>Starting submission...</p>');
                $('.status-cell').text(''); // Clear previous results

                var queue = [];
                selected.each(function(){
                    queue.push({
                        id: $(this).val(),
                        name: $(this).data('name')
                    });
                });

                processQueue(queue, email, name, domain);
            });

            function processQueue(queue, email, name, domain) {
                if (queue.length === 0) {
                    $('#start-submission').prop('disabled', false);
                    $('#spinner').removeClass('is-active');
                    $('#submission-log').append('<p><strong>All Done!</strong></p>');
                    return;
                }

                var item = queue.shift();
                var row = $('#plugin-row-' + item.id);
                var statusCell = row.find('.status-cell');
                var termsId = row.find('.terms-id-input').val();
                
                statusCell.text('Processing...').css('color', 'orange');
                $('#submission-log').append('<p>Processing ' + item.name + '...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fs_as_add_affiliate',
                        _ajax_nonce: '<?php echo wp_create_nonce( "fs_as_ajax_nonce" ); ?>',
                        plugin_id: item.id,
                        terms_id: termsId,
                        email: email,
                        name: name,
                        domain: domain
                    },
                    success: function(response) {
                        if (response.success) {
                            statusCell.text('Success').css('color', 'green');
                            $('#submission-log').append('<p style="color:green;">' + response.data + '</p>');
                        } else {
                            statusCell.text('Failed').css('color', 'red');
                            $('#submission-log').append('<p style="color:red;">Failed for ' + item.name + ': ' + (response.data || 'Unknown error') + '</p>');
                        }
                        processQueue(queue, email, name, domain);
                    },
                    error: function() {
                        statusCell.text('Error').css('color', 'red');
                        $('#submission-log').append('<p style="color:red;">Server error for ' + item.name + '</p>');
                        processQueue(queue, email, name, domain);
                    }
                });
            }
        });
        </script>
		<?php
	}

    public function ajax_fetch_plugins() {
        check_ajax_referer( 'fs_as_ajax_nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $options = get_option( $this->option_name );
        $plugins = $this->fetch_plugins( $options );

        if ( is_wp_error( $plugins ) ) {
            wp_send_json_error( $plugins->get_error_message() );
        } else {
            wp_send_json_success( $plugins );
        }
    }

    private function fetch_plugins( $creds ) {
        $dev_id = trim( $creds['dev_id'] ?? '' );
        $pub = trim( $creds['public_key'] ?? '' );
        $sec = trim( $creds['secret_key'] ?? '' );

        try {
            $client = new Freemius_Api( 'developer', $dev_id, $pub, $sec );
            $response = $client->Api( '/plugins.json' );

            if ( isset( $response->error ) ) {
                return new WP_Error( 'api_error', $response->error->message ?? 'Unknown API Error' );
            } elseif ( isset( $response->plugins ) || is_array( $response ) ) {
                return isset($response->plugins) ? $response->plugins : $response;
            } else {
                return new WP_Error( 'api_error', 'Unexpected response format' );
            }
        } catch ( Exception $e ) {
            return new WP_Error( 'sdk_error', $e->getMessage() );
        }
    }

    public function ajax_add_affiliate() {
        check_ajax_referer( 'fs_as_ajax_nonce' );

        if ( ! current_user_can( 'read' ) ) { 
            wp_send_json_error( 'Permission denied' );
        }

        $plugin_id = isset( $_POST['plugin_id'] ) ? intval( $_POST['plugin_id'] ) : 0;
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $domain = isset( $_POST['domain'] ) ? sanitize_text_field( $_POST['domain'] ) : '';
        $terms_id = isset( $_POST['terms_id'] ) && ! empty( $_POST['terms_id'] ) ? intval( $_POST['terms_id'] ) : 0;

        if ( ! $plugin_id || ! $email || ! $name ) {
            wp_send_json_error( 'Missing required fields' );
        }

        if ( ! $terms_id ) {
            wp_send_json_error( 'Please enter the Affiliate Program Terms ID for this plugin. Find it in your Freemius Dashboard → Product → Affiliation (first tab).' );
        }

        $options = get_option( $this->option_name );
        $dev_id = trim( $options['dev_id'] ?? '' );
        $pub = trim( $options['public_key'] ?? '' );
        $sec = trim( $options['secret_key'] ?? '' );

        $errors = [];

        // Submit Application to Freemius
        try {
            $client = new Freemius_Api( 'developer', $dev_id, $pub, $sec );
            
            $payload = [
                'name' => $name,
                'email' => $email,
                'domain' => str_replace(['http://','https://'], '', $domain) ?: 'example.com',
                'promotional_methods' => 'content_marketing',
                'stats_description' => 'N/A',
                'promotion_method_description' => 'Applied via Bulk Tool',
                'state' => 'active' // Try to auto-approve
            ];

            // Endpoint: /plugins/{productID}/aff/{affiliateProgramTermsID}/affiliates.json
            $path = "/plugins/{$plugin_id}/aff/{$terms_id}/affiliates.json";
            
            $response = $client->Api( $path, 'POST', $payload );

            if ( ! isset( $response->error ) ) {
                wp_send_json_success( 'Affiliate Added Successfully!' );
                return;
            }
            
            $errors[] = 'Submit Application: ' . json_encode($response->error);

        } catch ( Exception $e ) {
            $errors[] = 'Submit Application Ex: ' . $e->getMessage();
        }

        // If failed
        wp_send_json_error( 'Submission Failed: ' . implode( ' | ', $errors ) );
    }
}

new FS_Affiliate_Submit();
