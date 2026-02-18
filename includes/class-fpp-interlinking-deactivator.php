<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Deactivator {

	public static function deactivate() {
		delete_transient( 'fpp_interlinking_keywords_cache' );
	}
}
