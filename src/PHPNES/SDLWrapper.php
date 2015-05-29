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
	public $Window;
	public $Renderer;

	const width = 640;
	const height = 480;

	public function __construct(&$NES) {
		$this->NES = $NES;
		$this->init();
	}

	public function init() {
		$this->Window = SDL_CreateWindow(
			"PHPNES",
			SDL_WINDOWPOS_UNDEFINED,
			SDL_WINDOWPOS_UNDEFINED,
			SDLWrapper::width,
			SDLWrapper::height,
			SDL_WINDOW_ALLOW_HIGHDPI
		);

		$this->Renderer = SDL_CreateRenderer($this->Window, -1, 0);
	}

	public function reset() {
	}

	public function showErrorMessage($title, $message) {
		SDL_ShowSimpleMessageBox(
			SDL_MESSAGEBOX_ERROR,
			$title,
			$message
		);
	}
}
