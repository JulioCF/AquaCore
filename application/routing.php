<?php
use Aqua\Core\App;
use Aqua\Http\Request;
use Aqua\Ragnarok\Server\CharMap;
use Aqua\Ragnarok\Server\Login;
use Aqua\Ragnarok\Server;
use Aqua\Router\Router;

$router = new Router;

function ac_router_set_active_server($key)
{
	return (App::$activeServer = Server::get($key));
}
function ac_router_set_active_charmap($key)
{
	if(!App::$activeServer) {
		return false;
	}
	if($key === null || (strcasecmp($key, 'server') === 0 && !isset(App::$activeServer->charmap['server']))) {
		return (App::$activeCharMapServer = App::$activeServer->charmap());
	} else {
		return (App::$activeCharMapServer = App::$activeServer->charmap($key));
	}
}
function ac_router_set_active_account($key)
{
	if(!App::$activeServer) {
		return false;
	}
	if(App::settings()->get('ragnarok')->get('acc_username_url', false)) {
		App::$activeRagnarokAccount = App::$activeServer->login->get($key, 'username');
	} else {
		App::$activeRagnarokAccount = App::$activeServer->login->get($key, 'id');
	}
	return (bool)App::$activeRagnarokAccount;
}
function ac_router_set_active_char($key) {
	if(!App::$activeCharMapServer) {
		return false;
	}
	if(App::settings()->get('ragnarok')->get('char_name_url', false)) {
		App::$activeRagnarokCharacter = App::$activeCharMapServer->character($key, 'name');
	} else {
		App::$activeRagnarokCharacter = App::$activeCharMapServer->character($key, 'id');
	}
	return (bool)App::$activeRagnarokCharacter;
}

$router->add('Main Site')
	->map('/*', '/main/:path');
$router->add('Pages')
	->map("/page/:slug/", '/main/page/action/index/:slug');
$router->add('News')
	->map("/news/:slug/", '/main/news/action/view/:slug');
$router->add('News Tags')
	->map("/news/tagged/:tag/", '/main/news/action/tagged/:tag');
$router->add('News Categories')
	->map("/news/category/:category/", '/main/news/action/category/:category');
$_404 = 'ragnarok/account|ragnarok/server/char';
if(Server::$serverCount > 1) {
	$servers = implode('|', array_keys(Server::$servers));
	// /ro/<server>
	$router->add('Ragnarok Server Name')
		->map("/ro/:server[$servers]/*", '/main/ragnarok/:path')
		->attach('parse_ok', function($e, $match) {
			ac_router_set_active_server($match['server']);
		});
	// /ro/<server>/a/<account>/*
	$router->add('Ragnarok Server Name - Account')
		->map("/ro/:server[$servers]/a/:username/*", '/main/ragnarok/account/:path')
		->attach('parse_ok', function($e, $match) {
			ac_router_set_active_server($match['server']);
			ac_router_set_active_account($match['username']);
		});
	foreach(Server::$servers as $server) {
		if($server->charmapCount === 1) {
			// /ro/<server>/server/*
			$router->add('Ragnarok Server (' . $server->key . ') - CharMap')
			       ->map("/ro/:server[{$server->key}]/server/*", '/main/ragnarok/server/:path')
			       ->attach('parse_ok', function($e, $match) {
				       ac_router_set_active_server($match['server']);
				       ac_router_set_active_charmap(null);
			       });
			// /ro/<server>/server/c/<char>/*
			$router->add('Ragnarok Server (' . $server->key . ') - Char')
			       ->map("/ro/:server[{$server->key}]/server/c/:char/*", '/main/ragnarok/server/char/:path')
			       ->attach('parse_ok', function($e, $match) {
				       ac_router_set_active_server($match['server']);
				       ac_router_set_active_charmap(null);
				       ac_router_set_active_char($match['char']);
			       });
		} else if($server->charmapCount) {
			$charmap = implode('|', array_keys($server->charmap)) . '|server';
			// /ro/<server>/s/<charmap>/*
			$router->add('Ragnarok Server (' . $server->key . ') - CharMap')
				->map("/ro/:server[{$server->key}]/s/:charmap[$charmap]/*", '/main/ragnarok/server/:path')
				->attach('parse_ok', function($e, $match) {
					ac_router_set_active_server($match['server']);
					ac_router_set_active_charmap($match['charmap']);
				});
			// /ro/<server>/s/<charmap>/c/<char>/*
			$router->add('Ragnarok Server (' . $server->key . ') - Char')
			       ->map("/ro/:server[{$server->key}]/s/:charmap[$charmap]/c/:char/*", '/main/ragnarok/server/char/:path')
			       ->attach('parse_ok', function($e, $match) {
				       ac_router_set_active_server($match['server']);
				       ac_router_set_active_charmap($match['charmap']);
				       ac_router_set_active_char($match['char']);
			       });
		}
	}
	reset(Server::$servers);
// Single server
} else if(Server::$serverCount === 1) {
	App::$activeServer = current(Server::$servers);
	// /ragnarok/a/<account>/*
	$router->add('Ragnarok - Account')
		->map("/ragnarok/a/:username/*", '/main/ragnarok/account/:path')
		->attach('parse_ok', function($e, $match) {
			ac_router_set_active_account($match['username']);
		});
	// /ragnarok/c/<charmap>/*
	if(App::$activeServer->charmapCount > 1) {
		$charmap = implode('|', array_keys(App::$activeServer->charmap));
		$router->add('Ragnarok - CharMap')
			->map("/ragnarok/s/:charmap[$charmap]/*", '/main/ragnarok/server/:path')
			->attach('parse_ok', function($e, $match) {
				ac_router_set_active_charmap($match['charmap']);
			});
		$router->add('Ragnarok - Char')
			->map("/ragnarok/s/:charmap[$charmap]/c/:char/*", '/main/ragnarok/server/char/:path')
			->attach('parse_ok', function($e, $match) {
				ac_router_set_active_charmap($match['charmap']);
				ac_router_set_active_char($match['char']);
			});
	} else if(App::$activeServer->charmapCount) {
		App::$activeCharMapServer = current(App::$activeServer->charmap);
		$router->add('Ragnarok - Char')
			->map("/ragnarok/server/c/:char/*", '/main/ragnarok/server/char/:path')
			->attach('parse_ok', function($e, $match) {
				ac_router_set_active_char($match['char']);
			});
	}
} else {
	$_404 .= '|ragnarok/server';
}

$router->add('404')
	->map('/:n[' . $_404 . ']/*', '404');

return $router;
