//HINT: https://de.wikipedia.org/wiki/Benutzer:Schnark/js/veAutocorrect.js
( function ( ve ) {

	var replacements = {
		' :)': ' 😊',
		' :-)': ' 😊',
		' :(': ' 😞',
		' :-(': ' 😞',
		' :D': ' 😄',
		' :P': ' 😛',
		' :O': ' 😲',
		' ;)': ' 😉',
		' ;-)': ' 😉',
		' :|': ' 😐',
		' :-|': ' 😐',
		' :/': ' 😕',
		' :-/': ' 😕',
		' :-@': ' 😠',
		' :-X': ' 🤐',
		' :\'(': ' 😢',
		' :-O': ' 😲',
		' :-S': ' 😖',
		':lol:': '😂',
		' (:|': '😴',
		' :-[': ' 😳',
		' [-X': ' 😳',
		' (v)': ' ✅',
		':good:': '👍',
		' (x)': ' ❌',
		':bad:': '👎',
		' (?)': ' ❓',
		'--> ': '→',
		'<-- ': '←',
		'<-> ': '↔'
	};

	var sequences = [];

	for ( var key in replacements ) {
		var value = replacements[ key ];
		var encKey = '';
		for ( var i = 0; i < key.length; i++ ) {
			encKey += key.charCodeAt( i );
		}

		var commandName = 'insertReplacement' + encKey;
		var sequenceName = 'replacement' + encKey;

		var command = new ve.ui.Command(
			commandName,
			'content',
			'insert',
			{
				args: [ value, true, true ]
			}
		);

		var replacementSequence = new ve.ui.Sequence(
			sequenceName,
			commandName,
			key,
			key.length
		);

		ve.ui.commandRegistry.register( command );
		sequences.push( replacementSequence );
	}

	sequences.push( new ext.visualEditorPlus.ui.BackTickCodeSequence() );
	sequences.push( new ext.visualEditorPlus.ui.TildeStrikeThroughSequence() );
	sequences.push( new ext.visualEditorPlus.ui.UnderlineItalicSequence() );

	for ( var j = 0; j < sequences.length; j++ ) {
		var sequence = sequences[ j ];
		if ( ve.ui.sequenceRegistry ) {
			ve.ui.sequenceRegistry.register( sequence );
		}
		if ( ve.ui.wikitextSequenceRegistry ) {
			ve.ui.wikitextSequenceRegistry.register( sequence );
		}
	}
}( ve ) );
