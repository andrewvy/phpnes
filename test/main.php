<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

require "vendor/autoload.php";

use PHPNES\NES;

$nes = new NES(true);
$nes->debugMode = true;

/*
The rom being used for testing is an iNES executable
of Concentration Room 0.01:

http://pineight.com/croom/
Copyright © 2010 Damian Yerrick <croom@pineight.com>

Check roms/croom/croom.b64.license for details.
*/

$romData = file_get_contents("roms/nestest.nes");

$nes->loadRom($romData);
$nes->start();
