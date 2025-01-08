<?php

namespace MediaWiki\Extension\CollabPads\Backend\Model;

use Exception;

class Change implements \JsonSerializable {

	/**
	 * @var int
	 */
	private $start;

	/**
	 * @var array
	 */
	private $transactions;

	/**
	 * @var array
	 */
	private $selections;

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @var array
	 */
	private $storeLengthAtTransaction;

	/**
	 * @param int $start
	 * @param array $transactions
	 * @param array $selections
	 * @param array $stores
	 */
	public function __construct( int $start, array $transactions, array $selections, array $stores ) {
		$this->start = $start;
		$this->transactions = $this->normalizeTransactions( $transactions );
		$this->selections = $selections;
		$this->store = new Store( [], [] );
		$this->storeLengthAtTransaction = [];
		if ( $stores ) {
			foreach ( $stores as $store ) {
				if ( !$store ) {
					continue;
				}
				if ( is_array( $store ) ) {
					$store = Store::fromData( $store );
				}
				if ( $store->getLength() === 0 ) {
					continue;
				}
				$this->store->merge( $store );
				$this->storeLengthAtTransaction[] = $this->store->getLength();
			}
		}
	}

	/**
	 * @return int
	 */
	public function getStart(): int {
		return $this->start;
	}

	/**
	 * @param int $start
	 * @return void
	 */
	public function setStart( int $start ) {
		$this->start = $start;
	}

	/**
	 * @return Transaction[]
	 */
	public function getTransactions(): array {
		return $this->transactions;
	}

	/**
	 * @return array
	 */
	public function getSelections(): array {
		return $this->selections;
	}

	/**
	 * @return array
	 */
	public function getStores(): array {
		$start = 0;
		$stores = [];
		for ( $i = 0; $i < $this->getLength(); $i++ ) {
			if ( !isset( $this->storeLengthAtTransaction[$i] ) ) {
				continue;
			}
			$end = $this->storeLengthAtTransaction[$i];
			$sliced = $this->store->slice( $start, $end );
			if ( $sliced->getLength() > 0 ) {
				$stores[] = $sliced;
			}
			$start = $end;
		}
		return $stores;
	}

	/**
	 * @param int $length
	 * @return Change
	 */
	public function truncate( int $length ): Change {
		return new Change(
			$this->start,
			array_slice( $this->transactions, 0, $length ),
			[],
			array_slice( $this->getStores(), 0, $length )
		);
	}

	/**
	 * @param Change $otherChange
	 * @return Change
	 * @throws Exception
	 */
	public function concat( Change $otherChange ): Change {
		if ( $otherChange->getStart() !== $this->start + $this->getLength() ) {
			throw new Exception( 'this ends at ' . ( $this->start + $this->getLength() ) .
				' but other starts at ' . $otherChange->getStart() );
		}

		return new Change(
			$this->start,
			array_merge( $this->transactions, $otherChange->getTransactions() ),
			$otherChange->getSelections(),
			array_merge( $this->getStores(), $otherChange->getStores() )
		);
	}

	/**
	 * @param Transaction $transaction
	 * @param int $storeLength
	 * @return void
	 */
	public function pushTransaction( Transaction $transaction, int $storeLength ) {
		$this->transactions[] = $transaction;
		$this->storeLengthAtTransaction[] = $storeLength;
	}

	/**
	 * @param Change $other
	 * @return void
	 * @throws Exception
	 */
	public function push( Change $other ) {
		if ( $other->getStart() !== $this->start + $this->getLength() ) {
			throw new Exception( 'this ends at ' . ( $this->start + $this->getLength() ) .
				' but other starts at ' . $other->getStart() );
		}

		$stores = $other->getStores();
		foreach ( $other->getTransactions() as $i => $transaction ) {
			$store = $stores[ $i ] ?? null;
			if ( $store ) {
				$this->store->merge( $store );
			}
			$this->pushTransaction( $transaction, $this->store->getLength() );
		}
		$this->selections = $other->selections;
	}

	/**
	 * @param int $authorId
	 * @param Selection $selection
	 * @return void
	 */
	public function setSelection( int $authorId, Selection $selection ) {
		$this->selections[ $authorId ] = $selection->jsonSerialize();
	}

	/**
	 * @param int $start
	 * @return Change
	 */
	public function mostRecent( int $start ): Change {
		return new Change(
			$start,
			array_slice( $this->transactions, $start - $this->start ),
			$this->selections,
			array_slice( $this->getStores(), $start - $this->start )
		);
	}

	/**
	 * @return int
	 */
	public function getLength(): int {
		return count( $this->transactions );
	}

	/**
	 * @return bool
	 */
	public function isEmpty(): bool {
		return count( $this->transactions ) === 0 && count( $this->selections ) === 0;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): mixed {
		return [
			'start' => $this->getStart(),
			'transactions' => $this->getTransactions(),
			'selections' => $this->getSelections(),
			'stores' => $this->getStores()
		];
	}

	/**
	 * @param array $transactions
	 * @return Transaction[]
	 */
	private function normalizeTransactions( array $transactions ): array {
		$normalized = [];
		$lastInfo = null;
		foreach ( $transactions as $transaction ) {
			if ( $transaction instanceof Transaction ) {
				$normalized[] = $transaction;
				continue;
			}

			if ( is_string( $transaction ) ) {
				if ( !$lastInfo ) {
					continue;
				}
				$insertion = str_split( $transaction );
				$transaction = new Transaction( [
					[ 'type' => 'retain', 'length' => $lastInfo['end'] ],
					[ 'type' => 'replace', 'remove' => [], 'insert' => $insertion ],
					[ 'type' => 'retain', 'length' => $lastInfo['docLength'] - $lastInfo['end'] ]
				], $lastInfo['author'] );
			} else {
				$hasAuthor = isset( $transaction['a'] );
				if ( !$hasAuthor && $lastInfo ) {
					$transaction['a'] = $lastInfo['author'];
				}
				$transaction = Transaction::fromMinified( $transaction );
			}
			$normalized[] = $transaction;
			$lastInfo = $this->getTransactionInfo( $transaction );
		}

		return $normalized;
	}

	/**
	 * @param Transaction $transaction
	 * @return array|null
	 */
	private function getTransactionInfo( Transaction $transaction ): ?array {
		$op0 = $transaction->getOperations()[0] ?? null;
		$op1 = $transaction->getOperations()[1] ?? null;
		$op2 = $transaction->getOperations()[2] ?? null;

		if ( $op0 && $op0['type'] === 'replace' && ( !$op1 || $op1['type'] === 'retain' ) && !$op2 ) {
			$replaceOp = $op0;
			$start = 0;
			$end = $start + count( $replaceOp['insert'] ?? [] );
			$docLength = $end;
		} elseif (
			$op0 && $op0['type'] === 'retain' && $op1 && $op1['type'] === 'replace' &&
			( !$op2 || $op2['type'] === 'retain' )
		) {
			$replaceOp = $op1;
			$start = $op0['length'];
			$end = $start + count( $replaceOp['insert'] ?? [] );
			$docLength = $end + ( $op2 ? $op2['length'] : 0 );
		} else {
			return null;
		}

		return [
			'start' => $start,
			'end' => $end,
			'docLength' => $docLength,
			'author' => $transaction->getAuthor()
		];
	}

}
