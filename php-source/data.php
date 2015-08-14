<?php

	// ----- from parser -----

	class Music {
		public $measures = array();
	}

	class Measure {
		public $parts = array();
	}

	class Part {
		public $attributes = array();
		public $durations = array();
		public $endings = array();
	}

	class Unsupported {
		public $info;
	}

	// ----- for generator -----

	class Notation {
		public $elements = array();
	}

	class Notes {
		public $long;
		public $parts = array();
	}

	class Nil {
	}

	// ----- ends -----

	abstract class Ending {
	}

	class Volta extends Ending {
		public $start;
		public $number;
	}

	class Repeat extends Ending {
		public $left;
		public $right;
	}

	class DoubleBar extends Ending {
	}

	// ----- divisions -----

	abstract class Division {
		public $number;
		public $leftRepeat;
		public $rightRepeat;
		public $double;
	}

	class StartPiece extends Division {
		public $parts;
		public $attributes = array();
	}

	class Bar extends Division {
	}

	class ChangeContext extends Division {
		public $attributes = array();
	}

	class EndPiece extends Division {
	}

	// ----- attributes -----

	abstract class Attribute {
	}

	class Meter extends Attribute {
		public $beats;
		public $unit;
	}

	class Signature extends Attribute {
		public $fifths;
	}

	// ----- durations -----

	abstract class Duration {
		public $long;
		public $connections = array();
	}

	class Pause extends Duration {
	}

	class Note extends Duration {
		public $height;
		public $alter;
		public $nolyr;
	}


	abstract class Beam extends Note {
		public $number;
		public $up;
		public $multiplicity;
		public $partial;
	}

	class BeamStart extends Beam {
		public $slant;
	}

	class BeamContinue extends Beam {
		public $change;
	}

	class BeamEnd extends Beam {
	}

	// ----- connections -----

	abstract class Connection {
		public $number;
	}

	abstract class ConnectionStart extends Connection {
		public $up;
	}

	abstract class ConnectionEnd extends Connection {
	}

	class TieStart extends ConnectionStart {
	}

	class TieEnd extends ConnectionEnd {
	}

	class SlurStart extends ConnectionStart {
	}

	class SlurEnd extends ConnectionEnd {
	}

