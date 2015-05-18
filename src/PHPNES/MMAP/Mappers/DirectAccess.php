<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\MMAP\Mappers;

use PHPNES\MMAP\Mapper;
use PHPNES\CPU;
use PHPNES\Helpers\ArrayHelpers;

class DirectAccess extends Mapper {
	public $joy1StrobeState = 0;
	public $joy2StrobeState = 0;
	public $joypadLastWrite = 0;

	public $mousePressed = false;
	public $mouseX = null;
	public $mouseY = null;

	public function __construct($NES) {
		parent::__construct($NES);
	}

	public function write($address, $value) {
		if ($address < 0x2000) {
			$this->NES->CPU->mem[$address & 0x7FF] = $value;
		} else if ($address > 0x4017) {
			$this->NES->CPU->mem[$address] = $value;
			if ($address >= 0x6000 && $address < 0x8000) {
				// Write to battery ram
			}
		} else if ($address > 0x2007 && $address < 0x4000) {
			$this->regWrite(0x2000 + ($address & 0x7), $value);
		} else {
			$this->regWrite($address, $value);
		}
	}

	public function writelow($address, $value) {
		if ($address < 0x2000) {
			$this->NES->CPU->mem[$address & 0x7FF] = $value;
		} else if ($address > 0x4017) {
			$this->NES->CPU->mem[$address] = $value;
		} else if ($address > 0x2007 && $address < 0x4000) {
			$this->regWrite(0x2000 + ($address & 0x7), $value);
		} else {
			$this->regWrite($address, $value);
		}
	}

	public function load($address) {
		$address &= 0xFFFF;

		print "LOADING FROM ADDR: ".$address.PHP_EOL;

		if ($address > 0x4017) {
			print "LOADING FROM CPU MEM\n";
			return $this->NES->CPU->mem[$address];
		} else if ($address >= 0x2000) {
			return $this->regLoad($address);
		} else {
			return $this->NES->CPU->mem[$address & 0x7FF];
		}
	}

	public function regLoad($address) {
		switch ($address >> 12) {
			case 0:
				break;
			case 1:
				break;
			case 2:
			case 3:
				switch ($address & 0x7) {
					case 0x0:
						return $this->NES->CPU->mem[0x2000];
					case 0x1:
						return $this->NES->CPU->mem[0x2001];
					case 0x2:
						return $this->NES->PPU->readStatusRegister();
					case 0x3:
						return 0;
					case 0x4:
						return $this->NES->PPU->sramLoad();
					case 0x5:
						return 0;
					case 0x6:
						return 0;
					case 0x7:
						return $this->NES->PPU->vramLoad();
				}
				break;
			case 4:
				switch ($address - 0x4015) {
					case 0:
						return $this->NES->PAPU->readReg($address);
					case 1:
						return $this->joy1Read();
					case 2:
						if ($this->mousePressed) {
							$sx = max(0, $this->mosueX - 4);
							$ex = min(256, $this->mouseX + 4);
							$sy = max(0, $this->mouseY - 4);
							$ey = min(240, $this->mouseY + 4);
							$w = 0;

							for ($y = $sy; $y < $ey; $y++) {
								for ($x = $x; $x < $ex; $x++) {
									if ($this->NES->PPU->buffer[($y << 8) + $x] == 0xFFFFFF) {
										$w |= 0x1 << 3;
										break;
									}
								}
							}

							$w |= ($this->mousePressed ? (0x1 << 4) : 0);
							return ($this->joy2Read() | $w) & 0xFFFF;
						} else {
							return $this->joy2Read();
						}
				}
				break;
		}

		return 0;
	}

	public function regWrite($address, $value) {
		switch ($address) {
			case 0x2000:
				$this->NES->CPU->mem[$address] = $value;
				$this->NES->PPU->updateControlReg1($value);
				break;
			case 0x2001:
				$this->NES->CPU->mem[$address] = $value;
				$this->NES->PPU->updateControlReg2($value);
				break;
			case 0x2003:
				$this->NES->PPU->writeSRAMAddress($value);
				break;
			case 0x2004:
				$this->NES->PPU->sramWrite($value);
				break;
			case 0x2005:
				$this->NES->PPU->scrollWrite($value);
				break;
			case 0x2006:
				$this->NES->PPU->writeVRAMAdress($value);
				break;
			case 0x2007:
				$this->NES->PPU->vramWrie($value);
				break;
			case 0x4014:
				$this->NES->PPU->sramDMA($value);
				break;
			case 0x4015:
				$this->NES->PAPU->writeReg($address, $value);
				break;
			case 0x4016:
				if (($value & 1) === 0 && ($this->joypadLastWrite & 1) === 1) {
					$this->joy1StrobeState = 0;
					$this->joy2StrobeState = 0;
				}

				$this->joypadLastWrite = $value;
				break;
			case 0x4017:
				$this->NES->PAPU->writeReg($address, $value);
				break;
			default:
				if ($address >= 0x4000 && $address <= 0x4017) {
					$this->NES->PAPU->writeReg($address, $value);
				}
		}
	}

	public function joy1Read() {
		$ret = 0;

		switch ($this->joy1StrobeState) {
			case 0:
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 6:
			case 7:
				$ret = $this->NES->keyboard->state1[$this->joy1StrobeState];
				break;
			case 8:
			case 9:
			case 10:
			case 11:
			case 12:
			case 13:
			case 14:
			case 15:
			case 16:
			case 17:
			case 18:
				$ret = 0;
				break;
			case 19:
				$ret = 1;
				break;
			default:
				$ret = 0;
		}

		$this->joy1StrobeState++;
		if ($this->joy1StrobeState == 24) {
			$this->joy1StrobeState = 0;
		}

		return $ret;
	}

	public function joy2Read() {
		$ret = 0;

		switch ($this->joy12trobeState) {
			case 0:
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 6:
			case 7:
				$ret = $this->NES->keyboard->state2[$this->joy2StrobeState];
				break;
			case 8:
			case 9:
			case 10:
			case 11:
			case 12:
			case 13:
			case 14:
			case 15:
			case 16:
			case 17:
			case 18:
				$ret = 0;
				break;
			case 19:
				$ret = 1;
				break;
			default:
				$ret = 0;
		}

		$this->joy2StrobeState++;
		if ($this->joy2StrobeState == 24) {
			$this->joy2StrobeState = 0;
		}

		return $ret;
	}
	public function loadRom() {
		if (!$this->NES->rom->isValid || $this->NES->rom->romCount < 1) {
			return;
		}

		$this->loadPRGROM();
		$this->loadCHRROM();
		$this->loadBatteryRam();

		$this->NES->CPU->requestIrq(CPU::IRQ_RESET);
	}

	public function loadPRGROM() {
		if ($this->NES->rom->romCount > 1) {
			$this->loadRomBank(0, 0x8000);
			$this->loadRomBank(1, 0xC000);
		} else {
			$this->loadRomBank(0, 0x8000);
			$this->loadRomBank(0, 0xC000);
		}
	}

	public function loadCHRROM() {
		if ($this->NES->rom->vromCount > 0) {
			if ($this->NES->rom->vromCount == 1) {
				$this->loadVromBank(0, 0x0000);
				$this->loadVromBank(0, 0x1000);
			} else {
				$this->loadVromBank(0, 0x0000);
				$this->loadVromBank(1, 0x1000);
			}
		}
	}

	public function loadBatteryRam() {
		if ($this->NES->rom->batteryRam) {
			$ram = $this->NES->rom->batteryRam;
			if ($ram !== null && count($ram) == 0x2000) {
				ArrayHelpers::copyArrayElements($ram, 0, $this->NES->CPU->mem, 0x6000, 0x2000);
			}
		}
	}

	public function loadRomBank($bank, $address) {
		$bank %= $this->NES->rom->romCount;
		ArrayHelpers::copyArrayElements($this->NES->rom->rom[$bank], 0, $this->NES->CPU->mem, 0x6000, 0x2000);
	}

	public function loadVromBank($bank, $address) {
		if ($this->NES->rom->vromCount === 0) {
			return;
		}

		$this->NES->PPU->triggerRendering();
		ArrayHelpers::copyArrayElements($this->NES->rom->vrom[$bank % $this->NES->rom->vromCount], 0,
			$this->NES->PPU->vramMem, $address, 4096);

		$vromTile = $this->NES->rom->vromTile[$bank % $this->NES->rom->vromCount];
		ArrayHelpers::copyArrayElements($vromTile, 0, $this->NES->PPU->ptTile, $address >> 4, 256);
	}

	public function load32kRomBank($bank, $address) {
		$this->loadRomBank(($bank * 2) % $this->NES->rom->romCount, $address);
		$this->loadRomBank(($bank * 2 + 1) % $this->NES->rom->romCount, $address + 16384);
	}

	public function load8kVromBank($bank4kStart, $address) {
		if ($this->NES->rom->vromCount === 0) {
			return;
		}

		$this->NES->PPU->triggerRendering();
		$this->loadVromBank(($bank4kStart) % $this->NES->rom->vromCount, $address);
		$this->loadVromBank(($bank4kStart + 1) % $this->NES->rom->vromCount, $address + 4096);
	}

	public function load1kVromBank($bank1k, $address) {
		if ($this->NES->rom->vromCount === 0) {
			return;
		}

		$this->NES->PPU->triggerRendering();

		$bank4k = floor($bank1k / 4) % $this->NES->rom->vromCount;
		$bankoffset = ($bank1k % 4) * 1024;

		ArrayHelpers::copyArrayElements($this->NES->rom->vrom[$bank4k], 0, $this->NES->PPU->vramMem, $bankoffset, 1024);

		$vromTile = $this->NES->rom->vromTile[$bank4k];
		$baseIndex = $address >> 4;

		for ($i = 0; $i < 64; $i++) {
			$this->NES->PPU->ptTile[$baseIndex + $i] = $vromTile[(($bank1k % 4) << 6) + $i];
		}
	}

	public function load2kVromBank($bank2k, $address) {
		if ($this->NES->rom->vromCount === 0) {
			return;
		}

		$this->NES->PPU->triggerRendering();

		$bank4k = floor($bank2k / 2) % $this->NES->rom->vromCount;
		$bankoffset = ($bank2k % 2) * 2048;

		ArrayHelpers::copyArrayElements($this->NES->rom->vrom[$bank4k], $bankoffset, $this->NES->PPU->vramMem, $address, 2048);

		$vromTile = $this->NES->rom->vromTile[$bank4k];
		$baseIndex = $address >> 4;

		for ($i = 0; $i < 128; $i++) {
			$this->NES->PPU->ptTile[$baseIndex + $i] = $vromTile[(($bank2k % 2) << 7) + $i];
		}
	}

	public function load8kRomBank($bank8k, $address) {
		$bank16k = floor($bank8k / 2) % $this->NES->rom->romCount;
		$offset = ($bank8k % 2) * 8192;

		ArrayHelpers::copyArrayElements($this->NES->rom->rom[$bank16k], $offset, $this->NES->CPU->mem, $address, 8192);
	}

	public function clockIrqCounter() {
	}

	public function latchAccess() {
	}
}
