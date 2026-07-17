<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

class AdminPagesRegistry {
	private static $pages = array();

	public static function bulk_add( $pages ) {
		self::$pages = array_merge( self::$pages, $pages );
	}

	public static function set( $key, $service ) {
		self::$pages[ $key ] = $service;
	}

	public static function get( $key ): PageAbstract {
		return self::$pages[ $key ];
	}
}
