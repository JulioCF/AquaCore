<?php
use Aqua\Core\App;
use Aqua\Ragnarok\Server;
use Aqua\UI\Menu;

$menu = new Menu;
$menu->append('home', array(
		'title' => __('menu', 'home'),
		'url' => \Aqua\URL
	));
if(App::user()->loggedIn()) {
	$menu->append('account', array(
			'title' => __('menu', 'my-account'),
			'url' => ac_build_url(array( 'path' => array( 'account' ) ))
		));
} else {
	$menu->append('register', array(
			'title' => __('menu', 'register'),
			'url' => ac_build_url(array( 'path' => array( 'account' ), 'action' => 'register' ))
		));
}
if(App::settings()->get('donation')->get('enable')) {
	$menu->append('donation', array(
		'title' => __('menu', 'donate'),
		'url' => ac_build_url(array( 'path' => array( 'donate' ) ))
	));
}
$menu->append('news', array(
		'title' => __('menu', 'news'),
		'url' => ac_build_url(array( 'path' => array( 'news' ) ))
	));
if(Server::$serverCount) {
	$servers = array();
	foreach(Server::$servers as $server) {
		if(!$server->charmapCount) continue;
		$serverMenu = array(
			'title' => htmlspecialchars($server->name),
			'url' => $server->url(),
		);
		$charmaps = array();
		foreach($server->charmap as $charmap) {
			$charmaps[] = array(
				'title' => htmlspecialchars($charmap->name),
				'url' => $charmap->url(),
				'submenu' => array(
					array(
						'title' => __('menu', 'whos-online'),
						'url' => $charmap->url(array( 'action' => 'online' )),
					),
					array(
						'title' => __('menu', 'mob-db'),
						'url' => $charmap->url(array( 'path' => array( 'mob' ) )),
					),
					array(
						'title' => __('menu', 'item-db'),
						'url' => $charmap->url(array( 'path' => array( 'item' ) )),
					),
					array(
						'title' => __('menu', 'item-shop'),
						'url' => $charmap->url(array( 'path' => array( 'item' ), 'action' => 'shop' )),
					),
					array(
						'title' => __('menu', 'rankings'),
						'url' => $charmap->url(array( 'path' => array( 'ladder' ) )),
					),
				)
			);
			if($server->charmapCount === 1) {
				$serverMenu['submenu'] = $charmaps[0]['submenu'];
			} else {
				$serverMenu['submenu'] = $charmaps;
			}
			$menu->append('ragnarok-' . $server->key, $serverMenu);
		}
	}
	reset(Server::$servers);
}
echo $menu->render();