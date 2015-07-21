<?php

Requirements::set_backend(new Minify_Requirements_Backend());
		
if(defined('MINIFY_CACHE_BACKEND') && defined('MINIFY_CACHE_LIFETIME')){
	
	$backend = unserialize(MINIFY_CACHE_BACKEND);
	SS_Cache::add_backend('MINIFY_CACHE_BACKEND', $backend['Type'], $backend['Options']);
	SS_Cache::set_cache_lifetime('MINIFY_CACHE_BACKEND', MINIFY_CACHE_LIFETIME, 100);
	SS_Cache::pick_backend('MINIFY_CACHE_BACKEND', 'MINIFY_CACHE', 100);
}