<?php

use Roots\WPConfig\Config;

Config::define( 'WP_DEBUG', true );
Config::define( 'WP_DEBUG_DISPLAY', true );
Config::define( 'WP_DEBUG_LOG', true );
Config::define( 'SCRIPT_DEBUG', true );
Config::define( 'DISALLOW_FILE_EDIT', false );
Config::define( 'AUTOMATIC_UPDATER_DISABLED', true );

ini_set( 'display_errors', '1' );
