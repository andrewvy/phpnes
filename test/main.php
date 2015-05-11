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

/*
This rom is an iNES executable of Concentration Room 0.01 in base 64:
http://pineight.com/croom/
Copyright © 2010 Damian Yerrick <croom@pineight.com>

Check croom.rom.license for details.
*/

$romData64 = file_get_contents("croom.rom");
$romData = base64_decode($romData64);

$nes->loadRom($romData);
