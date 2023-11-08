<?php
namespace OpenCart;

class CurlRequest {
    private $url;
    private $postData = array();
    private $cookies = array();
    private $response = '';
    private $handle;
    private $session_id;
	private $sessionFile;

    private function getCookies() {
        $cookies = array();
        foreach ($this->cookies as $name=>$value) {
            $cookies[] = $name . '=' . $value;
        }

        return implode('; ', $cookies);
    }

    private function saveSession() {
        if (empty($this->sessionFile)) return;

        if (!file_exists(dirname($this->sessionFile))) {
            mkdir(dirname($this->sessionFile, 0755, true));
        }

        file_put_contents($this->sessionFile, json_encode($this->cookies));
    }

    private function restoreSession() {
        if ($this->session_id) {
            $this->cookies = ['OCSESSID' => $this->session_id];
        }
    }

    public function __construct($session_id, $session_file = '') {
    	$this->sessionFile = $session_file;
        $this->session_id = $session_id;
        $this->restoreSession();
    }

    public function makeRequest() {
        $this->handle = curl_init($this->url);

        curl_setopt($this->handle, CURLOPT_HEADER, true);
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->handle, CURLOPT_POST, true);
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($this->postData));
		curl_setopt($this->handle, CURLOPT_COOKIE, $this->getCookies());
        $this->response = curl_exec($this->handle);
        $header_size = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        $headers = substr($this->response, 0, $header_size);

        $this->response = substr($this->response, $header_size);

        $this->saveSession();
		curl_close($this->handle);
        return $this->response;


    }



    public function setUrl($url) {
        $this->url = $url;
    }

    public function setData($postData) {
        $this->postData = $postData;
    }

    public function getResponse() { return json_decode($this->response, true); }
    public function getRawResponse() { return $this->response; }
}

class Base {
    protected $oc;
    protected $curl;
    public $token;
    
    public function __construct($oc) {
        $this->oc = $oc;
        $this->curl = $oc->curl;
    }

    public function setToken($token) {
        $this->token = $token;
    }
}

class Order extends Base 
{
    public function history($order_id, $order_status_id = '', $notify = '', $override = '', $comment = '')
    {
        if (empty($order_id)) throw new InvalidDataException("Order ID cannot be empty for Order->edit()");

        $postData = array(
            'order_status_id' => $order_status_id,
            'notify' => $notify,
            'override' => $override,
            'comment' => $comment,
        );

        $url = $this->oc->getUrl('order/history&order_id='.$order_id.'&api_token='.$this->token);

        $this->curl->setUrl($url);
        $this->curl->setData($postData);
        $this->curl->makeRequest();
        return $this->curl->getResponse();
    }
}

class OpenCart {
    private $url;
    private $lastError = '';
    public $curl;
    public $order;

    public function __construct($url, $session_id = '', $session_file = '') {
        $this->url = rtrim('http://'.preg_replace('/^https?\:\/\//', '', $url), '/') . '/index.php?route=api/';
        $this->curl = new CurlRequest($session_id, $session_file);
        $this->order = new Order($this);
    }

    public function getUrl($method) { return $this->url . $method; }
    public function getLastError() { return $this->lastError; }

	public function login($username, $key) {
		if (empty($key)) throw new InvalidCredentialsException("Username and password cannot be empty");

		$this->curl->setUrl($this->getUrl('login'));
		$this->curl->setData(array(
			'username' => $username,
			'key' => $key
		));
		$this->curl->makeRequest();

		$response = $this->curl->getResponse();
		if (isset($response['success']) && isset($response['api_token'])) {
			return $response['api_token'];
		} else if (isset($response['error'])) {
			$this->lastError = $response['error'];
		}

		return false;
	}
}

class InvalidCredentialsException extends \Exception {}
class InvalidDataException extends \Exception {}
class InvalidProductException extends \Exception {}