<?php
class TruVisibilityAuthClient 
{
	private $auth_token_endpoint;

	private $redirect_uri;

	private $api_endpoint;

	private $client_id = '6202f4a6-c009-4ff8-8cec-60b120119577';

	private $client_secret = '89oGdGOBcITfodXOtVLI';

	public function __construct()
	{
		$this->auth_token_endpoint = 'https://auth.'.TruVisibilityPlagiarismConfig::$TvUmbrellaRoot.'/oauth/token';
		$this->redirect_uri = 'https://auth.'.TruVisibilityPlagiarismConfig::$TvUmbrellaRoot.'/oauth/authorization-code';
		$this->api_endpoint = 'https://auth.'.TruVisibilityPlagiarismConfig::$TvUmbrellaRoot.'/account/api';
	}

	public function ensure_account()
	{
        $response = wp_remote_post($this->api_endpoint.'/charge/customer/ensure', $this->default_args());

		return self::parse($response);
	}

	public function save_access($authorization_code)
	{
		$args = array(
			'body' => 'grant_type=authorization_code&code='.$authorization_code.'&client_id='.$this->client_id.'&client_secret='.$this->client_secret.'&redirect_uri='.urlencode($this->redirect_uri),
			'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
			'timeout' => 60,
			'sslverify' => TruVisibilityPlagiarismConfig::$SslVerify);

		$response = wp_remote_post($this->auth_token_endpoint, $args);

		if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200)
		{
			$token = json_decode($response['body']);

			$account = array();
			$account['access-token'] = $token->access_token;
			$account['refresh-token'] = $token->refresh_token;
			$account['expires-at'] = time() + $token->expires_in;
			$account['token-type'] = $token->token_type;

			update_option('truvisibility_plagiarism_account', $account);

			$user_info = $this->get_me($token->token_type, $token->access_token);

			$account['name'] = $user_info->firstName.' '.$user_info->lastName;
			$account['email'] = $user_info->email;

			update_option('truvisibility_plagiarism_account', $account);
		}
	}

	public function update_access_if_expired()
	{
		$account = get_option('truvisibility_plagiarism_account');

		if ($account['expires-at'] < time())
		{
			$args = array(
				'body' => 'grant_type=refresh_token&refresh_token='.$account['refresh-token'].'&client_id='.$this->client_id.'&client_secret='.$this->client_secret,
				'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
				'timeout' => 60,
				'sslverify' => TruVisibilityPlagiarismConfig::$SslVerify);

			$response = wp_remote_post($this->auth_token_endpoint, $args);

			if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200)
			{
				$new_token = json_decode($response['body']);

				$account['access-token'] = $new_token->access_token;
				$account['refresh-token'] = $new_token->refresh_token;
				$account['expires-at'] = time() + $new_token->expires_in;
				$account['token-type'] = $token->token_type;

				update_option('truvisibility_plagiarism_account', $account);
			}
		}
	}

	private function get_me($token_type, $access_token)
	{
		$url = 'https://auth.'.TruVisibilityPlagiarismConfig::$TvUmbrellaRoot.'/api/users/me';
		$args = array('headers' => array('Authorization' => $token_type.' '.$access_token), 'sslverify' => TruVisibilityPlagiarismConfig::$SslVerify);

		$response = wp_remote_get($url, $args);
		return json_decode($response['body']); ;
	}

	private static function parse($response)
	{
		return (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) ? $response : json_decode($response['body']);
	}

	private function default_args()
	{
		return array(
			'timeout' => 30, 
			'sslverify' => TruVisibilityPlagiarismConfig::$SslVerify, 
			'headers' => array('Authorization' => 'Bearer '.get_option('truvisibility_plagiarism_account')['access-token']));
	}
}
?>