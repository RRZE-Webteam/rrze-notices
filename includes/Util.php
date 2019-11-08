<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Roles;

class Util 
{
    public static function getRoles($translated = true) 
    {
			$wpRoles = new WP_Roles(); 
			$roles = $wpRoles->get_names();
			if (true === $translated) {
                $roles = array_map('translate_user_role', $roles);
            }
			return $roles;
	}

	public static function getNoticeTypes() {
		return [
			'info' 	  => __('Info', 'rrze-notices'),
			'warning' => __('Warning', 'rrze-notices')
		];
	}

	public static function arrayValInArray($needle, $haystack) 
	{
		foreach ($needle as $stack) {
				if (in_array($stack, $haystack)) {
					return true;
				}
		}
		return false;
	}	
}