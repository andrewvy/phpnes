<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license MIT
 */

require "../vendor/autoload.php";

use PHPNES\NES;

$nes = new NES();

/*
The rom being used for testing is an iNES executable
of Concentration Room 0.01:

http://pineight.com/croom/
Copyright © 2010 Damian Yerrick <croom@pineight.com>

Check roms/croom/croom.b64.license for details.
*/

$romData = file_get_contents("../roms/croom/croom.nes");

$nes->loadRom($romData);
