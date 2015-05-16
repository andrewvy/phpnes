<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\CPU;

class OpData {
	public $opdata = [];

	const INS_ADC = 0;
	const INS_AND = 1;
	const INS_ASL = 2;

	const INS_BCC = 3;
	const INS_BCS = 4;
	const INS_BEQ = 5;
	const INS_BIT = 6;
	const INS_BMI = 7;
	const INS_BNE = 8;
	const INS_BPL = 9;
	const INS_BRK = 10;
	const INS_BVC = 11;
	const INS_BVS = 12;

	const INS_CLC = 13;
	const INS_CLD = 14;
	const INS_CLI = 15;
	const INS_CLV = 16;
	const INS_CMP = 17;
	const INS_CPX = 18;
	const INS_CPY = 19;

	const INS_DEC = 20;
	const INS_DEX = 21;
	const INS_DEY = 22;

	const INS_EOR = 23;

	const INS_INC = 24;
	const INS_INX = 25;
	const INS_INY = 26;

	const INS_JMP = 27;
	const INS_JSR = 28;

	const INS_LDA = 29;
	const INS_LDX = 30;
	const INS_LDY = 31;
	const INS_LSR = 32;

	const INS_NOP = 33;

	const INS_ORA = 34;

	const INS_PHA = 35;
	const INS_PHP = 36;
	const INS_PLA = 37;
	const INS_PLP = 38;

	const INS_ROL = 39;
	const INS_ROR = 40;
	const INS_RTI = 41;
	const INS_RTS = 42;

	const INS_SBC= 43;
	const INS_SEC = 44;
	const INS_SED = 45;
	const INS_SEI = 46;
	const INS_STA = 47;
	const INS_STX = 48;
	const INS_STY = 49;

	const INS_TAX= 50;
	const INS_TAY = 51;
	const INS_TSX = 52;
	const INS_TXA = 53;
	const INS_TXS = 54;
	const INS_TYA = 55;

	const INS_DUMMY = 56;

	public function setOp($inst, $op, $addr, $size, $cycles) {
		$this->opdata[$op] =
			(($inst & 0xFF)) |
			(($addr & 0xFF) << 8) |
			(($size & 0xFF) << 16) |
			(($cycles & 0xFF) << 24);
	}
}
