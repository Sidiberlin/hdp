<?php

namespace BlueSpice\Social\Blog\Entity;

use MediaWiki\MediaWikiServices;
use Status;
use Title;
use User;

class GroupBlog extends Blog {
	public const TYPE = 'groupblog';

	public const ATTR_EDIT_GROUPS = 'editgroups';
	public const ATTR_READ_GROUPS = 'readgroups';
	public const ATTR_COMMENT_GROUPS = 'commentgroups';
	public const ATTR_DELETE_GROUPS = 'deletegroups';

	/**
	 * Gets the attributes formated for the api
	 * @param array $a
	 * @return \stdClass
	 */
	public function getFullData( $a = [] ) {
		return parent::getFullData( array_merge(
			$a,
			[
				static::ATTR_EDIT_GROUPS => $this->get(
					static::ATTR_EDIT_GROUPS,
					[]
				),
				static::ATTR_READ_GROUPS => $this->get(
					static::ATTR_READ_GROUPS,
					[]
				),
				static::ATTR_COMMENT_GROUPS => $this->get(
					static::ATTR_COMMENT_GROUPS,
					[]
				),
				static::ATTR_DELETE_GROUPS => $this->get(
					static::ATTR_DELETE_GROUPS,
					[]
				),
			]
		) );
	}

	/**
	 *
	 * @param \stdClass $o
	 */
	public function setValuesByObject( \stdClass $o ) {
		if ( !empty( $o->{static::ATTR_EDIT_GROUPS} ) ) {
			$this->set(
				static::ATTR_EDIT_GROUPS,
				$o->{static::ATTR_EDIT_GROUPS}
			);
		}
		if ( isset( $o->{static::ATTR_READ_GROUPS} ) ) {
			$this->set(
				static::ATTR_READ_GROUPS,
				$o->{static::ATTR_READ_GROUPS}
			);
		}
		if ( isset( $o->{static::ATTR_COMMENT_GROUPS} ) ) {
			$this->set(
				static::ATTR_COMMENT_GROUPS,
				$o->{static::ATTR_COMMENT_GROUPS}
			);
		}
		if ( isset( $o->{static::ATTR_DELETE_GROUPS} ) ) {
			$this->set(
				static::ATTR_DELETE_GROUPS,
				$o->{static::ATTR_DELETE_GROUPS}
			);
		}
		parent::setValuesByObject( $o );
	}

	/**
	 *
	 * @param string $action
	 * @param User $user
	 * @param Title|null $title
	 * @return Status
	 */
	protected function checkPermission( $action, User $user, Title $title = null ) {
		$groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups( $user );
		if ( $action === 'create' || $action === 'edit' || $action === 'delete' ) {
			if ( empty( $this->get( static::ATTR_EDIT_GROUPS, [] ) ) ) {
				return parent::checkPermission( $action, $user, $title );
			}
			if ( array_intersect( $groups, $this->get( static::ATTR_EDIT_GROUPS ) ) ) {
				return Status::newGood( $this );
			}
			return Status::newFatal( 'bs-socialblog-groupblog-permission-error' );
		}

		if ( $action === 'read' ) {
			if ( empty( $this->get( static::ATTR_READ_GROUPS, [] ) ) ) {
				return parent::checkPermission( $action, $user, $title );
			}
			if ( array_intersect( $groups, $this->get( static::ATTR_READ_GROUPS ) ) ) {
				return Status::newGood( $this );
			}
			return Status::newFatal( 'bs-socialblog-groupblog-permission-error' );
		}

		if ( $action === 'deleteothers' ) {
			$permission = parent::checkPermission( $action, $user, $title );
			if ( $permission->isOK() ) {
				return $permission;
			} else {
				if ( array_intersect( $groups, $this->get( static::ATTR_DELETE_GROUPS ) ) ) {
					return Status::newGood( $this );
				}
				return Status::newFatal( 'bs-socialblog-groupblog-permission-error' );
			}
		}

		if ( $action === 'comment' ) {
			if ( empty( $this->get( static::ATTR_COMMENT_GROUPS, [] ) ) ) {
				return parent::checkPermission( $action, $user, $title );
			}
			if ( array_intersect( $groups, $this->get( static::ATTR_COMMENT_GROUPS ) ) ) {
				return Status::newGood( $this );
			}
			return Status::newFatal( 'bs-socialblog-groupblog-permission-error' );
		}

		return parent::checkPermission( $action, $user, $title );
	}
}
