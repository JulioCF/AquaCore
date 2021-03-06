<?php
use Aqua\Core\App;
use Aqua\Core\Settings;
use Aqua\Http\Request;
use Aqua\Http\Response;

class AquaCoreSetup
{
	/**
	 * @var \Aqua\Http\Response
	 */
	public $response;
	/**
	 * @var \PDO
	 */
	public $dbh;
	/**
	 * @var string
	 */
	public $languageCode;
	/**
	 * @var Aqua\Core\Settings
	 */
	public $config;
	/**
	 * @var
	 */
	public $language;
	/**
	 * @var array
	 */
	public $languagesAvailable;
	/**
	 * @var int
	 */
	public $currentStep;
	/**
	 * @var int
	 */
	public $lastStep;
	/**
	 * @var array
	 */
	public $steps = array(
		'index',
		'address',
		'database',
		'application',
		'email',
		'phpass',
		'account',
		'install',
		'finish'
	);

	const LANGUAGE_KEY = 'setup_language';
	const STEP_KEY     = 'setup_step';
	const CONFIG_KEY   = 'setup_config';

	public function __construct(Request $request, Response $response)
	{
		defined('Aqua\PUBLIC_FILE_PERMISSION') or define('Aqua\PUBLIC_FILE_PERMISSION', 0775);
		defined('Aqua\PUBLIC_DIRECTORY_PERMISSION') or define('Aqua\PUBLIC_DIRECTORY_PERMISSION', 0775);
		defined('Aqua\PRIVATE_FILE_PERMISSION') or define('Aqua\PRIVATE_FILE_PERMISSION', 0770);
		defined('Aqua\PRIVATE_DIRECTORY_PERMISSION') or define('Aqua\PRIVATE_DIRECTORY_PERMISSION', 0770);
		$this->languagesAvailable = include \Aqua\ROOT . '/install/language/languages-available.php';
		$this->response           = $response;
		if($settings = App::cache()->fetch('setup_settings_full')) {
			App::settings()->merge($settings);
		}
		App::settings()->set('cache', array(
			'storage_adapter' => 'File',
			'storage_options' => array(
				'prefix' => '',
				'hash' => null,
				'directory' => \Aqua\ROOT . '/tmp/cache',
				'gc_probability' => 0,
			)
		));
		ac_define_constants();
		App::defineConstants();
		if(!($lang = $request->cookie(self::LANGUAGE_KEY, null)) || !isset($this->languagesAvailable[$lang])) {
			$lang = 'en';
		}
		if(($currentStep = array_search($request->uri->action, $this->steps)) === false) {
			$currentStep = 0;
		}
		if(!file_exists(\Aqua\ROOT . '/settings/application.php')) {
			try {
				$this->lastStep = App::cache()->fetch(self::STEP_KEY, 0);
				$this->config   = App::cache()->fetch(self::CONFIG_KEY, array());
			} catch(\Exception $e) {
				$this->lastStep = 0;
				$this->config   = array();
			}
			if($this->lastStep < $currentStep) {
				$response->status(302)
				         ->redirect(ac_build_url(array('action' => $this->steps[$this->lastStep])))
				         ->send();
				die;
			}
			$this->config = new Settings($this->config);
		} else if($currentStep !== (count($this->steps) - 1)) {
			$response->status(302)
			         ->redirect(ac_build_url(array('action' => $this->steps[(count($this->steps) - 1)])))
			         ->send();
			die;
		} else {
			$this->lastStep = $currentStep;
			$this->config   = new Settings(array());
		}
		$this->currentStep  = max(0, min($this->lastStep, $currentStep));
		$this->languageCode = $lang;
		$this->language     = include \Aqua\ROOT . "/install/language/$lang/language.php";
	}

	public function setLanguage($lang)
	{
		$lang = utf8_encode($lang);
		if(isset($this->languagesAvailable[$lang])) {
			$this->response->setCookie(self::LANGUAGE_KEY, array( 'value' => $lang, 'http_only' => true ));
		}

		return $this;
	}

	public function languageName($code = null)
	{
		if($code === null) {
			$code = $this->languageCode;
		}

		return (isset($this->languagesAvailable[$code]) ? $this->languagesAvailable[$code][0] : null);
	}

	public function languageDirection($code = null)
	{
		if($code === null) {
			$code = $this->languageCode;
		}

		return (isset($this->languagesAvailable[$code]) ? $this->languagesAvailable[$code][1] : null);
	}

	public function setStep($step)
	{
		$this->lastStep = max(0, $this->lastStep, $step);

		return $this;
	}

	public function nextStep()
	{
		return $this->setStep($this->currentStep + 1);
	}

	public function previousStep()
	{
		return $this->setStep($this->currentStep - 1);
	}

	public function connection()
	{
		if($this->dbh === null) {
			if(!$this->config->exists('database')) {
				$this->dbh = false;
			} else {
				$this->dbh = ac_mysql_connection(array(
					'host'     => $this->config->get('database')->get('host', ''),
					'port'     => $this->config->get('database')->get('port', ''),
					'database' => $this->config->get('database')->get('database', ''),
					'username' => $this->config->get('database')->get('username', ''),
					'password' => $this->config->get('database')->get('password', ''),
					'charset'  => $this->config->get('database')->get('charset', ''),
					'timezone' => $this->config->get('database')->get('timezone', ''),
				));
			}
		}

		return $this->dbh;
	}

	public function commit()
	{
		App::cache()->store(self::CONFIG_KEY, $this->config->toArray());
		App::cache()->store(self::STEP_KEY, $this->lastStep);

		return $this;
	}

	public function translate($key, $sprintf = array())
	{
		if(isset($this->language[$key])) {
			$key = $this->language[$key];
		}
		if(!empty($sprintf)) {
			array_unshift($sprintf, $key);
			$key = call_user_func_array('sprintf', $sprintf);
		}

		return $key;
	}

	public function clear()
	{
		$this->response->setCookie(self::LANGUAGE_KEY, array('value' => '', 'ttl' => -100));
		App::cache()->delete(self::CONFIG_KEY);
		App::cache()->delete(self::STEP_KEY);

		return $this;
	}
}
