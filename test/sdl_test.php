<?php

error_reporting( E_ALL | E_STRICT );
ini_set("display_errors", true);

dl("sdl.".PHP_SHLIB_SUFFIX);

if (!extension_loaded("sdl")) {
	fprintf( STDERR, "ERROR: The SDL extension is not loaded." . PHP_EOL);
	exit(1);
}

function waitForInput() {
	$event = null;
	while (SDL_WaitEvent($event)) {
		if (in_array( $event["type", array(SDL_MOUSEBUTTONDOWN, SDL_KEYDOWN))) {
			break;
		}
	}
}
