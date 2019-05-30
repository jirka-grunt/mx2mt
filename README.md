# mx2mt

*Limited MusicXML to MusixTeX converter*

This converter is primary used to transfer songs of [Falešné společenstvo](http://fs.ulmus.cz/) from [Mozart](http://www.mozart.co.uk/) music processor to [MusixTeX](https://www.ctan.org/pkg/musixtex) typesetting notation via small subset of [MusicXML](http://www.musicxml.com/) 3.0 format.

Written in [PHP](http://php.net/), sorry. But you can try [online version](http://mx2mt.ulmus.cz/).

You also need small [header.tex](https://github.com/jirka-grunt/mx2mt/blob/master/header.tex) file for some auxiliary and compatibility stuff.

Supported features:
- score-partwise and score-timewise XML
- clefs: treble
- meters: with single denominator
- keys: flats or sharps
- ordinary notes and rests
- lengths: from 1/32 to whole note
- heights: from C2 to H6 (5 octaves)
- accidentals: sharp, double sharp, natural, flat, double flat
- single dots from 1/16 notes and rests
- articulations: accent, staccato, fermata
- beams: single, duble, partial, dotted
- multiple measures lasting rests
- slurs and ties
- repeats: left, right, both
- endings: begin and end volta
- double bar lines
- chord (non-spacing) notes
- multiple staves

Some features may be added or extended in future (but most of them probably not).

Additional features:
- correct vertical alignment with multiple staves
- merging meters/signatures to general ones when possible
- automatic orientation of stems, beams, slurs and ties

Known limitations:
- only beams non-overlapping within one staff are supported
- repeats only one bar long don't work (not really limitation)
- bar lines & voltas are synchronized over all staves (and taken from first staff)
- number of staves can't be changed
- fermatas have fixed height
- automatic orientation of stems, beams, slurs and ties

As MusicXML is pretty complex format there definitely are plenty of unknown limitations.
Moreover, conversion is tested only with XML exported from Mozart version 12.

Note: You can also give a chance to xml2pmx tool (binary distribution only).
It convers MusicXML to PMX format, which is pre-processor for MusixTeX. See
[archive](https://icking-music-archive.org/software/htdocs/) for details.
