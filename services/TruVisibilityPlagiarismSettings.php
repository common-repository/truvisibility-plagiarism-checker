<?php
class TruVisibilityPlagiarismSettings 
{
	private $plagiarism_client;

	public function __construct($plagiarism_client)
	{
		$this->plagiarism_client = $plagiarism_client;

		add_action('admin_menu', array($this, 'init'));
		add_action('admin_init', array($this, 'render_page'));

		if (isset($_GET['page']) && $_GET['page'] == 'truvisibility_plagiarism_settings')
		{
			add_action('admin_enqueue_scripts', array($this, 'insert_styles_and_scripts'));
		}
	}

	function init() 
	{
		add_menu_page(
			'TruVisibility Plagiarism &raquo; Settings',
			'Plagiarism',
			'manage_options',
			'truvisibility_plagiarism_settings',
			array($this, 'render_options_page_html'),
			null, 
			26);
	}

	public function render_page() 
	{
		register_setting('plagiarism', 'truvisibility_plagiarism_usage', array($this, 'sanitize_settings'));
 
		add_settings_section('section_account', 'Account', array($this, 'section_account_cb'), 'truvisibility_plagiarism_settings');

		if (get_option('truvisibility_plagiarism_account') != false)
		{
			add_settings_section('section_usage', 'Usage', array($this, 'section_usage_cb'), 'truvisibility_plagiarism_settings');

			$usage = get_option('truvisibility_plagiarism_usage');

			add_settings_field(
				'field_enabled',
				'Plagiarism checking is enabled',
				array($this, 'field_enabled_cb'),
				'truvisibility_plagiarism_settings',
				'section_usage',
				$usage);

			add_settings_field(
				'field_send_notif',
				'Send notification to site owner',
				array($this, 'field_send_notif_cb'),
				'truvisibility_plagiarism_settings',
				'section_usage', 
				$usage);

			add_settings_field(
				'field_stop_limit',
				'Minimum acceptable uniqueness',
				array($this, 'field_stop_limit_cb'),
				'truvisibility_plagiarism_settings',
				'section_usage',
				$usage);

			add_settings_section('section_subscription', 'Subscription', array($this, 'section_subscription_cb'), 'truvisibility_plagiarism_settings');
			
			add_settings_field(
				'field_quota',
				'Current usage',
				array($this, 'field_quota_cb'),
				'truvisibility_plagiarism_settings',
				'section_subscription');

			add_settings_field(
				'field_plans',
				'Available subscriptions',
				array($this, 'field_plans_cb'),
				'truvisibility_plagiarism_settings',
				'section_subscription');

			
		}
	}

	function sanitize_settings($input) 
	{
		$input['checking_enabled'] = ($input['checking_enabled'] == 'on') ? 'on' : '';
		$input['notification_enabled'] = ($input['notification_enabled'] == 'on') ? 'on' : '';

		return $input;
	}

	public function section_usage_cb( $args ) {}
	public function section_subscription_cb($args) {}
	public function section_account_cb($args) 
	{
		$account = get_option('truvisibility_plagiarism_account');

		if ($account == false)
		{
			?>
			<div id="auth-button" class="button">Authorize with TruVisibility</div>
			<div id="auth-code" style="display: none;">
				<p class="description">Copy the authorization code into the box below and click next</p>
				<input id="auth-code-value" type="text" value="" placeholder="Authorization code" style="margin-right: 0.5em;" />
				<div id="auth-code-apply" class="button">Next</div>
			</div>
			<?php
		}
		else
		{
			?>
			<b><?php echo $account['name']; ?></b> <i>(<?php echo $account['email']; ?>)</i><br/>
			<div style="padding: 10px 0;">
				<div id="clean-auth-button" class="button">Sign Out</div>
			</div>
			<?php
		}
	}

	public function field_enabled_cb($usage) 
	{
		echo '<input type="checkbox" '.checked($usage['checking_enabled'], 'on', false).' name="truvisibility_plagiarism_usage[checking_enabled]" />';
    }

	public function field_send_notif_cb($usage) 
	{
		echo '<input type="checkbox" '.checked($usage['notification_enabled'], 'on', false).' name="truvisibility_plagiarism_usage[notification_enabled]" style="position: relative; top: -2px;" /> ';
		echo '<input type="email" value="'.$usage['notification_target_email'].'" name="truvisibility_plagiarism_usage[notification_target_email]" />';
		echo '<p class="description">Notification will be sent to this email if somebody publishes plagiarized post</p>';
    }

	public function field_stop_limit_cb($usage) 
	{
		echo '<input type="number" min="0", max="100" name="truvisibility_plagiarism_usage[min_uniqueness]" value="'.esc_attr($usage['min_uniqueness']).'" /> %';
    }

	public function field_plans_cb($args) 
	{
		?>
		<img id="rast-plans-loading" src="<?php echo get_admin_url(); ?>images/loading.gif" />
		<table id="rast-plans" style="margin: 0 0 0 -10px;"></table>
		<?php
	}

	public function field_quota_cb($args) 
	{
        ?>
		<img id="rast-quota-loading" src="<?php echo get_admin_url(); ?>images/loading.gif" />
		<div id="rast-quota-view" style="display: none;">
			<span id="rast-quota-used"></span> of <span id="rast-quota-limit"></span> checks used. <span id="rast-purchase-info"></span>
			<p id="rast-purchasing-ok" class="description" style="display: none;">
				You can <a href="javascript:void()" id="rast-upgrade"><strong>increase</strong></a> your quota by purchasing additional checks.
			</p>
			<p id="rast-purchasing-no-card" class="description" style="display: none;">
				Please update your <a href="https://auth.<?php echo TruVisibilityPlagiarismConfig::$TvUmbrellaRoot; ?>/account/profile#credit-card" target="_blank">TruVisibility account</a> with credit card information to unblock additional checks.
			</p>
		</div>
		
		<div id="rast-buy-checks-dialog" class="rast-modal">
			<div class="rast-modal-content">
				<div style="padding: 8px 12px; border-bottom: 1px solid #eee; cursor: pointer;">
					<strong>Confirmation</strong>
					<span id="rast-buy-checks-dialog-close" class="dashicons dashicons-no-alt" style="float: right;"></span>
				</div>
				<div style="padding: 0 12px 12px 12px;">
					<div id="rast-checks-price-loader">
						<p>Loading price...</p>
					</div>
					<div id="rast-checks-price-view" style="display: none;">
						<p>Price: <span id="rast-checks-price"></span> per <span id="rast-checks-quantity"></span> checks</p>
						<div>
							<div id="rast-buy-checks" class="button button-primary" style="margin: 6px 6px 0 0;">Buy</div>
							<div id="rast-buy-checks-cancel" class="button" style="margin: 6px 6px 0 0;">Cancel</div>
						</div>
					</div>
					<div id="rast-purchase-waiter" style="display: none; text-align: center; margin: 20px;">
						<p><span class="dashicons dashicons-clock"></span></p>
						<p>The purchase is currently being processed. Please wait a moment.</p>
						<p>You can close this window and check the purchase later.</p>
					</div>
				</div>
			</div>
		</div>
		<?php
    }

	public function insert_styles_and_scripts()
    {
		wp_enqueue_style('rast-common-style', plugins_url('../css/common.css', __FILE__));

		wp_enqueue_script('rast-settings', plugins_url('../js/settings.js', __FILE__), array('jquery'));
		wp_localize_script('rast-settings', 'rastSettings', array(
			'restUrl' => rest_url(), 
			'umbrellaRoot' => TruVisibilityPlagiarismConfig::$TvUmbrellaRoot, 
			'pluginAuthorized' => get_option('truvisibility_plagiarism_account') != false));
    }

	public function render_options_page_html() 
	{
		if (!current_user_can('manage_options')) 
		{
			return;
		}
 
		if (isset($_GET['settings-updated']))
		{
			add_settings_error('plagiarism', array($this, 'sanitize_checkbox'), 'Settings Saved', 'updated');
		}
		
		settings_errors('plagiarism');

		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">		
		<?php

		settings_fields('plagiarism');
		do_settings_sections('truvisibility_plagiarism_settings');

		if (get_option('truvisibility_plagiarism_account') != false)
		{
			submit_button('Save Settings');
		}

		?>
			</form>
		</div>
		<?php
	}
}
?>