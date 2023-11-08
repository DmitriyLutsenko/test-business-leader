<?php
class ModelToolCdektool extends Model {

	public function check()
	{
		$license = $this->chechLicense();

		if($license['status'] == false) {
			$this->session->data['error'] = $license['message'];
			return false;
		} else {
			return true;
		}
	}

	public function checkInstalled($type, $name)
	{
		$this->load->model('setting/extension');
		$installed = true;
		$extensions = $this->model_setting_extension->getInstalled($type);

		if (!in_array($name, $extensions)) 
		{
			$installed = false;
			
			$this->model_setting_extension->install($type, $name);

			$this->load->model('user/user_group');

			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $type . '/' . $name);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $type . '/' . $name);

			// Call install method if it exsits
			$this->load->controller($type . '/' . $name . '/install');
		}
		
		return $installed;
	}

	public function chechLicense()
	{
		$this->load->model('setting/setting');
		$license_status = 'fail';
		$user = 'test';
		$password = 'test';
		
		$settings = $this->model_setting_setting->getSetting('cdekLicense');
		if(isset($settings['cdekLicense_user']) && isset($settings['cdekLicense_password']))
		{
			$user = $settings['cdekLicense_user'];
			$password = $settings['cdekLicense_password'];
			$license_status = $this->chechUser($user, $password);
			if($license_status == 'fail')
			{
				$license = array('status'=>false, 'message'=>'Пользователь '.$user.' не активен! <a href="http://cdek-souz.ru/users/registrate/" target="_blank">Зарегистрироваться</a> <br> <b>Перед установкой/настройкой модуля пожалуйста, ознакомьтесь с <a href="http://cdek.opencart.ru/documentation/" target="_blank">документацией</a></b>');
			}
			else
			{
				$license = array('status'=>true, 'message'=>'Лицензия '.$user.' работает нормально!');	
			}
		}
		else
		{
			$license = array('status'=>false, 'message'=>'Нет лицензии! <a href="http://cdek-souz.ru/users/registrate/" target="_blank">Зарегистрироваться</a> <br> <b>Перед установкой/настройкой модуля пожалуйста, ознакомьтесь с <a href="http://cdek.opencart.ru/documentation/" target="_blank">документацией</a></b>');
		}	

		return $license;
	}

	public function chechUser($user, $pass)
	{
		$licenseServers = array();

		$licenseServers[] = 'http://cdek.opencart.ru/service/license/';
		$licenseServers[] = 'https://cdek.im/udata/users/userCheck/';

		$status = 'fail';
        
		foreach ($licenseServers as $licenseServer) {
			$response = $this->getCurl($licenseServer.'?m='.$user.'&p='.$pass.'');
            
			if(!empty($response['content']) && ($response['content'] == 'successful' || $response['content'] == 'good'))
			{
				return 'successful';
			}
		}

		return $status;
	}

	public function importCdekCities(){
		$file = DIR_SYSTEM . "library/cdek_integrator/cdek_city.json";
		if (file_exists($file)){
			$json = file_get_contents($file);
			if (!empty($json)){
				$this->db->query("TRUNCATE TABLE ".DB_PREFIX . "cdek_city");
				$cdek_cities = json_decode($json,1);
				foreach ($cdek_cities as $cdek_city) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "cdek_city SET id='".(int)$cdek_city['id']."', name='".$this->db->escape($cdek_city['name']) ."', cityName='".$this->db->escape($cdek_city['cityName']) ."', regionName='".$this->db->escape($cdek_city['regionName']) ."', center='".(int)$cdek_city['center'] ."', cache_limit='".(float)$cdek_city['cache_limit'] ."'");
				}
			}
		}
	}

	public function getCurl( $url )
	{
	    $options = array(
	        CURLOPT_RETURNTRANSFER => true,     // return web page
	        CURLOPT_HEADER         => false,    // don't return headers
	        CURLOPT_FOLLOWLOCATION => true,     // follow redirects, or false
			CURLOPT_ENCODING       => "",       // handle all encodings
			CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
	        CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17", // who am i
	        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
	        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
	        CURLOPT_TIMEOUT        => 120,      // timeout on response
	        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
	        );
	    $ch      = curl_init( $url );
	    curl_setopt_array( $ch, $options );
	    $content = curl_exec( $ch );
	    $err     = curl_errno( $ch );
	    $errmsg  = curl_error( $ch );
	    $header  = curl_getinfo( $ch );
	    curl_close( $ch );
	    $header['errno']   = $err;
	    $header['errmsg']  = $errmsg;
	    $header['content'] = $content;
	    return $header;
	}
}