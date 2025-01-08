<?php

namespace MediaWiki\Extension\CollabPads\Backend;

use MediaWiki\Extension\CollabPads\Backend\Model\Author;
use MediaWiki\Extension\CollabPads\Backend\Model\Change;
use MediaWiki\Extension\CollabPads\Backend\Model\Store;

interface ICollabSessionDAO {

	/**
	 * @param string $wikiScriptPath
	 * @param string $pageTitle
	 * @param int $pageNamespace
	 * @param int $ownerId
	 * @return int Session ID
	 */
	public function setNewSession( string $wikiScriptPath, string $pageTitle, int $pageNamespace, int $ownerId );

	/**
	 * @param int $sessionId
	 * @param int $authorId
	 * @param string $authorName
	 * @param string $authorColor
	 * @param bool $authorStatus
	 * @param int $connectionId
	 */
	public function setNewAuthorInSession(
		int $sessionId, int $authorId, string $authorName,
		string $authorColor, bool $authorStatus, int $connectionId
	);

	/**
	 * @param int $sessionId
	 */
	public function deleteSession( int $sessionId );

	/**
	 * @param int $sessionId
	 * @param int $authorId
	 * @return bool
	 */
	public function isAuthorInSession( int $sessionId, int $authorId ): bool;

	/**
	 * @param int $sessionId
	 * @param int $authorId
	 * @param string $authorData
	 * @param string $authorValue
	 */
	public function changeAuthorDataInSession( int $sessionId, int $authorId, string $authorData, string $authorValue );

	/**
	 * @param int $sessionId
	 * @param int $authorId
	 * @param int $connectionId
	 */
	public function activateAuthor( int $sessionId, int $authorId, int $connectionId );

	/**
	 * @param int $sessionId
	 * @param bool $authorActive
	 * @param int $authorId
	 */
	public function deactivateAuthor( int $sessionId, bool $authorActive, int $authorId );

	/**
	 * @param int $sessionId
	 * @return array
	 */
	public function getAllAuthorsFromSession( int $sessionId );

	/**
	 * @param int $sessionId
	 * @param int $authorId
	 * @return array
	 */
	public function getAuthorInSession( int $sessionId, int $authorId );

	/**
	 * @param int $sessionId
	 * @return array
	 */
	public function getFullHistoryFromSession( int $sessionId );

	/**
	 * @param int $sessionId
	 * @return array
	 */
	public function getFullStoresFromSession( int $sessionId );

	/**
	 * @param int $sessionId
	 * @return int
	 */
	public function getSessionOwner( int $sessionId );

	/**
	 * @param int $sessionId
	 * @return array
	 */
	public function getActiveConnections( int $sessionId );

	/**
	 * @param string $wikiScriptPath
	 * @param string $pageTitle
	 * @param int $pageNamespace
	 * @return array
	 */
	public function getSessionByName( string $wikiScriptPath, string $pageTitle, int $pageNamespace );

	/**
	 * @return void
	 */
	public function cleanConnections();

	/**
	 * @param int $sessionId
	 * @param Author $author
	 * @return Change|null
	 */
	public function getAuthorContinueBase( int $sessionId, Author $author ): ?Change;

	/**
	 * @param int $sessionId
	 * @param Author $author
	 * @return int
	 */
	public function getAuthorRejections( int $sessionId, Author $author ): int;

	/**
	 * @param int $sessionId
	 * @return array
	 */
	public function getFullSelectionsFromSession( int $sessionId ): array;

	/**
	 * @param int $sessionId
	 * @return Change
	 */
	public function getChange( int $sessionId ): Change;

	/**
	 * @param int $sessionId
	 * @param Change $change
	 * @return mixed
	 */
	public function replaceHistory( int $sessionId, Change $change );
}
