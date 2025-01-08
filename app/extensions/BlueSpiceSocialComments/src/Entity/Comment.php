<?php

/**
 * BSSociaEntityComment class for BSSocial
 *
 * add desc
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit https://bluespice.com
 *
 * @author     Patric Wirth
 * @package    BlueSpiceSocial
 * @subpackage BSSocialMicroBlog
 * @copyright  Copyright (C) 2018 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 */
namespace BlueSpice\Social\Comments\Entity;

use BlueSpice\Data\Entity\IStore;
use BlueSpice\EntityConfig;
use BlueSpice\EntityFactory;
use BlueSpice\Social\Entity\Text;
use MediaWiki\MediaWikiServices;
use RequestContext;
use Status;
use User;

/**
 * Comment class for BSSocial extension
 * @package BlueSpiceSocial
 * @subpackage BSSocialMicroBlog
 */
class Comment extends Text {
	public const TYPE = 'comment';

	/**
	 * Returns the instance - Should not be used directly. Use mediawiki service
	 * 'BSEntityFactory' instead
	 * @param \stdClass $data
	 * @param EntityConfig $config
	 * @param IStore $store
	 * @param EntityFactory|null $entityFactory
	 * @return \static
	 */
	public static function newFromFactory( \stdClass $data, EntityConfig $config,
		IStore $store, EntityFactory $entityFactory = null ) {
		if ( !$entityFactory ) {
			$entityFactory = MediaWikiServices::getInstance()->getService(
				'BSEntityFactory'
			);
		}
		$parserFactory = MediaWikiServices::getInstance()->getParserFactory();
		$instance = new static( $data, $config, $entityFactory, $store, $parserFactory );
		// Dealing with currupted entities, whenever a proccess or - more likely
		// a human breaks stuff by deleting, moving, protecting... etc. source
		// titles
		if ( $instance->get( static::ATTR_PARENT_ID, 0 ) < 1 ) {
			return null;
		}
		if ( !$instance->getParent() ) {
			return null;
		}
		return $instance;
	}

	/**
	 * @param string $action
	 * @param User|null $user
	 * @return Status
	 */
	public function userCan( $action = 'read', User $user = null ) {
		if ( !$this->hasParent() ) {
			return Status::newFatal( wfMessage(
				'bs-social-entity-fatalstatus-permission-permissiondeniedusercan',
				$action
			) );
		}
		if ( !$user instanceof User ) {
			$user = RequestContext::getMain()->getUser();
		}
		$status = $this->getParent()->userCan( 'read', $user );
		if ( !$status->isOK() ) {
			return $status;
		}
		if ( $action === 'create' ) {
			$status = $this->getParent()->userCan( 'comment', $user );
			if ( !$status->isOK() ) {
				return $status;
			}
		}
		return parent::userCan( $action, $user );
	}

	/**
	 *
	 * @param User|null $user
	 * @param array $options
	 * @return Status
	 */
	public function save( User $user = null, $options = [] ) {
		if ( !$this->getParent() || !$this->getParent()->exists() ) {
			return Status::newFatal(
				'bs-socialcomments-entity-fatalstatus-save-emptyfield',
				$this->getVarMessage( static::ATTR_PARENT_ID )->plain()
			);
		}
		return parent::save( $user, $options );
	}

	/**
	 * Returns the Message object for the entity header
	 * @param Message|null $msg
	 * @return Message
	 */
	public function getHeader( $msg = null ) {
		$msg = parent::getHeader( $msg );
		if ( !$this->exists() ) {
			return $msg;
		}

		return $msg->params( [
			$this->getParent()->getTitle()->getFullText(),
			strip_tags( $this->getParent()->getHeader()->parse() )
		] );
	}
}
