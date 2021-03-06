<?php
namespace Page;

use Aqua\Core\App;
use Aqua\Core\L10n;
use Aqua\Core\Settings;
use Aqua\Log\ErrorLog;
use Aqua\Site\Page;
use Aqua\UI\Form;
use Aqua\UI\Menu;
use Aqua\UI\Search\Input;
use Aqua\UI\Template;
use Aqua\UI\Theme;
use Aqua\User\Account;
use Aqua\User\Role;

class Setup
extends Page
{
	public $dbh;
	/**
	 * @var \AquaCoreSetup
	 */
	public $setup;

	public function run()
	{
		$this->setup              = App::registryGet('setup');
		$this->theme              = new Theme('setup');
		$this->theme->template    = 'default';
		$this->theme->head->title = __setup('page-title');
		$options                  = array(
				'index'       => __setup('requirements'),
				'address'     => __setup('address'),
				'database'    => __setup('database'),
				'application' => __setup('application'),
				'email'       => __setup('email'),
				'phpass'      => __setup('phpass'),
				'account'     => __setup('account'),
				'install'     => __setup('install'),
				'finish'      => __setup('finish'),
			);
		$menu                     = new Menu;
		$i                        = 0;
		foreach($options as $key => $option) {
			$menu->append($key, array(
				'class' => array($this->setup->currentStep == $i ?
					       'active' : ($this->setup->lastStep > $i ?
						   'complete' : 'inactive')),
				'title' => $option,
				'url'   => ($this->setup->lastStep < $i ? '' : ac_build_url(array('action' => $key)))
			));
			++$i;
		}
		$this->theme->set('menu', $menu);
	}

	public function index_action()
	{
		$requirements = array(
			'php-requirements' => array(
				'php-version'  => version_compare(PHP_VERSION, '5.3.2', '>='),
				'auto-session' => !ini_get('session.auto_start'),
				'mbs-overload' => !ini_get('mbstring.func_overload')
			),
			'php-extensions'   => array(
				'pdo-ext'       => extension_loaded('pdo'),
				'mysql-ext'     => extension_loaded('pdo_mysql'),
				'phar-ext'      => extension_loaded('phar'),
				'mbstring-ext'  => extension_loaded('mbstring'),
				'gd2-ext'       => extension_loaded('gd'),
				'simplexml-ext' => extension_loaded('simplexml'),
				'dom-ext'       => extension_loaded('dom'),
				'ctype-ext'     => extension_loaded('ctype'),
				'zlib-ext'      => extension_loaded('zlib'),
			),
			'file-permission'  => array(
				'/.htaccess'     => $this->_access('w', '/.htaccess'),
				'/tmp'           => $this->_access('rw', '/tmp', '/tmp/cache', '/tmp/emblem'),
				'/tmp/error_log' => $this->_access('w', '/tmp/error_log'),
				'/uploads'       => $this->_access('w', '/uploads/*'),
				'/upgrade'       => $this->_access('rw', '/upgrade'),
				'/settings'      => $this->_access('rw', '/settings', '/settings/*'),
				'/plugins'       => $this->_access('rw', '/plugins'),
				'/schema'        => $this->_access('r', '/schema', 'schema/*', '/schema/*/*'),
			)
		);
		if($this->request->method === 'POST') {
			$this->response->status(302);
			$ok = true;
			foreach($requirements as $section) {
				foreach($section as $requirement) {
					if(!$requirement) {
						$ok = false;
						break 2;
					}
				}
			}
			if($ok) {
				$this->setup->nextStep();
				$this->response->redirect(App::request()->uri->url(array('action' => 'address')));
			}
		} else {
			$this->title = $this->theme->head->section = __setup('requirements');
			$tpl         = new Template;
			$tpl->set('requirements', $requirements)
			    ->set('page', $this);
			echo $tpl->render('setup/requirements');
		}
	}

	public function address_action()
	{
		$settings = $this->setup->config->get('address');
		$frm      = new Form($this->request);
		$frm->input('domain', true)
		    ->type('text')
		    ->required()
		    ->value(htmlspecialchars($settings->get('domain', \Aqua\DOMAIN)), false)
		    ->setLabel(__setup('url-domain-label'));
		$frm->input('base-dir', true)
		    ->type('text')
		    ->value(htmlspecialchars($settings->get('base_dir', \Aqua\DIR)), false)
		    ->setLabel(__setup('url-base-dir-label'))
		    ->setDescription(__setup('url-base-dir-desc'));
		$frm->checkbox('rewrite', true)
		    ->value(array('1' => ''))
		    ->checked($settings->get('rewrite_url', false) ? '1' : null, false)
		    ->setLabel(__setup('url-rewrite-label'))
		    ->setDescription(__setup('url-rewrite-desc'));
		$this->_setDefaultErrorMessages($frm);
		$frm->validate();
		if($frm->status !== Form::VALIDATION_SUCCESS) {
			$this->title = $this->theme->head->section = __setup('address');
			$tpl         = new Template;
			$tpl->set('form', $frm)
			    ->set('page', $this);
			echo $tpl->render('setup/address', 'setup/step');

			return;
		}
		$settings->set('domain', $this->request->getString('domain'));
		$settings->set('base_dir', trim($this->request->getString('base-dir'), "\t\n\r\0\x0B\\/"));
		$settings->set('rewrite_url', $this->request->getInt('rewrite'));
		$this->setup->nextStep();
		$this->response->status(302)->redirect(App::request()->uri->url(array('action' => 'database')));
	}

	public function database_action()
	{
		$settings = $this->setup->config->get('database');
		$frm      = new Form($this->request);
		$frm->input('host', true)
		    ->type('text')
		    ->required()
		    ->value(htmlspecialchars($settings->get('host', '127.0.0.1')), false)
		    ->setLabel(__setup('db-host-label'));
		$frm->input('port', true)
		    ->type('number')
		    ->required()
		    ->attr('min', 0)
		    ->value($settings->get('port', 3306), false)
		    ->setLabel(__setup('db-port-label'));
		$frm->input('database', true)
		    ->type('text')
		    ->required()
		    ->value($settings->get('database', ''), false)
		    ->setLabel(__setup('db-database-label'));
		$frm->input('username', true)
		    ->type('text')
		    ->required()
		    ->value(htmlspecialchars($settings->get('username', 'root')), false)
		    ->setLabel(__setup('db-username-label'));
		$frm->input('password', true)
		    ->type('password')
		    ->value(htmlspecialchars($settings->get('password', '')), false)
		    ->setLabel(__setup('db-password-label'));
		$frm->input('timezone', true)
		    ->type('text')
		    ->value(htmlspecialchars($settings->get('timezone', '')), false)
		    ->setLabel(__setup('db-timezone-label'))
		    ->setDescription(__setup('db-timezone-desc'));
		$frm->input('charset', true)
		    ->type('text')
		    ->value(htmlspecialchars($settings->get('charset', 'UTF8')), false)
		    ->setLabel(__setup('db-charset-label'));
		$frm->input('prefix', true)
		    ->type('text')
		    ->attr('maxlength', 50)
		    ->value(htmlspecialchars($settings->get('prefix', 'ac_')), false)
		    ->setLabel(__setup('db-prefix-label'));
		$this->_setDefaultErrorMessages($frm);
		$frm->validate(function (Form $frm, &$message) {
			try {
				if($prefix = $frm->request->getString('prefix')) {
					if(preg_match_all('/[^a-z1-9\$\:\-\_]/i', $prefix, $match)) {
						$frm->field('prefix')
							->setWarning(__setup('prefix-invalid-char', implode(', ', array_unique($match[0]))));

						return false;
					}
				}
				$dbh = ac_mysql_connection(array(
						'host'     => $frm->request->getString('host'),
						'port'     => $frm->request->getInt('port'),
						'database' => $frm->request->getString('database'),
						'username' => $frm->request->getString('username'),
						'password' => $frm->request->getString('password'),
						'timezone' => $frm->request->getString('timezone'),
						'charset'  => $frm->request->getString('charset'),
					));
				unset($dbh);

				return true;
			} catch(\PDOException $exception) {
				if(($info = $exception->errorInfo) && count($info) === 3) {
					$code = $info[1];
				} else {
					$code = $exception->getCode();
				}
				switch($code) {
					case 2002:
						$message = __setup('pdo-connection-failed', $exception->getCode(), $exception->getMessage());
						break;
					case 1298:
						$message = __setup('pdo-invalid-timezone', $exception->getCode(), $exception->getMessage());
						break;
					case 1045:
						$message = __setup('pdo-access-denied', $exception->getCode(), $exception->getMessage());
						break;
					case 1049:
						$message = __setup('pdo-unknown-db', $exception->getCode(), $exception->getMessage());
						break;
					default:
						$message = __setup('pdo-exception', $exception->getCode(), $exception->getMessage());
						break;
				}

				return false;
			}
		});
		if($frm->status !== Form::VALIDATION_SUCCESS) {
			$this->title = $this->theme->head->section = __setup('database');
			$tpl         = new Template;
			$tpl->set('form', $frm)
			    ->set('page', $this);
			echo $tpl->render('setup/database', 'setup/step');

			return;
		}
		$settings->set('host', trim($this->request->getString('host')));
		$settings->set('port', $this->request->getInt('port'));
		$settings->set('database', trim($this->request->getString('database')));
		$settings->set('username', $this->request->getString('username'));
		$settings->set('password', $this->request->getString('password'));
		$settings->set('timezone', trim($this->request->getString('timezone')));
		$settings->set('charset', trim($this->request->getString('charset')));
		$settings->set('prefix', trim($this->request->getString('prefix')));
		if($prefix = $settings->get('prefix', '')) {
			$settings->set('prefix', rtrim($prefix, '_') . '_');
		}
		$this->setup->nextStep();
		$this->response->status(302)->redirect(App::request()->uri->url(array('action' => 'application')));
	}

	public function application_action()
	{
		$settings = $this->setup->config->get('application');
		$frm      = new Form($this->request);
		$frm->input('title', true)
		    ->type('text')
		    ->value(htmlspecialchars($settings->get('title', '')), false)
		    ->setLabel(__setup('app-title-label'));
		$frm->select('ssl', true)
		    ->value(array(
				0 => __setup('ssl-0'),
				1 => __setup('ssl-1'),
				2 => __setup('ssl-2')
			))
		    ->selected($settings->get('ssl', 0), false)
		    ->setLabel(__setup('app-ssl-label'))
		    ->setDescription(__setup('app-ssl-desc'));
		$languages = $this->setup->languagesAvailable;
		foreach($languages as &$lang) {
			$lang = $lang[0];
		}
		$frm->select('language', true)
		    ->value($languages)
		    ->selected($settings->get('language', key($languages)), false)
		    ->setLabel(__setup('app-language-label'));
		$frm->input('timezone', true)
		    ->type('text')
		    ->value($settings->get('timezone', ''), false)
		    ->setLabel(__setup('app-timezone-label'))
		    ->setDescription(__setup('app-timezone-desc'));
		if(!DEFAULT_TIMEZONE) {
			$frm->input('timezone')->required();
		}
		$frm->checkbox('ob', true)
		    ->value(array('1' => ''))
		    ->checked($settings->get('output_compression', true) ? '1' : null, false)
		    ->setLabel(__setup('app-ob-label'))
		    ->setDescription(__setup('app-ob-desc'));
		$this->_setDefaultErrorMessages($frm);
		$frm->validate(function (Form $frm) {
			if($timezone = $frm->request->getString('timezone')) {
				try {
					new \DateTimeZone($timezone);
				} catch(\Exception $exception) {
					$frm->field('timezone')->setWarning(__setup('invalid-timezone'));

					return false;
				}
			}

			return true;
		});
		if($frm->status !== Form::VALIDATION_SUCCESS) {
			$this->title = $this->theme->head->section = __setup('application');
			$tpl         = new Template;
			$tpl->set('form', $frm)
			    ->set('page', $this);
			echo $tpl->render('setup/application', 'setup/step');

			return;
		}
		$settings->set('title', $this->request->getString('title'));
		$settings->set('ssl', $this->request->getInt('ssl'));
		$settings->set('language', $this->request->getString('language'));
		$settings->set('timezone', $this->request->getString('timezone'));
		$settings->set('output_compression', (bool)$this->request->getInt('ob'));
		$this->setup->nextStep();
		$this->response->status(302)->redirect(App::request()->uri->url(array('action' => 'email')));
	}

	public function email_action()
	{
		$settings = $this->setup->config->get('email');
		$frm      = new Form($this->request);
		$frm->input('address', true)
		    ->type('email')
		    ->required()
		    ->value(htmlspecialchars($settings->get('from_address', '')), false)
		    ->setLabel(__setup('mail-address-label'));
		$frm->input('name', true)
		    ->type('text')
		    ->required()
		    ->value(htmlspecialchars($settings->get('from_name', '')), false)
		    ->setLabel(__setup('mail-sender-label'));
		$frm->checkbox('smtp', true)
		    ->value(array('1' => ''))
		    ->checked($settings->get('use_smtp', false) ? '1' : null, false)
		    ->setLabel(__setup('mail-smtp-label'));
		$frm->input('smtp-host', true)
		    ->type('text')
		    ->value(htmlspecialchars($settings->get('smtp_host', '')), false)
		    ->setLabel(__setup('mail-smtp-host-label'));
		$frm->input('smtp-port', true)
		    ->type('number')
		    ->attr('min', 0)
		    ->value(htmlspecialchars($settings->get('smtp_port', 25)), false)
		    ->setLabel(__setup('mail-smtp-port-label'));
		$frm->select('smtp-encryption', true)
		    ->value(array(
				''    => __setup('smtp-none'),
				'tls' => __setup('smtp-tls'),
				'ssl' => __setup('smtp-ssl'),
			))
		    ->selected(htmlspecialchars($settings->get('smtp_encryption', '')), false)
		    ->setLabel(__setup('mail-smtp-encryption-label'));
		$frm->input('smtp-username', true)
		    ->type('text')
		    ->value(htmlspecialchars($settings->get('smtp_username', '')), false)
		    ->setLabel(__setup('mail-smtp-username-label'))
		    ->setDescription(__setup('mail-smtp-username-desc'));
		$frm->input('smtp-password', true)
		    ->type('password')
		    ->value(htmlspecialchars($settings->get('smtp_password', '')), false)
		    ->setLabel(__setup('mail-smtp-password-label'));
		$this->_setDefaultErrorMessages($frm);
		$frm->validate();
		if($frm->status !== Form::VALIDATION_SUCCESS) {
			$this->title = $this->theme->head->section = __setup('email');
			$tpl         = new Template;
			$tpl->set('form', $frm)
			    ->set('page', $this);
			echo $tpl->render('setup/email', 'setup/step');

			return;
		}
		$settings->set('from_address', $this->request->getString('address'));
		$settings->set('from_name', $this->request->getString('name'));
		$settings->set('use_smtp', (bool)$this->request->getInt('smtp'));
		$settings->set('smtp_host', $this->request->getString('smtp-host'));
		$settings->set('smtp_port', $this->request->getInt('smtp-port'));
		$settings->set('smtp_encryption', $this->request->getString('smtp-encryption'));
		$settings->set('smtp_username', $this->request->getString('smtp-username'));
		$settings->set('smtp_password', $this->request->getString('smtp-password'));
		$this->setup->nextStep();
		$this->response->status(302)->redirect(App::request()->uri->url(array('action' => 'phpass')));
	}

	public function phpass_action()
	{
		$settings = $this->setup->config->get('phpass');
		$frm      = new Form($this->request);
		$frm->select('adapter', true)
		    ->value(array(
				'bcrypt'   => 'BCrypt',
				'md5'      => 'MD5',
				'pbkdf2'   => 'PBKDF2',
				'sha1'     => 'Sha1',
				'sha256'   => 'Sha256',
				'sha512'   => 'Sha512',
				'portable' => 'Portable'
			))
		    ->selected($settings->get('adapter', 'bcrypt'), false)
		    ->setLabel(__setup('pw-hash-label'));
		if(!version_compare(PHP_VERSION, '5.3.7', '<')) {
			$frm->select('identifier', true)
				->value(array(
					'2a' => '2a',
				    '2x' => '2x',
				    '2y' => '2y'
				))
				->selected($settings->get('identifier', '2y'), false)
				->setLabel(__setup('pw-identifier-label'));
		}
		$frm->input('bcrypt-iteration', true)
			->type('number')
			->attr('min', 4)
			->attr('max', 31)
			->value($settings->get('bcrypt_iteration', 12), false)
			->setLabel(__setup('pw-itercountlog2-label'))
			->setDescription(__setup('pw-itercountlog2-desc'));
		$frm->input('pbkdf2-iteration', true)
			->type('number')
			->attr('min', 1)
			->attr('max', 4294967296)
			->value($settings->get('pbkdf2_iteration', 12000), false)
			->setLabel(__setup('pw-itercount-label'))
			->setDescription(__setup('pw-itercount-desc'));
		$frm->input('sha1-iteration', true)
			->type('number')
			->attr('min', 1)
			->attr('max', 4294967296)
			->value($settings->get('sha1_iteration', 40000), false)
			->setLabel(__setup('pw-itercount-label'))
			->setDescription(__setup('pw-itercount-desc'));
		$frm->input('sha256-iteration', true)
			->type('number')
			->attr('min', 100)
			->attr('max', 999999)
			->value($settings->get('sha1_iteration', 80000), false)
			->setLabel(__setup('pw-itercount-label'))
			->setDescription(__setup('pw-itercount-desc'));
		$frm->input('sha512-iteration', true)
			->type('number')
			->attr('min', 100)
			->attr('max', 999999)
			->value($settings->get('sha512_iteration', 60000), false)
			->setLabel(__setup('pw-itercount-label'))
			->setDescription(__setup('pw-itercount-desc'));
		$frm->input('portable-iteration', true)
			->type('number')
			->attr('min', 7)
			->attr('max', 30)
			->value($settings->get('portable_iteration', 12), false)
			->setLabel(__setup('pw-itercountlog2-label'))
			->setDescription(__setup('pw-itercountlog2-desc'));
		$frm->select('digest', true)
			->value(array(
				'sha1'   => 'Sha1',
			    'sha256' => 'Sha256',
			    'sha512' => 'Sha512',
			))
			->selected($settings->get('digest', 'sha512'), false)
			->setLabel(__setup('pw-digest-label'))
			->setDescription(__setup('pw-digest-desc'));
		$this->_setDefaultErrorMessages($frm);
		$frm->validate();
		if($frm->status !== Form::VALIDATION_SUCCESS) {
			$this->title = $this->theme->head->section = __setup('phpass');
			$tpl         = new Template;
			$tpl->set('form', $frm)
			    ->set('page', $this);
			echo $tpl->render('setup/phpass', 'setup/step');

			return;
		}
		$settings->data = array();
		$settings->size = 0;
		$settings->set('adapter', $this->request->getString('adapter'));
		switch($settings->get('adapter')) {
			case 'bcrypt':
				$settings->set('bcrypt_iteration', $this->request->getInt('bcrypt-iteration'))
					->set('identifier', $this->request->getString('identifier', '2a'));
				break;
			case 'pbkdf2':
				$settings->set('pbkdf2_iteration', $this->request->getInt('pbkdf2-iteration'))
					->set('digest', $this->request->getInt('digest'));
				break;
			case 'portable':
				$settings->set('portable_iteration', $this->request->getInt('portable-iteration'));
				break;
			case 'sha1':
				$settings->set('sha1_iteration', $this->request->getInt('sha1-iteration'));
				break;
			case 'sha256':
				$settings->set('sha256_iteration', $this->request->getInt('sha256-iteration'));
				break;
			case 'sha512':
				$settings->set('sha512_iteration', $this->request->getInt('sha512-iteration'));
				break;
		}
		$this->setup->nextStep();
		$this->response->status(302)->redirect(App::request()->uri->url(array('action' => 'account')));
	}

	public function account_action()
	{
		$settings = $this->setup->config->get('account');
		$frm = new Form($this->request);
		$frm->input('username', true)
			->type('text')
			->required()
			->value(htmlspecialchars($settings->get('username', '')), false)
			->setLabel(__setup('acc-username-label'));
		$frm->input('display-name', true)
			->type('text')
			->required()
			->value(htmlspecialchars($settings->get('display_name', '')), false)
			->setLabel(__setup('acc-display-name-label'));
		$frm->input('email', true)
			->type('text')
			->required()
			->value(htmlspecialchars($settings->get('email', '')), false)
			->setLabel(__setup('acc-email-label'));
		$frm->input('password', true)
			->type('password')
			->required()
			->value(htmlspecialchars($settings->get('password', '')), false)
			->setLabel(__setup('acc-password-label'));
		$frm->input('repeat-password', true)
			->type('password')
			->required()
			->value(htmlspecialchars($settings->get('password', '')), false)
			->setLabel(__setup('acc-repeat-password-label'));
		$frm->input('birthday', true)
			->type('date')
			->placeholder('YYYY-MM-DD')
			->required()
			->value($settings->exists('birthday') ? date('Y-m-d', $settings->get('birthday')) : null, false)
			->setLabel(__setup('acc-birthday-label'));
		$this->_setDefaultErrorMessages($frm);
		$frm->validate(function(Form $frm) {
			if(!($date = \DateTime::createFromFormat('Y-m-d', $frm->request->getString('birthday'))) || $date->getTimestamp() > time()) {
				$frm->field('birthday')->setWarning(__setup('invalid-birthday'));
				return false;
			} else if(Account::checkValidBirthday($date->getTimestamp(), $message)) {
				$frm->field('birthday')->setWarning(__setup('invalid-birthday'));
				return false;
			} else if(Account::checkValidUsername($frm->request->getString('username'), $message)) {
				$frm->field('username')->setWarning(__setup("err-$message"));
				return false;
			} else if(Account::checkValidDisplayName($frm->request->getString('display-name'), $message)) {
				$frm->field('display-name')->setWarning(__setup("err-$message"));
				return false;
			} else if(Account::checkValidEmail($frm->request->getString('email'), $message)) {
				$frm->field('email')->setWarning(__setup("err-$message"));
				return false;
			} else if(Account::checkValidPassword($frm->request->getString('password'), $message)) {
				$frm->field('password')->setWarning(__setup("err-$message"));
				return false;
			} else if($frm->request->getString('password') !== $frm->request->getString('repeat-password')) {
				$frm->field('repeat-password')->setWarning(__setup("err-$message"));
				return false;
			}
			return true;
		});
		if($frm->status !== Form::VALIDATION_SUCCESS) {
			$this->title = $this->theme->head->section = __setup('account');
			$tpl         = new Template;
			$tpl->set('form', $frm)
			    ->set('page', $this);
			echo $tpl->render('setup/account', 'setup/step');

			return;
		}
		$settings
			->set('username', trim($this->request->getString('username')))
			->set('display_name', trim($this->request->getString('display-name')))
			->set('email', trim($this->request->getString('email')))
			->set('password', trim($this->request->getString('password')))
			->set('birthday', \DateTime::createFromFormat('Y-m-d', $this->request->getString('birthday'))->getTimestamp());
		$this->setup->nextStep();
		$this->response->status(302)->redirect(App::request()->uri->url(array('action' => 'install')));
	}

	public function install_action($step = null, $progress = 1)
	{
		if($step === null || !$this->request->ajax) {
			$this->title = $this->theme->head->section = __setup('install');
			$tpl = new Template;
			$tpl->set('page', $this);
			echo $tpl->render('setup/install');

			return;
		}
		try {
			$this->theme = new Theme;
			$this->response->setHeader('Content-Type', 'application/json');
			$progress = intval($progress);
			$response = array();
			switch($step) {
				case 'start':
					App::cache()->delete('setup_settings_full');
					$response = array(
						'prepare-config'     => __setup('step-prepare-config'),
						'setup-files'        => __setup('step-setup-files'),
						'create-tables'      => __setup('step-create-tables'),
						'populate-tables'    => __setup('step-populate-tables'),
						'insert-namespaces'  => __setup('step-insert-namespaces'),
						'insert-emails'      => __setup('step-insert-emails'),
						'write-config'       => __setup('step-write-config'),
					);
					break;
				case 'prepare-config':
					$settings = new Settings(include \Aqua\ROOT . '/settings/application.example.php');
					$config = $this->setup->config;
					$settings->merge($config->get('address'), true);
					$settings->merge($config->get('application'), true);
					$settings->get('db')->merge($config->get('database'), true);
					$settings->get('email')->merge($config->get('email'), true);
					$phpass = $config->get('phpass');
					switch($config->get('phpass')->get('adapter', '')) {
						case 'bcrypt':
							$settings->get('account')
								->get('phpass')
								->get('adapter')
								->set('adapter', 'bcrypt')
								->set('iterationcountlog2', $phpass->get('bcrypt_iteration', 12))
								->set('identifier', $phpass->get('identifier', '2a'));
							break;
						case 'pbkdf2':
							$settings->get('account')
								->get('phpass')
								->get('adapter')
								->set('adapter', 'pbkdf2')
								->set('iterationcount', $phpass->get('pbkdf2_iteration', 12000))
								->set('digest', $phpass->get('digest', '2a'));
							break;
						case 'portable':
							$settings->get('account')
								->get('phpass')
								->get('adapter')
								->set('adapter', 'portable')
								->set('iterationcountlog2', $phpass->get('portable_iteration', 12));
							break;
						case 'sha1':
							$settings->get('account')
								->get('phpass')
								->get('adapter')
								->set('adapter', 'sha1')
								->set('iterationcount', $phpass->get('sha1_iteration', 40000));
							break;
						case 'sha256':
							$settings->get('account')
								->get('phpass')
								->get('adapter')
								->set('adapter', 'sha256')
								->set('iterationcount', $phpass->get('256_iteration', 80000));
							break;
						case 'sha512':
							$settings->get('account')
								->get('phpass')
								->get('adapter')
								->set('adapter', 'sha512')
								->set('iterationcount', $phpass->get('512_iteration', 60000));
							break;
						case 'md5':
							$settings->get('account')
							         ->get('phpass')
							         ->get('adapter')
							         ->set('adapter', 'md5');
							break;
					}
					$file = \Aqua\ROOT . '/install/language/' . $config->get('application')->get('language', 'en') . '/language.xml';
					$xml  = new \SimpleXMLElement(file_get_contents($file));
					$settings->set('language', array(
						'name'      => (string)$xml->language->name,
						'code'      => (string)$xml->language->code,
						'direction' => strtoupper((string)$xml->language->direction ?: 'LTR')
					));
					$locales = array();
					foreach($xml->language->locale as $lc) {
						$locales[] = (string)$lc;
					}
					$settings->get('language')->set('locales', $locales);
					$settings->set('cron_key', bin2hex(secure_random_bytes(32)));
					App::cache()->store('setup_settings_full', $settings->toArray());
					$response = array( 'progress' => array( 1, 1 ) );
					break;
				case 'setup-files':
					$directories = array(
						'/tmp/emblem',
						'/tmp/chargen',
						'/tmp/chargen/body',
						'/tmp/chargen/head',
						'/tmp/error_log',
						'/tmp/lang',
						'/tmp/session',
						'/uploads/avatar',
						'/uploads/content',
						'/uploads/smiley',
						'/uploads/application',
					);
					$old = umask(0);
					foreach($directories as $path) {
						$path = \Aqua\ROOT . $path;
						if(!is_dir($path)) {
							if(substr($path, 0, 4) === '/tmp') {
								mkdir($path, \Aqua\PRIVATE_DIRECTORY_PERMISSION, true);
							} else {
								mkdir($path, \Aqua\PUBLIC_DIRECTORY_PERMISSION, true);
							}
						}
					}
					foreach(array( 'ragnarok.php', 'ckeditor.php' ) as $name) {
						$file = \Aqua\ROOT . "/settings/$name";
						if(!file_exists($file)) {
							file_put_contents($file, "<?php\r\nreturn array();");
							chmod($file, \Aqua\PRIVATE_FILE_PERMISSION);
						}
					}
					umask($old);
					$response = array( 'progress' => array( 1, 1 ) );
					break;
				case 'create-tables':
					$progress = min(2, max(1, $progress));
					$prefix = $this->setup->config->get('database')->get('prefix', '');
					switch($progress) {
						case 1:
							$query = file_get_contents(\Aqua\ROOT . '/schema/aquacore.sql');
							break;
						case 2:
							$query = file_get_contents(\Aqua\ROOT . '/schema/disablePlugin.sql');
							break;
						default: break 2;
					}
					$query = str_replace('#', $prefix, $query);
					App::connection()->exec($query);
					$response = array( 'progress' => array( $progress, 2 ) );
					break;
				case 'populate-tables':
					$progress = min(2, max(1, $progress));
					switch($progress) {
						case 1:
							$query = file_get_contents(\Aqua\ROOT . '/schema/inserts.sql');
							$query = str_replace('#', $this->setup->config->get('database')->get('prefix', ''), $query);
							$query = str_replace('{rss-settings}', serialize(array( 'title' => $this->setup->config->get('application')->get('title', '') )), $query);
							App::connection()->exec($query);
							break;
						case 2:
							$settings = $this->setup->config->get('account');
							if($settings->get('skip', false) || $settings->exists('account_id')) {
								break;
							}
							$account = Account::register(
								$settings->get('username', ''),
								$settings->get('display_name', ''),
								$settings->get('password', ''),
								$settings->get('email', ''),
								$settings->get('birthday', ''),
								Role::ROLE_ADMIN,
								Account::STATUS_NORMAL,
								true
							);
							$settings->set('account_id', $account ? $account->id : null);
							break;
					}
					$response = array( 'progress' => array( $progress, 2 ) );
					break;
				case 'insert-namespaces':
					set_time_limit(120);
					L10n::init();
					$dir = \Aqua\ROOT . '/install/language/' . L10n::$code;
					$namespaces = glob("$dir/namespaces/*.xml");
					array_unshift($namespaces, $dir . '/language.xml');
					$count = 2;
					$max = ceil(count($namespaces) / $count);
					$progress = min($max, max(0, $progress));
					for($i = 0, $j = ($progress - 1) * $count; $i < $count; ++$i, ++$j) {
						if(!isset($namespaces[$j])) {
							break;
						}
						L10n::import(new \SimpleXMLElement(file_get_contents($namespaces[$j])));
					}
					$response = array( 'progress' => array( $progress, $max ) );
					break;
				case 'insert-emails':
					L10n::init();
					$dir = \Aqua\ROOT . '/install/language/' . L10n::$code;
					$emails = glob("$dir/emails/*.xml");
					foreach($emails as $path) {
						L10n::import(new \SimpleXMLElement(file_get_contents($path)));
					}
					$response = array( 'progress' => array( 1, 1 ) );
					break;
				case 'write-config':
					if(!file_exists(\Aqua\ROOT . '/upgrade/version')) {
						file_put_contents(\Aqua\ROOT . '/upgrade/version', App::VERSION);
					}
					App::settings()->export(\Aqua\ROOT . '/settings/application.php');
					App::cache()->delete('setup_settings_full');
					$this->setup->clear();
					$response = array( 'progress' => array( 1, 1 ) );
					break;
			}
		} catch(\Exception $exception) {
			ErrorLog::logText($exception);
			$response = array( 'error' => $exception->getMessage() );
			$this->response->status(500);
		}
		echo json_encode($response);
	}

	public function finish_action()
	{
		$this->title = $this->theme->head->section = __setup('finish');
		$tpl = new Template;
		$tpl->set('page', $this);
		echo $tpl->render('setup/finish');
	}

	protected function _access($type)
	{
		$directories = func_get_args();
		array_shift($directories);
		$read = (strpos($type, 'r') !== false);
		$write = (strpos($type, 'w') !== false);
		foreach($directories as $dir) {
			foreach(glob($dir) as $path) {
				if($read && !is_readable($path)) {
					return false;
				} else if($write && !is_writable($path)) {
					return false;
				}
			}
		}
		return true;
	}

	protected function _setDefaultErrorMessages(Form $form)
	{
		foreach($form->content as $field) {
			if($field instanceof Form\Input) {
				$field->setDefaultErrorMessage(__setup('err-invalid-length'), Input::VALIDATION_INVALID_LENGTH);
				$field->setDefaultErrorMessage(__setup('err-invalid-pattern'), Input::VALIDATION_PATTERN);
				$field->setDefaultErrorMessage(__setup('err-required'), Input::VALIDATION_EMPTY_VALUE);
				switch($field->getAttr('type')) {
					case 'date':
					case 'datetime':
					case 'time':
						$field->setDefaultErrorMessage(__setup('err-invalid-date'), Input::VALIDATION_INVALID_TYPE);
						$field->setDefaultErrorMessage(__setup('err-invalid-date-range'), Input::VALIDATION_INVALID_RANGE);
						break;
					case 'email':
						$field->setDefaultErrorMessage(__setup('err-invalid-email'), Input::VALIDATION_INVALID_TYPE);
						break;
					case 'url':
						$field->setDefaultErrorMessage(__setup('err-invalid-url'), Input::VALIDATION_INVALID_TYPE);
						break;
					case 'number':
					case 'range':
						$field->setDefaultErrorMessage(__setup('err-invalid-number'), Input::VALIDATION_INVALID_TYPE);
						break;
				}
			} else if($field instanceof Form\Checkbox) {
				$field->setDefaultErrorMessage(__setup('err-invalid-option'), Form\Checkbox::VALIDATION_INVALID_OPTION);
			} else if($field instanceof Form\Select) {
				$field->setDefaultErrorMessage(__setup('err-invalid-option'), Form\Select::VALIDATION_INVALID_OPTION);
				$field->setDefaultErrorMessage(__setup('err-required'), Form\Select::VALIDATION_FIELD_REQUIRED);
			}
		}
		reset($form->content);
	}
}
