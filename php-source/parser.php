<?php

	class Parser {

		private $pitches = array();

		private $types = array(
				'16th'    => 1,
				'eighth'  => 2,
				'quarter' => 4,
				'half'    => 8,
				'whole'   => 16,
			);

		protected $measureNumber;

		protected $partNumber;

		public function __construct() {
			$names = array('C', 'D', 'E', 'F', 'G', 'A', 'B');
			$height = 1;
			for ($octave=2; $octave<=6; $octave++) {
				foreach ($names as $name) {
					$this->pitches[$octave][$name] = $height;
					$height++;
				}
			}
		}

		public function getMusic($xml) {
			$music = new Music;
			$x_measures = $xml->xpath('//measure');
			foreach ($x_measures as $x_measure) {
				$this->measureNumber = (string) $x_measure['number'];
				$measure = new Measure;
				$x_parts = $x_measure->xpath('part');
				foreach ($x_parts as $x_part) {
					$this->partNumber = (string) $x_part['id'];
					$part = new Part;

					$x_attributes = $x_part->xpath('attributes');
					if (!empty($x_attributes)) {
						assert('count($x_attributes)===1');
						$x_attributes = $x_attributes[0];
						foreach ($x_attributes as $x_attribute) {
							$name = $x_attribute->getName();
							switch ($name) {
								case 'time':
									$meter = new Meter;
									$meter->beats = (string) $x_attribute->beats;
									$meter->unit = (string) $x_attribute->{'beat-type'};
									$part->attributes[] = $meter;
									break;
								case 'key':
									$signature = new Signature;
									$signature->fifths = (string) $x_attribute->fifths;
									$part->attributes[] = $signature;
									break;
							}
						}
					}

					$x_notes = $x_part->xpath('note');
					foreach ($x_notes as $x_note) {
						$duration = $this->getDuration($x_note);

						$x_ties = $x_note->xpath('notations/tied');
						foreach ($x_ties as $x_tie) {
							$type = (string) $x_tie['type'];
							if ($type == 'start') {
								$tie = new TieStart;
							} elseif ($type == 'stop') {
								$tie = new TieEnd;
							}
							$duration->connections[] = $tie;
						}

						$x_slurs = $x_note->xpath('notations/slur');
						foreach ($x_slurs as $x_slur) {
							$type = (string) $x_slur['type'];
							if ($type == 'start') {
								$slur = new SlurStart;
							} elseif ($type == 'stop') {
								$slur = new SlurEnd;
							}
							$duration->connections[] = $slur;
						}

						$part->durations[] = $duration;
					}

					$x_barlines = $x_part->xpath('barline');
					if (!empty($x_barlines)) {
						assert('count($x_barlines)===1');
						$x_barlines = $x_barlines[0];

						$x_ending = $x_barlines->xpath('ending');
						if (!empty($x_ending)) {
							assert('count($x_ending)===1');
							$x_ending = $x_ending[0];
							$type = (string) $x_ending['type'];
							$volta = new Volta;
							$volta->number = (string) $x_ending['number'];
							if ($type == 'start') {
								$volta->start = TRUE;
							} elseif ($type == 'stop') {
								$volta->start = FALSE;
							} else {
								$volta = $this->getUnsupported('VOLTA TYPE', 'number: '.$volta->number.', type: '.$type);
							}
							$part->endings[] = $volta;
						}

						$x_repeat = $x_barlines->xpath('repeat');
						if (!empty($x_repeat)) {
							assert('count($x_repeat)===1');
							$x_repeat = $x_repeat[0];
							$direction = (string) $x_repeat['direction'];
							$repeat = new Repeat;
							$repeat->left = ($direction == 'forward');
							$repeat->right = ($direction == 'backward');
							$part->endings[] = $repeat;
						}

						$x_double = $x_barlines->xpath('bar-style');
						if (!empty($x_double)) {
							assert('count($x_double)===1');
							$x_double = $x_double[0];
							$style = (string) $x_double;
							if ($style == 'light-light') {
								$part->endings[] = new DoubleBar;
							}
						}
					}
					$measure->parts[] = $part;
				}
				$music->measures[] = $measure;
			}
			return $music;
		}

		protected function getDuration($x_note) {
			$rest = FALSE;
			$pointed = 0;
			foreach ($x_note as $info) {
				$name = $info->getName();
				switch ($name) {
					case 'pitch':
						$step = (string) $info->step;
						$octave = (string) $info->octave;
						if (!isset($this->pitches[$octave][$step])) {
							return $this->getUnsupported('NOTE PITCH', 'octave: '.$octave.', step: '.$step);
						}
						// TODO: alter - sharp/flat
						$height = $this->pitches[$octave][$step];
						break;
					case 'type':
						$type = (string) $info;
						if (!isset($this->types[$type])) {
							return $this->getUnsupported('NOTE TYPE', 'type: '.$type);
						}
						$long = $this->types[$type];
						break;
					case 'beam':
						$beam = (string) $info;
						break;
					case 'rest':
						$rest = TRUE;
						break;
					case 'dot':
						$pointed++;
						break;
				}
			}

			if ($pointed > 1) {
				return $this->getUnsupported('MORE DOTS', 'dots: '.$pointed.', octave: '.$octave.', step: '.$step);
			}
			if ($pointed == 1) {
				if ($long == 1) {
					return $this->getUnsupported('DOT FOR 16th', 'octave: '.$octave.', step: '.$step);
				}
				$long *= 1.5;
			}
			
			if (isset($height)) {
				if (isset($beam)) {
					// TODO: double beams
					// TODO: partial beams
					// TODO: beams changes
					if ($beam=='begin') {
						$duration = new BeamStart();
					} elseif ($beam=='end') {
						$duration = new BeamEnd();
					} elseif ($beam=='continue') {
						$duration = new BeamContinue();
					} else {
						return $this->getUnsupported('BEAM TYPE', 'type: '.$beam);
					}
				} else {
					$duration = new Note();
				}
				$duration->height = $height;
			} elseif ($rest) {
				$duration = new Pause;
			} else {
				return $this->getUnsupported('NOTE', 'unknown type');
			}
			$duration->long = $long;
			return $duration;
		}

		protected function getUnsupported($feature, $info) {
			$unsupported = new Unsupported;
			$unsupported->info = 'UNSUPPORTED '.$feature.' --  measure: '.$this->measureNumber.', part: '.$this->partNumber.', '.$info;
			return $unsupported;
		}

	}

