<?php

// phpcs:ignoreFile

if ( $argc < 2 ) {
	fprintf( STDERR, "Usage: php %s <count>\n", $argv[0] );
	exit( 1 );
}

$count = (int)$argv[1];
if ( $count < 1 ) {
	fprintf( STDERR, "Error: Count must be a positive integer.\n" );
	exit( 1 );
}

for ( $i = 0; $i < $count; $i++ ) {
	$key = generateKey();
	$normalized = trim( strtolower( str_replace( '-', '', $key ) ) );
	$hash = sha1( $normalized );
	echo "$key\t$normalized\t$hash\n";
}

function generateKey(): string {
	$blocks = [];
	for ( $i = 0; $i < 4; $i++ ) {
		$block = '';
		for ( $j = 0; $j < 4; $j++ ) {
			$block .= chr( random_int( 65, 90 ) );
		}
		$blocks[] = $block;
	}
	return implode( '-', $blocks );
}
