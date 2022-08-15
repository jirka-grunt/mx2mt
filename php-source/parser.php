<?php

	class Parser {

		private $pitches = array();

		private $types = array(
				'32nd'    => 1,
				'16th'    => 2,
				'eighth'  => 4,
				'quarter' => 8,
				'half'    => 16,
				'whole'   => 32,
				'breve'   => 64,
				'long'    => 128,
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
			$multiple = 0;
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
								case 'measure-style':
									if (isset($x_attribute->{'multiple-rest'})) {
										$multiple = (int) $x_attribute->{'multiple-rest'};
										$multiplePause = new FullPause;
										$multiplePause->long = $this->types['whole'];
										$multiplePause->count = $multiple;
									}
									break;
							}
						}
					}

					$x_notes = $x_part->xpath('(note|harmony)');
					$previous = NULL;
					$gchord = NULL;
					$tuplet = 0;
					foreach ($x_notes as $x_note) {
						if ($x_note->getName() === 'harmony') {
							$gchord = new Chord;
							assert('isset($x_note->root->{\'root-step\'})');
							$gchord->base = (string) $x_note->root->{'root-step'};
							if (isset($x_note->kind)) {
								$kind = (string) $x_note->kind;
								switch ($kind) {
									case 'major':
										break;
									case 'minor':
										$gchord->minor = TRUE;
										break;
									case 'dominant':
										$gchord->seven = TRUE;
										break;
									case 'minor-seventh':
										$gchord->minor = TRUE;
										$gchord->seven = TRUE;
										break;
									case 'augmented':
										$gchord->plus = 1;
										break;
									case 'diminished':
										$gchord->plus = -1;
										break;
									case 'major-seventh':
										$gchord->seven = TRUE;
										$gchord->maj = TRUE;
										break;
									case 'major-minor':
										$gchord->minor = TRUE;
										$gchord->seven = TRUE;
										$gchord->maj = TRUE;
										break;
									case 'suspended-second':
										$gchord->sus = 2;
										break;
									case 'suspended-fourth':
										$gchord->sus = 4;
										break;
									case 'power':
										assert('$x_note->kind["text"] == 5');
										$gchord->power = TRUE;
										break;
									default:
										$gchord->unsupported = $this->getUnsupported('HARMONY KIND', $gchord->base.' '.$kind);
								}
							}
							if (isset($x_note->root->{'root-alter'})) {
								$alter = (string) $x_note->root->{'root-alter'};
								switch ($alter) {
									case '1':
										$gchord->alter = 1;
										break;
									case '-1':
										$gchord->alter = -1;
										break;
									default:
										$gchord->unsupported = $this->getUnsupported('HARMONY ALTER', $gchord->base.' '.$alter);
								}
							}
						} else {
							if ($multiple !== 0) {
								assert('count($x_notes) === 1');
								$multiple--;
								if (empty($multiplePause)) {
									assert('isset($x_note->rest)');
									continue 3;
								}
								$duration = $multiplePause;
								unset($multiplePause);
							} else {
								$duration = $this->getDuration($x_note);
							}

							$x_ties = $x_note->xpath('notations/tied');
							foreach ($x_ties as $x_tie) {
								$type = (string) $x_tie['type'];
								if ($type == 'start') {
									$tie = new TieStart;
								} elseif ($type == 'stop') {
									$tie = new TieEnd;
								}
								$tie->number = (int) $x_tie['number'];
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
								$slur->number = (int) $x_slur['number'];
								$duration->connections[] = $slur;
							}

							$x_tuplet = $x_note->xpath('time-modification');
							if (!empty($x_tuplet)) {
								assert('count($x_tuplet)===1');
								$x_tuplet = $x_tuplet[0];
								$actual = (int) $x_tuplet->{'actual-notes'};
								$normal = (int) $x_tuplet->{'normal-notes'};
								if (
									(($actual == 3) && ($normal == 2))
									||
									(($actual == 2) && ($normal == 3))
								) {
									if ($tuplet == 0) {
										$duration->tuplet = $actual;
										$tuplet = $actual - 1;
									} else {
										$tuplet--;
									}
								} else {
									$duration->tuplet = $this->getUnsupported('TUPLET', $actual.' for '.$normal);
								}
							} else {
								assert('$tuplet===0');
							}

							$x_fermatas = $x_note->xpath('notations/fermata');
							foreach ($x_fermatas as $x_fermata) {
								$type = (string) $x_fermata['type'];
								$duration->fermata = ($type != 'inverted');
							}

							$x_accents = $x_note->xpath('notations/articulations/accent');
							foreach ($x_accents as $x_accent) {
								assert('$duration instanceof Note');
								$placement = (string) $x_accent['placement'];
								$accent = new Accent;
								$accent->below = $placement == 'below';
								$duration->articulations[] = $accent;
							}

							$x_staccatos = $x_note->xpath('notations/articulations/staccato');
							foreach ($x_staccatos as $x_staccato) {
								assert('$duration instanceof Note');
								$placement = (string) $x_staccato['placement'];
								$staccato = new Staccato;
								$staccato->below = $placement == 'below';
								$duration->articulations[] = $staccato;
							}

							$x_chord = $x_note->xpath('chord');
							if (!empty($x_chord)) {
								assert('$previous instanceof Note');
								assert('get_class($duration)==="Note"');
								$previous->chords[] = $duration;
								continue;
							}

							$duration->gchord = $gchord;
							$gchord = NULL;

							$part->durations[] = $duration;
							$previous = $duration;
						}
					}

					$x_barlines = $x_part->xpath('barline');
					if (!empty($x_barlines)) {
						foreach ($x_barlines as $x_barline) {
							$x_ending = $x_barline->xpath('ending');
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

							$x_repeat = $x_barline->xpath('repeat');
							if (!empty($x_repeat)) {
								assert('count($x_repeat)===1');
								$x_repeat = $x_repeat[0];
								$direction = (string) $x_repeat['direction'];
								$repeat = new Repeat;
								$repeat->left = ($direction == 'forward');
								$repeat->right = ($direction == 'backward');
								$part->endings[] = $repeat;
							}

							$x_double = $x_barline->xpath('bar-style');
							if (!empty($x_double)) {
								assert('count($x_double)===1');
								$x_double = $x_double[0];
								$style = (string) $x_double;
								if ($style == 'light-light') {
									$part->endings[] = new DoubleBar;
								}
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
			$alter = NULL;
			$accidental = NULL;
			$beams = array();
			foreach ($x_note as $info) {
				$name = $info->getName();
				switch ($name) {
					case 'pitch':
						$step = (string) $info->step;
						$octave = (string) $info->octave;
						if (!isset($this->pitches[$octave][$step])) {
							return $this->getUnsupported('NOTE PITCH', 'octave: '.$octave.', step: '.$step);
						}
						$height = $this->pitches[$octave][$step];
						if (isset($info->alter)) {
							$alter = (string) $info->alter;
							if (!in_array($alter, array('-2', '-1', '1', '2'))) {
								return $this->getUnsupported('PITCH ALTER', 'value: '.$alter);
							}
						}
						break;
					case 'accidental':
						$accidental = (string) $info;
						if (!in_array($accidental, array('double-sharp', 'sharp', 'natural'))) {
							return $this->getUnsupported('ACCIDENTAL', 'name: '.$accidental);
						}
						break;
					case 'type':
						$type = (string) $info;
						if (!isset($this->types[$type])) {
							return $this->getUnsupported('NOTE TYPE', 'type: '.$type);
						}
						$long = $this->types[$type];
						break;
					case 'beam':
						$beams[] = (string) $info;
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
					return $this->getUnsupported('DOT FOR 32nd', 'octave: '.$octave.', step: '.$step);
				}
				$long *= 1.5;
			}
			
			if (isset($height)) {
				if (!empty($beams)) {
					$duration = $this->getBeam($beams);
				} else {
					$duration = new Note();
				}
				$duration->height = $height;
				if ($accidental === 'natural') {
					$duration->alter = 0;
				} elseif (!\is_null($accidental)) {
					$duration->alter = (int) $alter;
				}
			} elseif ($rest) {
				$duration = new Pause;
			} else {
				return $this->getUnsupported('NOTE', 'unknown type');
			}
			$duration->long = $long;
			return $duration;
		}

		protected function getBeam(array $beams) {
			$begin = array();
			$continue = array();
			$end = array();
			$partial = 0;
			foreach ($beams as $beam) {
				switch ($beam) {
					case 'begin':
						$begin[] = $beam;
						break;
					case 'continue':
						$continue[] = $beam;
						break;
					case 'end':
						$end[] = $beam;
						break;
					case 'forward hook':
						assert('$partial == 0');
						$partial = 1;
						break;
					case 'backward hook':
						assert('$partial == 0');
						$partial = -1;
						break;
					default:
						return $this->getUnsupported('BEAM TYPE', 'type: '.$beam);
				}
			}
			$duration = NULL;
			if (!empty($continue)) {
				$duration = new BeamContinue();
				$duration->multiplicity = count($continue);
				$duration->multiplicity += count($begin);
			} elseif (!empty($begin)) {
				$duration = new BeamStart();
				$duration->multiplicity = count($begin);
				assert('count($end)==0');
			} elseif (!empty($end)) {
				$duration = new BeamEnd();
				$duration->multiplicity = count($end);
			}
			assert('!is_null($duration)');
			if ($duration->multiplicity > 3) {
				return $this->getUnsupported('BEAM MULTIPLICITY', 'count: '.$duration->multiplicity);
			}
			if ($partial == 1) {
				assert('$duration instanceof BeamStart || $duration instanceof BeamContinue');
			} elseif ($partial == -1) {
				assert('$duration instanceof BeamEnd || $duration instanceof BeamContinue');
			}
			$duration->partial = $partial;
			return $duration;
		}

		protected function getUnsupported($feature, $info) {
			$unsupported = new Unsupported;
			$unsupported->info = 'UNSUPPORTED '.$feature.' --  measure: '.$this->measureNumber.', part: '.$this->partNumber.', '.$info;
			return $unsupported;
		}

	}

