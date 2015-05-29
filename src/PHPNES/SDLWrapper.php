<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES;

class SDLWrapper {
	public $NES;

	public function __construct(&$NES) {
		$this->NES = $NES;
	}

	public function showErrorMessage($title, $message) {
		SDL_ShowSimpleMessageBox(
			SDL_MESSAGEBOX_ERROR,
			$title,
			$message
		);
	}
}
