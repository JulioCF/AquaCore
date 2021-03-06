<?php
use Aqua\Core\L10n;
use Aqua\Core\App;
use Aqua\Ragnarok\Character;
use Aqua\Http\Uri;

/**
 * @param string $namespace
 * @param string|int $key
 * @return string
 */
function __($namespace, $key)
{
	$arguments = func_get_args();
	return L10n::replace(
		array_shift($arguments), // namespace
		array_shift($arguments), // string
		$arguments               // sprintf arguments
	);
}

/**
 * @param string $table
 * @return string
 */
function ac_table($table)
{
	return '`' . \Aqua\TABLE_PREFIX . $table . '`';
}

function ac_form_path($url = null, $action = null, $arg = null, $force = false)
{
	if(\Aqua\REWRITE && !$force) {
		return '';
	}
	if(!$url) {
		static $current_url;
		if(!$current_url) {
			$uri = App::request()->uri;
			if(!empty($uri->path)) {
				$path = implode(\Aqua\URL_SEPARATOR, $uri->path);
				$current_url.= "<input type=\"hidden\" name=\"path\" value=\"{$path}\">";
			}
			if($uri->action && $uri->action !== 'index') {
				$current_url.= "<input type=\"hidden\" name=\"action\" value=\"{$uri->action}\">";
			}
			if(!empty($uri->arguments)) {
				$arg = implode(\Aqua\URL_SEPARATOR, $uri->arguments);
				$current_url.= "<input type=\"hidden\" name=\"arg\" value=\"{$arg}\">";
			}
		}
		return $current_url;
	} else if($url instanceof Uri) {
		$html = '';
		if(!empty($url->path)) {
			$path = implode(\Aqua\URL_SEPARATOR, $url->path);
			$html.= "<input type=\"hidden\" name=\"path\" value=\"{$path}\">";
		}
		if($url->action && $url->action !== 'index') {
			$html.= "<input type=\"hidden\" name=\"action\" value=\"{$url->action}\">";
		}
		if(!empty($url->arguments)) {
			$arg = implode(\Aqua\URL_SEPARATOR, $url->arguments);
			$html.= "<input type=\"hidden\" name=\"arg\" value=\"{$arg}\">";
		}
		return $html;
	} else if(is_array($url)) {
		$html = '';
		if(!empty($url)) {
			$path = implode('.', $url);
			$html.= "<input type=\"hidden\" name=\"path\" value=\"{$path}\">";
		}
		if($action && $action !== 'index') {
			$html.= "<input type=\"hidden\" name=\"action\" value=\"{$action}\">";
		}
		if(!empty($arg)) {
			$arg = implode('.', $arg);
			$html.= "<input type=\"hidden\" name=\"arg\" value=\"{$arg}\">";
		}
		return $html;
	} else {
		return '';
	}
}

function ac_skill_icon($skill_id) { return Aqua\URL . "/assets/images/skills/{$skill_id}.gif"; }
function ac_item_icon($item_id) { return Aqua\URL . "/assets/images/item/icon/{$item_id}.png"; }
function ac_item_collection($item_id) { return Aqua\URL . "/assets/images/item/collection/{$item_id}.png"; }
function ac_item_cardbmp($item_id) { return Aqua\URL . "/assets/images/item/cardbmp/{$item_id}.bmp"; }
function ac_mob_sprite($mob_id) { return Aqua\URL . "/assets/images/mob/{$mob_id}.gif"; }
function ac_guild_emblem($serverName, $charMapName, $guildId) {
	if(\Aqua\REWRITE) {
		return \Aqua\URL . sprintf('/guild/%s/%s/%d', $serverName, $charMapName, $guildId);
	} else {
		return \Aqua\URL . '/img.php?' . http_build_query(array(
			'x' => 'guild',
			's' => $serverName,
			'c' => $charMapName,
			'i' => $guildId
		));
	}
}
function ac_char_head(Character $char) {
	if(\Aqua\REWRITE) {
		return \Aqua\URL . sprintf('/head/%s/%s/%d', $char->charmap->server->key, $char->charmap->key, $char->id);
	} else {
		return \Aqua\URL . '/img.php?' . http_build_query(array(
			'x' => 'head',
			's' => $char->charmap->server->key,
			'c' => $char->charmap->key,
			'i' => $char->id
		));
	}
}
function ac_char_body(Character $char) {
	if(\Aqua\REWRITE) {
		return \Aqua\URL . sprintf('/char/%s/%s/%d', $char->charmap->server->key, $char->charmap->key, $char->id);
	} else {
		return \Aqua\URL . '/img.php?' . http_build_query(array(
			'x'  => 'body',
			's' => $char->charmap->server->key,
			'c' => $char->charmap->key,
			'i' => $char->id
		));
	}
}

function ac_server_status($ip, $port, $timeout = 1)
{
	$sock = @fsockopen($ip, $port, $errno, $errstr, (int)$timeout);
	if(is_resource($sock)) {
		fclose($sock);
		return true;
	} else {
		return false;
	}
}

function ac_build_url(array $options)
{
	$options += array(
		'protocol'    => \Aqua\HTTPS ? 'https://' : 'http://',
		'username'    => null,
		'password'    => null,
		'url_rewrite' => Aqua\REWRITE,
		'subdomain'   => null,
		'domain'      => Aqua\DOMAIN,
	);
	$url = $options['protocol'];
	if($options['username']) {
		$url.= $options['username'];
		if($options['password']) {
			$url.= ':' . $options['password'];
		}
		$url.= '@';
	}
	if($options['subdomain']) {
		$url.= $options['subdomain'] . '.';
	}
	$url.= rtrim($options['domain'], '/');
	$url.= ac_build_path($options);
	return $url;
}

function ac_build_path(array $options) {
	$options+= array(
		'url_rewrite' => \Aqua\REWRITE,
		'base_dir'    => \Aqua\DIR . '/' . \Aqua\WORKING_DIR,
	    'script'      => \Aqua\SCRIPT_NAME
	);
	$url = '/';
	if(strcasecmp($options['script'], 'index.php') === 0) {
		$options['script'] = null;
	}
	if($options['base_dir'] && ($baseDir = trim($options['base_dir'], '/'))) {
		$url.= "$baseDir/";
	}
	if($options['script']) {
		$url.= $options['script'];
	}
	if($query = ac_build_query($options)) {
		if($query[0] === '/' && !$options['script']) {
			$url.= substr($query, 1);
		} else {
			$url.= $query;
		}
	}
	return $url;
}

function ac_build_query(array $options)
{
	$options+= array(
		'url_rewrite' => false,
		'path'        => array(),
		'action'      => 'index',
		'arguments'   => array(),
		'query'       => array(),
		'hash'        => null,
	);
	$query = '';
	if(!$options['url_rewrite']) {
		$q = array();
		if($options['path']) {
			$q['path'] = implode(\Aqua\URL_SEPARATOR, array_map('urlencode', $options['path']));
		}
		if($options['action'] !== 'index' || $options['arguments']) {
			$q['action'] = urlencode($options['action']);
			if($options['arguments']) {
				$q['arg'] = implode(\Aqua\URL_SEPARATOR, array_map('urlencode', $options['arguments']));
			}
		}
		$options['query'] = $q + $options['query'];
	} else {
		foreach($options['path'] as $path) {
			$query.= '/' . urlencode($path);
		}
		$query = substr($query, 1);
		if($options['arguments'] || $options['action'] !== 'index') {
			if(!empty($options['path'])) {
				$query.= '/';
			}
			$query.= 'action/' . urlencode($options['action']);
			foreach($options['arguments'] as $arg) {
				$query.= '/' . urlencode($arg);
			}
		}
		if(!empty($query)) {
			$query = "/$query";
		}
	}
	if(!empty($options['query'])) {
		$query.= '?' . http_build_query($options['query']);
	}
	if($options['hash']) {
		$query.= '#' . $options['hash'];
	}
	return $query;
}

/**
 * Returns true depending on the given probability
 *
 * @param array|string|integer|float $probability array(10, 200) = Ten out of 200 times, same as 20%. 20.0 and 20 = 20%
 * @return bool
 */
function ac_probability($probability)
{
	if(is_string($probability)) {
		$probability = doubleval($probability);
	}
	if(is_double($probability)) {
		$divisor = pow(10, strlen(substr(strrchr((string)$probability, '.'), 1)) + 2);
		$probability *= $divisor/10000;
	} else if(is_array($probability)) {
		$divisor     = (int)$probability[1];
		$probability = (int)$probability[0];
	} else {
		$divisor     = 100;
		$probability = (int)$probability;
	}
	if((mt_rand() % $divisor) < $probability) {
		return true;
	}
	return false;
}

/**
 * Creates a safe url name from the given string
 *
 * @param string $title
 * @param int    $maxSize
 * @return string
 */
function ac_slug($title, $maxSize = 255)
{
	$cyrylicFrom = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й',
	                     'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф',
	                     'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я',
	                     'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й',
	                     'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф',
	                     'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я');
	$cyrylicTo   = array('A', 'B', 'W', 'G', 'D', 'Ie', 'Io', 'Z', 'Z', 'I', 'J', 'K',
	                     'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'Ch', 'C',
	                     'Tch', 'Sh', 'Shtch', '', 'Y', '', 'E', 'Iu', 'Ia', 'a', 'b',
	                     'w', 'g', 'd', 'ie', 'io', 'z', 'z', 'i', 'j', 'k', 'l', 'm',
	                     'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'ch', 'c', 'tch', 'sh',
	                     'shtch', '', 'y', '', 'e', 'iu', 'ia');
	$from = array("Á", "À", "Â", "Ä", "Ă", "Ā", "Ã", "Å","Ą", "Æ", "Ć", "Ċ", "Ĉ", "Č", "Ç", "Ď", "Đ", "Ð", "É",
	              "È", "Ė", "Ê", "Ë", "Ě", "Ē", "Ę", "Ə", "Ġ", "Ĝ", "Ğ", "Ģ", "á", "à", "â", "ä", "ă", "ā", "ã",
	              "å", "ą", "æ", "ć", "ċ", "ĉ", "č", "ç", "ď", "đ", "ð", "é", "è", "ė", "ê", "ë", "ě", "ē", "ę",
	              "ə", "ġ", "ĝ", "ğ", "ģ", "Ĥ", "Ħ", "I", "Í", "Ì", "İ", "Î", "Ï", "Ī", "Į", "Ĳ", "Ĵ", "Ķ", "Ļ",
	              "Ł", "Ń", "Ň", "Ñ", "Ņ", "Ó", "Ò", "Ô", "Ö", "Õ", "Ő", "Ø", "Ơ", "Œ", "ĥ", "ħ", "ı", "í", "ì",
	              "i", "î", "ï", "ī", "į", "ĳ", "ĵ", "ķ", "ļ", "ł", "ń", "ň", "ñ", "ņ", "ó", "ò", "ô", "ö", "õ",
	              "ő", "ø", "ơ", "œ", "Ŕ", "Ř", "Ś", "Ŝ", "Š", "Ş", "Ť", "Ţ", "Þ", "Ú", "Ù", "Û", "Ü", "Ŭ", "Ū",
	              "Ů", "Ų", "Ű", "Ư", "Ŵ", "Ý", "Ŷ", "Ÿ", "Ź", "Ż", "Ž", "ŕ", "ř", "ś", "ŝ", "š", "ş", "ß", "ť",
	              "ţ", "þ", "ú", "ù", "û", "ü", "ŭ", "ū", "ů", "ų", "ű", "ư", "ŵ", "ý", "ŷ", "ÿ", "ź", "ż", "ž");
	$to   = array("A", "A", "A", "A", "A", "A", "A", "A", "A", "AE", "C", "C", "C", "C", "C", "D", "D", "D", "E",
	              "E", "E", "E", "E", "E", "E", "E", "G", "G", "G", "G", "G", "a", "a", "a", "a", "a", "a", "a",
	              "a", "a", "ae", "c", "c", "c", "c", "c", "d", "d", "d", "e", "e", "e", "e", "e", "e", "e", "e",
	              "g", "g", "g", "g", "g", "H", "H", "I", "I", "I", "I", "I", "I", "I", "I", "IJ", "J", "K", "L",
	              "L", "N", "N", "N", "N", "O", "O", "O", "O", "O", "O", "O", "O", "CE", "h", "h", "i", "i", "i",
	              "i", "i", "i", "i", "i", "ij", "j", "k", "l", "l", "n", "n", "n", "n", "o", "o", "o", "o", "o",
	              "o", "o", "o", "o", "R", "R", "S", "S", "S", "S", "T", "T", "T", "U", "U", "U", "U", "U", "U",
	              "U", "U", "U", "U", "W", "Y", "Y", "Y", "Z", "Z", "Z", "r", "r", "s", "s", "s", "s", "B", "t",
	              "t", "b", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "w", "y", "y", "y", "z", "z", "z");
	$from = array_merge($from, $cyrylicFrom);
	$to   = array_merge($to, $cyrylicTo);
	$slug = str_replace($from, $to, $title);
	$slug = strtolower($slug);
	$slug = preg_replace('/[^a-z0-9_\- ]/', '', $slug);
	$slug = str_replace(' ', '-', $slug);
	$slug = preg_replace('/(\-\-+)/', '-', $slug);
	$slug = trim($slug, '_.-');
	if(empty($slug)) {
		$slug = '_';
	}
	return substr($slug, 0, $maxSize);
}

function ac_slug_available($slug, $taken)
{
	$num   = 0;
	$pattern = '/' . preg_quote($slug, '/') . '-[0-9]+/i';
	foreach($taken as $test) {
		if($test === $slug && $num === 0) {
			$num = 2;
		} else if(preg_match($pattern, $test, $matches)) {
			$n = intval($matches[1]) + 1;
			if($num < $n) {
				$num = $n;
			}
		}
	}
	if($num) {
		$slug .= "-$num";
	}

	return $slug;
}

function ac_mysql_connection(array $options)
{
	$options += array(
		'host'             => '127.0.0.1',
		'port'             => 3306,
		'socket'           => null,
		'timezone'         => null,
		'charset'          => null,
		'database'         => null,
		'username'         => 'root',
		'password'         => null,
		'options'          => array()
	);
	$dsn = "mysql:host={$options['host']}";
	if($options['port']) { $dsn .= ";port={$options['port']}"; }
	if($options['socket']) { $dsn .= ";unix_socket={$options['port']}"; }
	if($options['database']) { $dsn .= ";dbname={$options['database']}"; }
	if($options['charset']) { $dsn .= ";charset={$options['charset']}"; }
	$driverOptions = $options['options'] + array(
		PDO::ATTR_ERRMODE			 => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES   => true
	);
	$dbh = new PDO($dsn, $options['username'], $options['password'], $driverOptions);
	if($options['timezone']) {
		$sth = $dbh->prepare("SET time_zone=:tz");
		$sth->bindValue(':tz', $options['timezone'], PDO::PARAM_STR);
		$sth->execute();
	}
	return $dbh;

}

/**
 * Delete a directory and it's contents recursively
 *
 * @param string $dir
 * @param bool   $deleteSelf
 */
function ac_delete_dir($dir, $deleteSelf = false)
{
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
		$path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
	}
	if($deleteSelf) {
		rmdir($dir);
	}
}

/**
 * Checks whether a file have been successfully uploaded
 *
 * @param string       $key The $_FILES key
 * @param bool         $multiple Whether multiple files are expected
 * @param int|null     $error The error ID, if any
 * @param string|null  $errorStr The error message, if any
 * @param int|null     $index For multiple file uploads, the index number of the file with errors
 * @return bool
 */
function ac_file_uploaded($key, $multiple = false, &$error = null, &$errorStr = null, &$index = null)
{
	$error = $errorStr = null;
	do {
		if(!isset($_FILES[$key])
		|| !isset($_FILES[$key]['name'])
		|| !isset($_FILES[$key]['tmp_name'])
		|| !isset($_FILES[$key]['type'])
		|| !isset($_FILES[$key]['size'])
		|| !isset($_FILES[$key]['error'])) {
			return false;
		}
		if($multiple) {
			foreach($_FILES[$key] as $x) {
				if(!is_array($x)) {
					return false;
				}
			}
			$count = count($_FILES[$key]['name']);
			for($index = 0; $index < $count; ++$index) {
				if($_FILES[$key]['error'][$index]) {
					$error = (int)$_FILES[$key]['error'][$index];
					break;
				}
			}
			$index = null;
			return true;
		} else if(!is_array($_FILES[$key]['name']) &&
				  !is_array($_FILES[$key]['tmp_name']) &&
				  !is_array($_FILES[$key]['type']) &&
				  !is_array($_FILES[$key]['size']) &&
				   $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE) {
			if($_FILES[$key]['error']) {
				$error = (int)$_FILES[$key]['error'];
				break;
			} else {
				return true;
			}
		}
		return false;
	} while(0);
	switch($error) {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			$errorStr = __('upload', 'file-too-large');
			break;
		case UPLOAD_ERR_PARTIAL:
			$errorStr = __('upload', 'partially-uploaded');
			break;
		case UPLOAD_ERR_NO_TMP_DIR:
			$errorStr = __('upload', 'missing-tmp-folder');
			break;
		default:
			$errorStr = __('upload', 'upload-fail');
			break;
	}
	return false;
}

/**
 * Get metadata from a TTF font
 *
 * @param string $font Path to a TTF font file
 * @param null   $info
 * @return array|bool|null|string Returns false if the file is not of a valid TTF font
 */
function ac_font_info($font, $info = null)
{
	try {
		$fp = fopen($font, 'r');
		$major_version = current(unpack('n', fread($fp, 2)));
		$minor_version = current(unpack('n', fread($fp, 2)));
		$num_tables    = current(unpack('n', fread($fp, 2)));
		if($major_version !== 1 || $minor_version !== 0) {
			fclose($fp);
			return false;
		}
		fseek($fp, 12, SEEK_SET);
		do {
			for($i = 0; $i < $num_tables; ++$i) {
				$sz_tag = fread($fp, 4);
				if(strtolower($sz_tag) === 'name') {
					fseek($fp, 4, SEEK_CUR);
					$offset = current(unpack('N', fread($fp, 4)));
					break 2;
				} else {
					fseek($fp, 12, SEEK_CUR);
				}
			}
			fclose($fp);
			return false;
		} while(0);
		fseek($fp, $offset + 2, SEEK_SET);
		$nr_count       = current(unpack('n', fread($fp, 2)));
		$storage_offset = current(unpack('n', fread($fp, 2)));
		$data = array_fill(0, 7, null);
		for($i = 0; $i < $nr_count; ++$i) {
			$record = fread($fp, 12);
			$id = current(unpack('n', substr($record, 6, 2)));
			if($id > 7) {
				break;
			}
			$encoding   = current(unpack('n', substr($record, 2, 2)));
			$str_len    = current(unpack('n', substr($record, 8, 2)));
			$str_offset = current(unpack('n', substr($record, 10, 2)));
			if($str_len <= 0) {
				continue;
			}
			$pos = ftell($fp);
			fseek($fp, $offset + $str_offset + $storage_offset, SEEK_SET);
			$val = fread($fp, $str_len);
			if(!empty($val) && empty($data[$id])) {
				if($info !== null && $id === $info) {
					fclose($fp);
					return $val;
				}
				$data[$id] = $val;
			}
			fseek($fp, $pos, SEEK_SET);
		}
		fclose($fp);
		if($info) {
			return null;
		} else {
			return $data;
		}
	} catch(\Exception $exception) {
		\Aqua\Log\ErrorLog::logSql($exception);
		if(isset($fp) && is_resource($fp)) {
			fclose($fp);
		}
		return false;
	}
}

function ac_truncate_string($content, $max, $append = '')
{
	$out = '';
	$printed = 0;
	$pattern = '%</?([a-z]+)[^>]*/?>|&#?[a-zA-Z0-9]+;|[\x80-\xFF][\x80-\xBF]*%miS';
	$offset = 0;
	$tags = array();
	while($printed < $max && preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
		$tagLength = strlen($match[0][0]);
		$tagOffset = $match[0][1];
		$str = substr($content, $offset, $tagOffset - $offset);
		if(($printed + strlen($str)) > $max) {
			$str = substr($str, 0, $max - $printed);
		}
		$printed += strlen($str);
		if(substr($match[0][0], 0, 2) === '</') {
			$name = strtolower($match[1][0]);
			for($i = count($tags) - 1; $i >= 0; --$i) {
				if($name === $tags[$i]) {
					break;
				}
				$printed.= "</{$tags[$i]}>";
				array_pop($tags);
			}
		} else if($match[0][0][0] === '<' && isset($match[1]) &&
		          substr($match[0][0], -2) !== '/>') {
			$tags[] = strtolower($match[1][0]);
		}
		$out.= $str . $match[0][0];
		$offset = $tagOffset + $tagLength;
	}
	if($printed < $max) {
		$out.= substr($content, $offset, $max - $printed);
	}
	$out = substr($out, 0, strrpos($out, ' '));
	$inline = array(
		'b', 'big', 'i', 'small', 'tt', 'label',
		'abbr', 'acronym', 'cite', 'code', 'input',
		'dfn', 'em', 'kdb', 'strong', 'samp', 'select',
		'var', 'a', 'bdo', 'br', 'img', 'map', 'textarea',
		'object', 'span', 'sub', 'sup', 'button'
	);
	for($i = count($tags) - 1; $i >= 0; --$i) {
		$name = $tags[$i];
		if($append && !in_array($name, $inline, true)) {
			$out.= $append;
			$append = null;
		}
		$out.= "</$name>";
	}
	if($append) {
		$out.= $append;
	}

	return $out;
}

function ac_parse_content($content, &$pages, &$shortContent)
{
	if(preg_match('/<!-{2,} *readmore *-{2,}>/i', $content, $match, PREG_OFFSET_CAPTURE)) {
		$shortContent = substr($content, 0, $match[0][1]);
		$shortContent = ac_truncate_string($shortContent, strlen($shortContent));
	}
	$pages = preg_split('/<!-{2,} *nextpage *-{2,}>/', $content);
}

function ac_bitmask($x)
{
	if(is_array($x)) {
		$mask = 0;
		foreach($x as &$y) {
			if(is_int($y)) $mask |= (int)$y;
		}
		return $mask;
	} else if(is_int($x)) {
		return (int)$x;
	} else {
		return 0;
	}
}

function ac_between($x, $y)
{
	if(!ctype_digit($x)) $x = null;
	if(!ctype_digit($y)) $y = null;
	if($x && $y) {
		return array( \Aqua\SQL\Search::SEARCH_BETWEEN, $x, $y );
	} else if($x) {
		return array( \Aqua\SQL\Search::SEARCH_HIGHER, $x );
	} else if($y) {
		return array( \Aqua\SQL\Search::SEARCH_LOWER, $x );
	} else {
		return null;
	}
}

/**
 * Convert storage sizes
 *
 * @param string $size A number followed by the unit, e.g.: "10MB"
 * @param string $convert Size to be converted to. "B", "KB", "MB", "GB", "TB" or "PB"
 * @return bool|int Returns false on failure
 */
function ac_size($size, $convert = 'B')
{
	if(is_int($size) || ctype_digit($size)) {
		$size = intval($size);
		$unit = 'B';
	} else if(strcasecmp(substr($size, -1), 'B') === 0 &&
	          ctype_digit(substr($size, 0, -1))) {
		$unit = 'B';
		$size = intval(substr($size, 0, -1));
	} else {
		$unit = strtoupper(substr($size, -2));
		$size = intval(substr($size, 0, -2));
	}
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
	$convert = array_search($convert, $units);
	$unit = array_search($unit, $units);
	if($unit === false || $convert === false) {
		return false;
	} else {
		return ($size * pow(1024, ($unit - $convert)));
	}
}

/**
 * Returns the PCRE error message corresponding to the given error ID
 *
 * @param int|null $errorId The error ID, the last error ID is used in case this is null
 * @return null|string
 */
function ac_pcre_error_str($errorId = null)
{
	if($errorId === null) {
		$errorId = preg_last_error();
	}
	switch($errorId) {
		default:
			return null;
		case PREG_INTERNAL_ERROR:
			return __('exception', 'internal-pcre-error');
		case PREG_BACKTRACK_LIMIT_ERROR:
			return __('exception', 'pcre-backtrack-limit');
		case PREG_RECURSION_LIMIT_ERROR:
			return __('exception', 'pcre-recursion-limit');
		case PREG_BAD_UTF8_ERROR:
			return __('exception', 'pcre-bad-utf8');
		case PREG_BAD_UTF8_OFFSET_ERROR:
			return __('exception', 'pcre-bad-utf8-offset');
	}
}

function ac_parse_upgrade_file_name($file, &$version, &$number = null, &$type = null)
{
	if(!preg_match('/^(\d+\.\d+\.\d+)(?:-([\d\w]+))?(?:\.([^\.]+))?\.\w+/', basename($file), $match)) {
		return false;
	}
	$version = isset($match[1]) ? $match[1] : null;
	$number  = isset($match[2]) ? $match[2] : null;
	$type    = isset($match[3]) ? $match[3] : null;
	return true;
}

function ac_normalize_hex_color($color)
{
	if(!preg_match('/#?([a-f0-9]{3,6})/i', $color, $match)) {
		return false;
	}
	$hex = $match[1];
	switch(strlen($hex)) {
		case 3:
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			break;
		case 4:
			$hex .= $hex[2] . $hex[3];
			break;
		case 5:
			$hex .= '0';
			break;
	}
	return $hex;
}

function ac_time_elapsed($timestamp)
{
	$timeElapsed = array();
	$now  = time();
	$diff = abs($now - $timestamp);
	if($timestamp === $now) {
		$timeElapsed[] = __('time-elapsed', 'none');
	} else {
		$intervals = array(
			1,      // second
			60,     // minute
			3600,   // hour
			86400,  // day
			2073600 // month
		);
		$intervalNames = array( 'second', 'minute', 'hour', 'day', 'month' );
		$format = array(
			array( 0 ),
		    array( 2, 1 ),
		    array( 3 ),
		);
		foreach($format as $time) {
			$match = 0;
			foreach($time as $key) {
				$count = floor($diff / $intervals[$key]);
				if($count && $count < ($intervals[$key + 1] / $intervals[$key])) {
					$timeElapsed[] = __('time-elapsed',
					                    $intervalNames[$key] . (intval($count) === 1 ? '' : 's'),
					                    $count);
					$diff -= $count * $intervals[$key];
					++$match;
				}
			}
			if($match) {
				break;
			}
		}
		if(empty($timeElapsed)) {
			$now  = new DateTime();
			$then = clone $now;
			$then->setTimestamp($timestamp);
			$diff = $now->diff($then);
			if($diff->y) {
				$timeElapsed[] = __('time-elapsed',
				                    'year' . (intval($diff->y) === 1 ? '' : 's'),
				                    $diff->y);
			}
			if($diff->m) {
				$timeElapsed[] = __('time-elapsed',
				                    'month' . (intval($diff->m) === 1 ? '' : 's'),
				                    $diff->m);
			}
		}
	}
	if(count($timeElapsed) === 1) {
		return $timeElapsed[0];
	} else {
		$last = array_pop($timeElapsed);
		$timeElapsed = implode(', ', $timeElapsed);
		$timeElapsed.= ' ' . __('application', 'and') . ' ' . $last;
		return $timeElapsed;
	}
}

function ac_define_constants()
{
	if(!defined('Aqua\DOMAIN')) {
		define('Aqua\DOMAIN', getenv('HTTP_HOST'));
	}
	if(!defined('Aqua\HTTPS')) {
		define('Aqua\HTTPS', ($https = getenv('HTTPS')) && ($https === 'on' || $https === '1'));
	}
	if(!defined('Aqua\DIR')) {
		define('Aqua\DIR', trim(substr(\Aqua\ROOT, strlen(getenv('DOCUMENT_ROOT'))), '/\\'));
	}
	if(!defined('Aqua\WORKING_DIR')) {
		define('Aqua\WORKING_DIR', trim(substr(dirname(getenv('SCRIPT_FILENAME')),
		                                       strlen(\Aqua\ROOT)),
		                                '/\\'));
	}
	if(!defined('Aqua\WORKING_URL')) {
		if(($path = getenv('PHP_SELF')) !== false) {
			$path = dirname($path);
		} else {
			$path = parse_url(getenv('REQUEST_URI'), PHP_URL_PATH);
			if(substr($path, -4) === '.php') {
				$path = dirname($path);
			}
		}
		$path = rtrim($path, '/');
		define('Aqua\WORKING_URL', 'http' . (\Aqua\HTTPS ? 's' : '') . '://' . getenv('HTTP_HOST') . $path);
	}
	if(!defined('Aqua\URL')) {
		define('Aqua\URL',
			'http' . (\Aqua\HTTPS ? 's' : '') . '://' .
			$_SERVER['HTTP_HOST'] .
			rtrim(dirname(dirname(getenv('PHP_SELF'))), '/'));
	}
}

function ac_files($key)
{
	if(!array_key_exists($key, $_FILES)) {
		return array();
	}
	$files = array();
	$keys  = array_keys($_FILES[$key]);
	if(!is_array($_FILES[$key][current($keys)])) {
		if($_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
			return array();
		} else {
			return array( $_FILES[$key] );
		}
	}
	$count = count($_FILES[$key][current($keys)]);
	for($i = 0; $i < $count; $i++) {
		if($_FILES[$key]['error'][$i] === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		foreach($keys as $k) {
			$files[$i][$k] = $_FILES[$key][$k][$i];
		}
	}
	return $files;
}
