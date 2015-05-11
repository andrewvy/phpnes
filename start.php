<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license MIT
 */

require "vendor/autoload.php";

use PHPNES\NES;

$nes = new NES();

$nes->loadRom("");
