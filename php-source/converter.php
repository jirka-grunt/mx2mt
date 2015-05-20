<?php

	require_once 'data.php';
	require_once 'parser.php';
	require_once 'processor.php';
	require_once 'generator.php';

	class Converter {

		public function convert(SimpleXMLElement $xml) {

			if ($xml->getName() == 'score-partwise') {
				$style = simplexml_load_file(__DIR__.'/../musicxml-3.0/parttime.xsl');
				$xslt = new XSLTProcessor();
				$xslt->importStylesheet($style);
				$simple = dom_import_simplexml($xml);
				$doc = $xslt->transformToDoc($simple);
				$xml = simplexml_import_dom($doc);
			}
			assert('$xml->getName() == "score-timewise"');

			$parser = new Parser();
			$music = $parser->getMusic($xml);

			$processor = new Processor();
			$notation = $processor->getNotation($music);

			$generator = new Generator();
			return $generator->getTex($notation);
		}

	}

