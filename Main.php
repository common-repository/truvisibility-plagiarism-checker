<?php

/*
Plugin Name: TruVisibility Plagiarism Checker
Plugin URI: http://www.truvisibility.com/software-plugins-tools/plagiarism-checker-for-wordpress
Description: Checks your posts for plagiarism before publishing. Provides checks history and detailed reports with plagiarized fragments and links to them.
Version: 1.0
Author: TruVisibility, LLC
Author URI: https://www.truvisibility.com/
License: GPLv2 or later
*/

require(__DIR__.'/config/Config.php');

register_activation_hook(__FILE__, 'truvisibility_plagiarism_checker_activated');
register_deactivation_hook(__FILE__, 'truvisibility_plagiarism_checker_deactivated');

function truvisibility_plagiarism_checker_activated()
{
	add_option('truvisibility_plagiarism_usage', array(
		'checking_enabled' => 'on', 
		'notification_enabled' => '', 
		'notification_target_email' => wp_get_current_user()->user_email,
		'min_uniqueness' => '80'));
}

function truvisibility_plagiarism_checker_deactivated()
{
	delete_option('truvisibility_plagiarism_account');
	delete_option('truvisibility_plagiarism_usage');
}

final class TruVisibilityPlagiarismChecker {
    
    private static $instance;

	private $rest_api;

	private $settings;

    private $history;

    private $report_widget;
    
	private $dashboard_widget;

	private $plagiarism_client;

    public static function get_instance() 
	{
        if (!isset(self::$instance) || !(self::$instance instanceof TruVisibilityPlagiarismChecker)) 
		{
            require_once plugin_dir_path(__FILE__).'services/clients/TruVisibilityAuthClient.php';
            require_once plugin_dir_path(__FILE__).'services/clients/TruVisibilityPlagiarismClient.php';
			require_once plugin_dir_path(__FILE__).'services/widgets/TruVisibilityPlagiarismReportWidget.php';
            require_once plugin_dir_path(__FILE__).'services/widgets/TruVisibilityPlagiarismDashboardWidget.php';
            require_once plugin_dir_path(__FILE__).'services/TruVisibilityPlagiarismRestApi.php';
			require_once plugin_dir_path(__FILE__).'services/TruVisibilityPlagiarismSettings.php';
            require_once plugin_dir_path(__FILE__).'services/TruVisibilityPlagiarismHistory.php';

			$auth_client = new TruVisibilityAuthClient();
			$plagiarism_client = new TruVisibilityPlagiarismClient($auth_client);

			self::$instance = new TruVisibilityPlagiarismChecker();
			self::$instance->plagiarism_client = $plagiarism_client;
			self::$instance->rest_api = new TruVisibilityPlagiarismRestApi($plagiarism_client, $auth_client);
			self::$instance->settings = new TruVisibilityPlagiarismSettings($plagiarism_client);
			
			if (get_option('truvisibility_plagiarism_account') != false)
			{
				self::$instance->history = new TruVisibilityPlagiarismHistory();
				self::$instance->report_widget = new TruVisibilityPlagiarismReportWidget();
				self::$instance->dashboard_widget = new TruVisibilityPlagiarismDashboardWidget($plagiarism_client);
			}

			add_filter('cron_schedules', array(self::$instance, 'register_custom_cron_intervals'));
			add_action('truvisibility_plagiarism_check_status', array(self::$instance, 'check_post_ready'));

			add_action('init', array(self::$instance, 'register_custom_post_status'));
            
			add_filter('display_post_states', array(self::$instance, 'display_custom_post_status'), 10, 2);
			add_action('transition_post_status', array(self::$instance, 'check_post_for_plagiarism'), 10, 3);
        }
    }

	function register_custom_cron_intervals( $schedules ) 
	{
		$schedules['3_seconds'] = array(
			'interval' => 3,
			'display'  => esc_html__( 'Every 3 Seconds' ),
		);
 
		return $schedules;
	}

	public function check_post_ready($args)
	{
		$check_info = split(';', $args);
		$post_global_id = $check_info[0];
		$post_time = $check_info[1];
		$report = $this->plagiarism_client->get_report($post_global_id, $post_time, false);

		if ($report == null || $report == false)
		{
			$timestamp = wp_next_scheduled('truvisibility_plagiarism_check_status', array($args));
			wp_unschedule_event($timestamp, 'truvisibility_plagiarism_check_status', array($args));
			return;
		}

		if ($report->status >= 2)
		{
			$timestamp = wp_next_scheduled('truvisibility_plagiarism_check_status', array($args));
			wp_unschedule_event($timestamp, 'truvisibility_plagiarism_check_status', array($args));
		}

		$post_id = split('/', $post_global_id)[2];
		$post = get_post($post_id);

		switch ($report->status)
		{
			case 0: $post->post_status = 'enqueued'; break;
			case 1: $post->post_status = 'checking'; break;
			case 2:
				$post->post_status = $report->plagiarismFactor >= $report->stopLimit ? 'plagiarized' : 'publish';
				if ($post->post_status == 'publish') add_post_meta($post->ID, 'truvisibility_plagiarism_post_checked', true, true);
				add_post_meta($post->ID, 'truvisibility_plagiarism_post_uniqueness', 100 - $report->plagiarismFactor, true);
				break;

			default: $post->post_status = 'draft';
		}
		
		wp_update_post($post);
	}

    public function register_custom_post_status() 
    {
        register_post_status('plagiarized', array(
		    'label'                     => _x('Plagiarized', 'post'),
		    'public'                    => false,
		    'protected'                 => true,
		    'exclude_from_search'       => false,
		    'show_in_admin_all_list'    => true,
		    'show_in_admin_status_list' => true,
		    'label_count'               => _n_noop('Plagiarized <span class="count">(%s)</span>', 'Plagiarized <span class="count">(%s)</span>'),
	    ));

        register_post_status('checking', array(
		    'label'                     => _x('Checking', 'post'),
		    'public'                    => false,
			'protected'                 => true,
		    'exclude_from_search'       => false,
		    'show_in_admin_all_list'    => true,
		    'show_in_admin_status_list' => true,
		    'label_count'               => _n_noop('Checking <span class="count">(%s)</span>', 'Checking <span class="count">(%s)</span>'),
	    ));

		register_post_status('enqueued', array(
		    'label'                     => _x('Enqueued', 'post'),
		    'public'                    => false,
			'protected'                 => true,
		    'exclude_from_search'       => false,
		    'show_in_admin_all_list'    => true,
		    'show_in_admin_status_list' => true,
		    'label_count'               => _n_noop('Enqueued <span class="count">(%s)</span>', 'Enqueued <span class="count">(%s)</span>'),
	    ));
    }

    public function display_custom_post_status($post_states, $post) 
    {
        if ($post->post_status == 'plagiarized') return array(_x('Plagiarized', 'post'));
        if ($post->post_status == 'enqueued') return array(_x('Enqueued', 'post'));
        if ($post->post_status == 'checking') return array(_x('Checking', 'post'));

        return $post_states;
    }

	public function check_post_for_plagiarism($new_status, $old_status, $post) 
    {
		// this function used to catch post publishing
		if ($new_status == 'publish')
		{
			$usage = get_option('truvisibility_plagiarism_usage');

			// if checking is disabled, plugin does nothing
			if ($usage['checking_enabled'] != 'on')
			{
				return;
			}

			// if 'publish anyway' flag is set, plugin prevents checking and sends email notification if post is plagiarized
			if (get_post_meta($post->ID, 'truvisibility_plagiarism_publish_anyway', true) == true)
			{
				delete_post_meta($post->ID, 'truvisibility_plagiarism_publish_anyway');
				delete_post_meta($post->ID, 'truvisibility_plagiarism_post_checked');

				if ($old_status == 'plagiarized' && $usage['notification_enabled'] == 'on') 
				{
					$email = $usage['notification_target_email'];
					$title = $post->post_title;
					$url = get_post_permalink($post->ID);
					$uniqueness = get_post_meta($post->ID, 'truvisibility_plagiarism_post_uniqueness', true);
					$min_uniqueness = $usage['min_uniqueness'];

					$this->plagiarism_client->notify_about_plagiarized_post($email, $title, $url, $uniqueness, $min_uniqueness);
				}

				return;
			}

			// this helps to prevent repeated checking when status changes from 'checking' to 'publish'
			if (get_post_meta($post->ID, 'truvisibility_plagiarism_post_checked', true) == true)
			{
				delete_post_meta($post->ID, 'truvisibility_plagiarism_post_checked');
				return;
			}

			$check_id = 'WordPress/'.get_current_blog_id().'/'.$post->ID;
			$report = $this->plagiarism_client->check_post($check_id, $post->post_title, $post->post_content);

			if ($report instanceof WP_Error)
			{
				return;
			}

			$post->post_status = 'enqueued';
			wp_update_post($post);

			wp_schedule_event(time(), '3_seconds', 'truvisibility_plagiarism_check_status', array($check_id.';'.$report->time));
		}
	}
}

function truvisibility_plagiarism_checker_run() {
    return TruVisibilityPlagiarismChecker::get_instance();
}

add_action('plugins_loaded', 'truvisibility_plagiarism_checker_run');

?>