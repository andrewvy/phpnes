<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES;

use PHPNES\CPU\OpData;

class CPU {
	const IRQ_NORMAL = 0;
	const IRQ_NMI = 1;
	const IRQ_RESET = 2;

	public $NES;
	public $OpData;

	public $mem = [];
	public $REG_ACC;
	public $REG_X;
	public $REG_Y;

	public $SP;
	public $PC;
	public $PC_NEW;

	public $REG_STATUS;

	public $F_CARRY;
	public $F_DECIMAL;
	public $F_INTERRUPT;
	public $F_INTERRUPT_NEW;
	public $F_SIGN;
	public $F_NOTUSED;
	public $F_NOTUSED_NEW;
	public $F_BRK;
	public $F_BRK_NEW;

	public $cyclesToHalt;
	public $crash;
	public $irqRequested;
	public $irqType;

	public function __construct($NES) {
		$this->NES = $NES;
	}

	public function reset() {
		$this->mem = array_fill(0, 0x10000, 0xFF);

		for ($p = 0; $p < 4; $p++) {
			$i = $p * 0x800;

			$this->mem[$i + 0x008] = 0xF7;
			$this->mem[$i + 0x009] = 0xEF;
			$this->mem[$i + 0x00A] = 0xDF;
			$this->mem[$i + 0x00F] = 0xBF;
		}

		for ($i = 0x2001; $i < 0x10000; $i++) {
			$this->mem[$i] = 0x00;
		}

		// Reset CPU Registers
		$this->REG_ACC = 0x00;
		$this->REG_X = 0x00;
		$this->REG_Y = 0x00;

		// Reset stack pointer and program counter
		$this->SP = 0xFD;
		$this->PC = 0x8000-1;
		$this->PC_NEW = 0x8000-1;

		// Reset status register
		$this->REG_STATUS = 0x34;

		// Set status
		$this->setStatus(0x34);

		// Set flags
		$this->F_CARRY = 0x00;
		$this->F_DECIMAL = 0x00;
		$this->F_INTERRUPT = 1;
		$this->F_INTERRUPT_NEW = 1;
		$this->F_OVERFLOW = 0x00;
		$this->F_SIGN = 0x00;
		$this->F_ZERO = 1;

		$this->F_NOTUSED = 1;
		$this->F_NOTUSED_NEW = 1;
		$this->F_BRK = 1;
		$this->F_BRK_NEW = 1;

		$opdata = new OpData();
		$this->opdata = $opdata->opdata;
		$this->cyclesToHalt = 0;

		// Reset crash bool
		$this->crash = false;

		// Interrupt notifications
		$this->irqRequested = false;
		$this->irqType = null;
	}

	public function emulate() {
		$temp = 0;
		$add = 0;

		if ($this->irqRequested) {
			$temp =
				($this->F_CARRY) |
				(($this->F_ZERO === 0 ? 1 : 0) << 1) |
				($this->F_INTERRUPT << 2) |
				($this->F_DECIMAL << 3) |
				($this->F_BRK << 4) |
				($this->F_NOTUSED << 5) |
				($this->F_OVERFLOW << 6) |
				($this->F_SIGN << 7);

			$this->PC_NEW = $this->PC;
			$this->F_INTERRUPT_NEW = $this->F_INTERRUPT;

			switch ($this->irqType) {
				case 0:
					if ($this->F_INTERRUPT != 0) {
						break;
					}

					$this->doIrq($temp);
					break;
				case 1:
					$this->doNonMaskableInterrupt($temp);
					break;
				case 2:
					$this->doResetInterrupt();
					break;
			}

			$this->PC = $this->PC_NEW;
			$this->F_INTERRUPT = $this->F_INTERRUPT_NEW;
			$this->F_BRK = $this->F_BRK_NEW;
			$this->irqRequested = false;
		}

		$opinf = $this->opdata[$this->NES->MMAP->load($this->PC + 1)];
		$cycleCount = ($opinf >> 24);
		$cycleAdd = 0;

		$addrMode = ($opinf >> 8) & 0xFF;
		$opaddr = $this->PC;
		$this->PC += (($opinf >> 16) & 0xFF);
		$addr = 0;

		switch ($addrMode) {
			case 0:
				$addr = $this->load($opaddr + 2);
				break;
			case 1:
				$addr = $this->load($opaddr + 2);
				if ($addr < 0x80) {
					$addr += $this->PC;
				} else {
					$addr += $this->PC - 256;
				}
				break;
			case 2:
				break;
			case 3:
				$addr = $this->load16bit($opaddr + 2);
				break;
			case 4:
				$addr = $this->REG_ACC;
				break;
			case 5:
				$addr = $this->PC;
				break;
			case 6:
				$addr = ($this->load($opaddr + 2) + $this->REG_X) & 0xFF;
				break;
			case 7:
				$addr = ($this->load($opaddr + 2) + $this->REG_Y) & 0xFF;
				break;
			case 8:
				$addr = $this->load16bit($opaddr + 2);
				if (($addr & 0xFF00) != (($addr + $this->REG_X) & 0xFF)) {
					$cycleAdd = 1;
				}

				$addr += $this->REG_X;
				break;
			case 9:
				$addr = $this->load16bit($opaddr + 2);
				if (($addr & 0xFF) != (($addr + $this->REG_Y) & 0xFF00)) {
					$cycleAdd = 1;
				}

				$addr += $this->REG_Y;
				break;
			case 10:
				$addr = $this->load($opaddr + 2);
				if (($addr & 0xFF00) != (($addr + $this->REG_X) & 0xFF00)) {
					$cycleAdd = 1;
				}

				$addr += $this->REG_X;
				$addr &= 0xFF;
				$addr = $this->load16bit($addr);
				break;
			case 11:
				$addr = $this->load16bit($this->load($opaddr + 2));
				if (($addr & 0xFF00) != (($addr + $this->REG_Y) & 0xFF00)) {
					$cycleAdd = 1;
				}
				$addr += $this->REG_Y;
				break;
			case 12:
				$addr = $this->load16bit($opaddr + 2);
				if ($addr < 0x1FFF) {
					$addr = $this->mem[$addr] + ($this->mem[($addr & 0xFF00) | ((($addr & 0xFF) + 1) & 0xFF)] << 8);
				} else {
					$addr = $this->NES->MMAP->load($addr) + ($this->NES->MMAP->load(($addr & 0xFF00) | ((($addr & 0xFF) + 1) & 0xFF)) << 8);
				}
				break;
		}

		$addr &= 0xFFFF;


		print "REG ACC: ".$this->REG_ACC
			." | REG_X: ".$this->REG_X
			." | REG_Y: ".$this->REG_Y
			." | REG_STATUS: ".$this->REG_STATUS
			." | SP: ".$this->SP
			." | OPCODE: ".($opinf & 0xFF)
			." | ADDR: ".(($this->opdata[$this->NES->MMAP->load($this->PC + 1)] << 4) & 0xFF)
			." | PC: ".$this->PC
			.PHP_EOL;


		switch ($opinf & 0xFF) {
			case 0:
				$temp = $this->REG_ACC + $this->load($addr) + $this->F_CARRY;
				$this->F_OVERFLOW = ((!((($this->REG_ACC ^ $this->load($addr)) & 0x80) != 0) && ((($this->REG_ACC ^ $temp) & 0x80)) != 0) ? 1 : 0);
				$this->F_CARRY = ($temp > 255 ? 1 : 0);
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp & 0xFF;
				$this->REG_ACC = ($temp & 255);
				$cycleCount += $cycleAdd;
				break;
			case 1:
				$this->REG_ACC = $this->REG_ACC & $this->load($addr);
				$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
				$this->F_ZERO = $this->REG_ACC;

				if ($addrMode != 11) {
					$cycleCount += $cycleAdd;
				}
				break;
			case 2:
				if ($addrMode == 4) {
					$this->F_CARRY = ($this->REG_ACC >> 7) & 1;
					$this->REG_ACC = ($this->REG_ACC << 1) & 255;
					$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
					$this->F_ZERO = $this->REG_ACC;
				} else {
					$temp = $this->load($addr);
					$this->F_CARRY = ($temp >> 7) & 1;
					$temp = ($temp << 1) & 255;
					$this->F_SIGN = ($temp >> 7) & 1;
					$this->F_ZERO = $temp;
					$this->write($addr, $temp);
				}
				break;
			case 3:
				if ($this->F_CARRY == 0) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 4:
				if ($this->F_CARRY == 1) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 5:
				if ($this->F_ZERO == 0) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 6:
				$temp = $this->load($addr);
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_OVERFLOW = ($temp >> 6) & 1;
				$temp &= $this->REG_ACC;
				$this->F_ZERO = $temp;
				break;
			case 7:
				if ($this->F_SIGN == 1) {
					$cycleCount++;
					$this->PC = $addr;
				}
				break;
			case 8:
				if ($this->F_ZERO != 0) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 9:
				if ($this->F_SIGN == 0) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 10:
				$this->PC += 2;
				$this->push(($this->PC >> 8) & 255);
				$this->push($this->PC & 255);
				$this->F_BRK = 1;

				$this->push(
					($this->F_CARRY) |
					(($this->F_ZERO == 0 ? 1 : 0) << 1) |
					($this->F_INTERRUPT << 2) |
					($this->F_DECIMAT << 3) |
					($this->F_BRK << 4) |
					($this->F_NOTUSED << 5) |
					($this->F_OVERFLOW << 6) |
					($this->F_SIGN << 7)
				);

				$this->F_INTERRUPT = 1;
				$this->PC = $this->load16bit(0xFFFE);
				$this->PC--;
				break;
			case 11:
				if ($this->F_OVERFLOW == 0) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 12:
				if ($this->F_OVERFLOW == 1) {
					$cycleCount += (($opaddr & 0xFF00) != ($addr & 0xFF00) ? 2 : 1);
					$this->PC = $addr;
				}
				break;
			case 13:
				$this->F_CARRY = 0;
				break;
			case 14:
				$this->F_DECIMAL = 0;
				break;
			case 15:
				$this->F_INTERRUPT = 0;
				break;
			case 16:
				$this->F_OVERFLOW = 0;
				break;
			case 17:
				$temp = $this->REG_ACC - $this->load($addr);
				$this->F_CARRY = ($temp >= 0 ? 1 : 0);
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp & 0xFF;
				$cycleCount += $cycleAdd;
				break;
			case 18:
				$temp = $this->REG_X - $this->load($addr);
				$this->F_CARRY = ($temp >= 0 ? 1 : 0);
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp & 0xFF;
				break;
			case 19:
				$temp = $this->REG_Y - $this->load($addr);
				$this->F_CARRY = ($temp >= 0 ? 1 : 0);
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp & 0xFF;
				break;
			case 20:
				$temp = ($this->load($addr) - 1) & 0xFF;
				$this->F_SIGN = ($temp >> 7 ) & 1;
				$this->F_ZERO = $temp;
				$this->write($addr, $temp);
				break;
			case 21:
				$this->REG_X = ($this->REG_X - 1) & 0xFF;
				$this->F_SIGN = ($this->REG_X >> 7) & 1;
				$this->F_ZERO = $this->REG_X;
				break;
			case 22:
				$this->REG_Y = ($this->REG_Y - 1) & 0xFF;
				$this->F_SIGN = ($this->REG_Y >> 7) & 1;
				$this->F_ZERO = $this->REG_Y;
				break;
			case 23:
				$this->REG_ACC = ($this->load($addr) ^ $this->REG_ACC) & 0xFF;
				$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
				$this->F_ZERO = $this->REG_ACC;
				$cycleCount += $cycleAdd;
				break;
			case 24:
				$temp = ($this->load($addr) + 1) & 0xFF;
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp;
				$this->write($addr, $temp & 0xFF);
				break;
			case 25:
				$this->REG_X = ($this->REG_X + 1) & 0xFF;
				$this->F_SIGN = ($this->REG_X >> 7) & 1;
				$this->F_ZERO = $this->REG_X;
				break;
			case 26:
				$this->REG_Y++;
				$this->REG_Y &= 0xFF;
				$this->F_SIGN = ($htis->REG_Y >> 7) & 1;
				$this->F_ZERO = $this->REG_Y;
				break;
			case 27:
				$this->PC = $addr - 1;
				break;
			case 28:
				$this->push(($this->PC >> 8) & 255);
				$this->push($this->PC & 255);
				$this->PC = $addr - 1;
				break;
			case 29:
				$this->REG_ACC = $this->load($addr);
				$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
				$this->F_ZERO = $this->REG_ACC;
				$cycleCount += $cycleAdd;
				break;
			case 30:
				$this->REG_X = $this->load($addr);
				$this->F_SIGN = ($this->REG_X >> 7) & 1;
				$this->F_ZERO = $this->REG_X;
				$cycleCount += $cycleAdd;
				break;
			case 31:
				$this->REG_Y = $this->load($addr);
				$this->F_SIGN = ($this->REG_Y >> 7) & 1;
				$this->F_ZERO = $this->REG_Y;
				$cycleCount += $cycleAdd;
				break;
			case 32:
				if ($addrMode == 4) {
					$temp = ($this->REG_ACC & 0xFF);
					$this->F_CARRY = $temp & 1;
					$temp >>= 1;
					$this->REG_ACC = $temp;
				} else {
					$temp = $this->load($addr) & 0xFF;
					$this->F_CARRY = $temp & 1;
					$temp >>= 1;
					$this->write($addr, $temp);
				}

				$this->F_SIGN = 0;
				$this->F_ZERO = $temp;
				break;
			case 33:
				break;
			case 34:
				$temp = ($this->load($addr) | $this->REG_ACC) & 255;
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp;
				$this->REG_ACC = $temp;
				if ($addrMode != 11) {
					$cycleCount += $cycleAdd;
				}
				break;
			case 35:
				$this->push($this->REG_ACC);
				break;
			case 36:
				$this->F_BRK = 1;
				$this->push(
					($this->F_CARRY) |
					(($this->F_ZERO == 0 ? 1 : 0) << 1) |
					($this->F_INTERRUPT << 2) |
					($this->F_DECIMAL << 3) |
					($this->F_BRK << 4) |
					($this->F_NOTUSED << 5) |
					($this->F_OVERFLOW << 6) |
					($this->F_SIGN << 7)
				);
				break;
			case 37:
				$this->REG_ACC = $this->pull();
				$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
				$this->F_ZERO = $this->REG_ACC;
				break;
			case 38:
				$temp = $this->pull();
				$this->F_CARRY = ($temp) & 1;
				$this->F_ZERO = ((($temp >> 1) & 1) == 1) ? 0 : 1;
				$this->F_INTERRUPT = ($temp >> 2) & 1;
				$this->F_DECIMAL = ($temp >> 3) & 1;
				$this->F_BRK = ($temp >> 4) & 1;
				$this->F_NOTUSED = ($temp >> 5) & 1;
				$this->F_OVERFLOW = ($temp >> 6) & 1;
				$this->F_SIGN = ($temp >> 7) & 1;

				$this->F_NOTUSED = 1;
				break;
			case 39:
				if ($addrMode == 4) {
					$temp = $this->REG_ACC;
					$add = $this->F_CARRY;
					$this->F_CARRY = ($temp >> 7) & 1;
					$temp = (($temp << 1) & 0xFF) + $add;
					$this->REG_ACC = $tmep;
				} else {
					$temp = $this->load($addr);
					$add = $this->F_CARRY;
					$this->F_CARRY = ($temp >> 7) & 1;
					$temp = (($temp << 1) & 0xFF) + $add;
					$this->write($addr, $temp);
				}

				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp;
				break;
			case 40:
				if ($addrMode == 4) {
					$add = $this->F_CARRY << 7;
					$this->F_CARRY = $this->REG_ACC & 1;
					$temp = ($this->REG_ACC >> 1) + $add;
					$this->REG_ACC = $temp;
				} else {
					$temp = $this->load($addr);
					$add = $this->F_CARRY << 7;
					$this->F_CARRY = $temp & 1;
					$temp = ($temp >> 1) + $add;
					$this->write($addr, $temp);
				}

				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp;
				break;
			case 41:
				$temp = $this->pull();
				$this->F_CARRY = ($temp) & 1;
				$this->F_ZERO = (($temp >> 1) & 1) == 0 ? 1 : 0;
				$this->F_INTERRUPT = ($temp >> 2) & 1;
				$this->F_DECIMAL = ($temp >> 3) & 1;
				$this->F_BRK = ($temp >> 4) & 1;
				$this->F_NOTUSED = ($temp >> 5) & 1;
				$this->F_OVERFLOW = ($temp >> 6) & 1;
				$this->F_SIGN = ($temp >> 7) & 1;

				$this->PC = $this->pull();
				$this->PC--;
				$this->F_NOTUSED = 1;
				break;
			case 42:
				$this->PC = $this->pull();
				$this->PC += ($this->pull() << 8);

				if ($this->PC == 0xFFFF) {
					return;
				}
				break;
			case 43:
				$temp = $this->REG_ACC - $this->load($addr) - (1 - $this->F_CARRY);
				$this->F_SIGN = ($temp >> 7) & 1;
				$this->F_ZERO = $temp & 0xFF;
				$this->F_OVERFLOW = (((($this->REG_ACC ^ $temp) & 0x80) != 0 && (($this->REG_ACC ^ $this->load($addr)) & 0x80) != 0) ? 1 : 0);
				$this->F_CARRY = ($temp < 0 ? 0 : 1);
				$this->REG_ACC = ($temp & 0xFF);

				if ($addrMode != 11) {
					$cycleCount += $cycleAdd;
				}
				break;
			case 44:
				$this->F_CARRY = 1;
				break;
			case 45:
				$this->F_DECIMAL = 1;
				break;
			case 46:
				$this->F_INTERRUPT = 1;
				break;
			case 47:
				$this->write($addr, $this->REG_ACC);
				break;
			case 48:
				$this->write($addr, $this->REG_X);
				break;
			case 49:
				$this->write($addr, $this->REG_Y);
				break;
			case 50:
				$this->REG_X = $this->REG_ACC;
				$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
				$this->F_ZERO = $this->REG_ACC;
				break;
			case 51:
				$this->REG_Y = $this->REG_ACC;
				$this->F_SIGN = ($this->REG_ACC >> 7) & 1;
				$this->F_ZERO = $this->REG_ACC;
				break;
			case 52:
				$this->REG_X = ($this->SP - 0x0100);
				$this->F_SIGN = ($this->SP >> 7) & 1;
				$this->F_ZERO = $this->REG_X;
				break;
			case 53:
				$this->REG_ACC = $this->REG_X;
				$this->F_SIGN = ($this->REG_X >> 7) & 1;
				$this->F_ZERO = $this->REG_X;
				break;
			case 54:
				$this->SP = ($this->REG_X + 0x0100);
				$this->stackWrap();
				break;
			case 55:
				$this->REG_ACC = $this->REG_Y;
				$this->F_SIGN = ($this->REG_Y >> 7) & 1;
				$this->F_ZERO = $this->REG_Y;
				break;
			default:
				print "Game crashed, invalid opcode at ".$opaddr.": ".$opinf.PHP_EOL;

				$this->NES->stop(true);
				break;
		}

		return $cycleCount;
	}


	public function load($addr) {
		if ($addr < 0x2000) {
			return $this->mem[$addr & 0x7FF];
		} else {
			return $this->NES->MMAP->load($addr);
		}
	}

	public function load16bit($addr) {
		if ($addr < 0x1FFF) {
			return $this->mem[$addr & 0x7FF] | ($this->mem[($addr + 1) & 0x7FF] << 8);
		} else {
			return $this->NES->MMAP->load($addr) | ($this->NES->MMAP->load($addr+1) << 8);
		}
	}

	public function write($addr, $val) {
		if ($addr < 0x2000) {
			$this->mem[$addr & 0x7FF] = $val;
		} else {
			$this->NES->MMAP->write($addr, $val);
		}
	}

	public function requestIrq($type) {
		if ($this->irqRequested === true) {
			if ($type == $this::IRQ_NORMAL) {
				return;
			}
		}

		$this->irqRequested = true;
		$this->irqType = $type;
	}

	public function push($val) {
		$this->NES->MMAP->write($this->SP, $val);
		$this->SP--;
		$this->stackWrap();
	}

	public function stackWrap() {
		$this->SP = 0x0100 | ($this->SP & 0xFF);
	}

	public function pull() {
		$this->SP++;
		$this->stackWrap();
		return $this->NES->MMAP->load($this->SP);
	}

	public function pageCrossed($addr1, $addr2) {
		return (($addr1 & 0xFF00) != ($addr2 & 0xFF00));
	}

	public function haltCycles($n) {
		$this->cyclesToHalt += $n;
	}

	public function doNonMaskableInterrupt($status) {
		// Only when vblank interrupts are enabled
		if (($this->NES->MMAP->load(0x2000) & 128) != 0) {
			$this->PC_NEW++;
			$this->push(($this->PC_NEW >> 8) && 0xFF);
			$this->push($status);

			$this->PC_NEW = $this->NES->MMAP->load(0xFFFA) | ($this->NES->MMAP->load(0xFFFB) << 8);
			$this->PC_NEW--;
		}
	}

	public function doResetInterrupt() {
		$this->PC_NEW = $this->NES->MMAP->load(0xFFFC) | ($this->NES->MMAP->load(0xFFFD) << 8);
		$this->PC_NEW--;
	}

	public function doIrq($status) {
		$this->PC_NEW++;
		$this->push(($this->PC_NEW >> 8) & 0xFF);
		$this->push($this->PC_NEW & 0xFF);
		$this->push($status);
		$this->F_INTERRUPT_NEW = 1;
		$this->F_BRK_NEW = 0;

		$this->PC_NEW = $this->NES->MMAP->load(0xFFFE) | ($this->NES->MMAP->load(0xFFFF) << 8);
		$this->PC_NEW--;
	}

	public function getStatus() {
		return ($this->F_CARRY)
				| ($this->F_ZERO << 1)
				| ($this->F_INTERRUPT << 2)
				| ($this->F_DECIMAL << 3)
				| ($this->F_BRK << 4)
				| ($this->F_NOTUSED << 5)
				| ($this->F_OVERFLOW << 6)
				| ($this->F_SIGN << 7);
	}

	public function setStatus($status) {
		$this->F_CARRY = ($status) & 1;
		$this->F_ZERO = ($status >> 1) & 1;
		$this->F_INTERRUPT = ($status >> 2) & 1;
		$this->F_DECIMAL = ($status >> 3) & 1;
		$this->F_BRK = ($status >> 4) & 1;
		$this->F_NOTUSED = ($status >> 5) & 1;
		$this->F_OVERFLOW = ($status >> 6) & 1;
		$this->F_SIGN = ($status >> 7) & 1;
	}
}
