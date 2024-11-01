<?php
class TruVisibilityPlagiarismDashboardWidget
{
	private $plagiarism_client;

	public function __construct($plagiarism_client)
	{
		$this->plagiarism_client = $plagiarism_client;

		add_action('wp_dashboard_setup', array($this, 'create_dashboard_widget'));
	}

	public function create_dashboard_widget()
	{
		wp_add_dashboard_widget('plagiarism_dashboard_widget', 'TruVisibility Plagiarism', array($this, 'fill_dashboard_widget'));

		add_action('admin_enqueue_scripts', array($this, 'insert_styles_and_scripts'));
	}

	public function fill_dashboard_widget()
	{
		if (get_option('truvisibility_plagiarism_account') == false)
		{
			?>
			<div align="center" style="padding: 2em 3em;">Authorize plugin</div>
			<?php
		}
		else
		{
			$history_url = get_admin_url().'admin.php?page=plagiarism_history';

			?>
			<div id="rast-dashboard">
				<div class="rast-block">
					<div id="rast-account-loading">Loading...</div>
					<div id="rast-account" style="display: none;">
						<h3>Account</h3>
						<ul>
							<li class="item"><span class="title">Name</span><span id="rast-account-name"></span></li>
							<li class="item"><span class="title">Quota</span><span id="rast-account-quota"></span></li>
						</ul>
					</div>
				</div>
				<div class="rast-block">
					<ul id="rast-latest-activity" class="rast-latest-activity">
						<li>Loading...</li>
					</ul>
				</div>
			</div>
			<?php
		}
	}

	public function insert_styles_and_scripts()
    {
		wp_enqueue_style('rast-dashboard-widget-style', plugins_url('../../css/dashboard-widget-style.css', __FILE__));

		wp_enqueue_script('rast-utils', plugins_url('../../js/utils.js', __FILE__));
		wp_enqueue_script('rast-dashboard-widget', plugins_url('../../js/dashboard.widget.js', __FILE__), array('jquery', 'rast-utils'));
		
		wp_localize_script('rast-dashboard-widget', 'rastSettings', array('restUrl' => rest_url(), 'adminUrl' => get_admin_url()));
    }
}
?>