<?php

error_reporting( E_ALL | E_STRICT );
ini_set("display_errors", true);

if (!extension_loaded("sdl")) {
	fprintf( STDERR, "ERROR: The SDL extension is not loaded." . PHP_EOL);
	exit(1);
}

SDL_ShowSimpleMessageBox(
	SDL_MESSAGEBOX_INFORMATION,
	"Title",
	"Message"
);
