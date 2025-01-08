<?php

/**
 * Blog class for BSSocial
 *
 * add desc
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
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
 * @subpackage BlueSpiceSocialBlog
 * @copyright  Copyright (C) 2017 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 */
namespace BlueSpice\Social\Blog\Entity;

use BlueSpice\Social\Entity\Text;
use BlueSpice\Social\Parser\Input;
use BlueSpice\Social\Parser\Teaser;
use Message;
use ParserOutput;
use SpecialPage;
use Status;
use Title;
use User;

/**
 * Blog class for BSSocial extension
 * @package BlueSpiceSocial
 * @subpackage BlueSpiceSocialBlog
 */
class Blog extends Text {
	public const TYPE = 'blog';

	public const ATTR_BLOG_TITLE = 'blogtitle';
	public const ATTR_TEASER_TEXT = 'teasertext';
	public const ATTR_TEASER_TEXT_PARSED = 'teasertextparsed';

	/**
	 * @var ParserOutput|null
	 */
	protected $teaserParserOutput = null;

	/**
	 *
	 * @param string $attrName
	 * @param mixed|null $default
	 * @return mixed
	 */
	public function get( $attrName, $default = null ) {
		if ( $attrName === static::ATTR_TEASER_TEXT ) {
			if ( empty( $this->attributes[static::ATTR_TEASER_TEXT] ) ) {
				$parser = new Teaser();
				// In case if there is no teaser - it will be created from parsed text content

				// Attention!
				// All HTML tags are stripped after \BlueSpice\Social\Parser\Input::parse() method.
				// So, for example, to make links in teaser clickable -
				// - teaser text should be parsed by MediaWiki parser.
				$this->attributes[static::ATTR_TEASER_TEXT] = $parser->parse(
					$this->get( static::ATTR_PARSED_TEXT, '' )
				);
			}
		}

		if ( $attrName === static::ATTR_TEASER_TEXT_PARSED ) {
			if ( empty( $this->get( static::ATTR_TEASER_TEXT, '' ) ) ) {
				return '';
			}
			// To make links in teaser clickable we should pass it to MediaWiki parser
			// Even if teaser was created from parsed text content (which happens when no teaser was passed),
			// it still won't contain any HTML tags.
			if ( empty( $this->attributes[static::ATTR_TEASER_TEXT_PARSED] ) ) {
				$this->attributes[static::ATTR_TEASER_TEXT_PARSED] = $this->getTeaserParserOutput()->getText( [
					'enableSectionEditLinks' => false,
					'allowTOC' => false,
				] );
			}
		}

		return parent::get( $attrName, $default );
	}

	/**
	 * @return ParserOutput
	 */
	private function getTeaserParserOutput() {
		if ( isset( $this->teaserParserOutput ) && $this->teaserParserOutput !== null ) {
			return $this->teaserParserOutput;
		}

		$parser = $this->parserFactory->create();
		$this->teaserParserOutput = $parser->parse(
			html_entity_decode( $this->attributes[static::ATTR_TEASER_TEXT] ),
			$this->getTitle(),
			$this->getParserOptions()
		);
		return $this->teaserParserOutput;
	}

	/**
	 * Gets the attributes formated for the api
	 * @param array $a
	 * @return array
	 */
	public function getFullData( $a = [] ) {
		return parent::getFullData( array_merge(
			$a,
			[
				static::ATTR_BLOG_TITLE => $this->get(
					static::ATTR_BLOG_TITLE,
					''
				),
				static::ATTR_TEASER_TEXT => $this->get(
					static::ATTR_TEASER_TEXT,
					''
				),
				static::ATTR_TEASER_TEXT_PARSED => $this->get(
					static::ATTR_TEASER_TEXT_PARSED,
					''
				)
			]
		) );
	}

	/**
	 *
	 * @param \stdClass $o
	 */
	public function setValuesByObject( \stdClass $o ) {
		if ( !empty( $o->{static::ATTR_BLOG_TITLE} ) ) {
			$this->set(
				static::ATTR_BLOG_TITLE,
				$o->{static::ATTR_BLOG_TITLE}
			);
		}
		if ( isset( $o->{static::ATTR_TEASER_TEXT} ) ) {
			$this->set(
				static::ATTR_TEASER_TEXT,
				$o->{static::ATTR_TEASER_TEXT}
			);
		}
		parent::setValuesByObject( $o );
	}

	/**
	 *
	 * @param Message|null $msg
	 * @return Message
	 */
	public function getHeader( $msg = null ) {
		$msg = parent::getHeader( $msg );
		return $msg->params( [
			$this->get( static::ATTR_BLOG_TITLE, '' ),
		] );
	}

	/**
	 *
	 * @param User|null $user
	 * @param array $options
	 * @return Status
	 */
	public function save( User $user = null, $options = [] ) {
		$parser = new Input;
		$this->set( static::ATTR_BLOG_TITLE,
			$parser->parse( $this->get( static::ATTR_BLOG_TITLE, '' ) )
		);
		if ( empty( $this->get( static::ATTR_BLOG_TITLE, '' ) ) ) {
			return Status::newFatal( wfMessage(
				'bs-social-entity-fatalstatus-save-emptyfield',
				$this->getVarMessage( static::ATTR_BLOG_TITLE )->plain()
			) );
		}
		$teaser = new Teaser();
		$this->set( static::ATTR_TEASER_TEXT,
			$teaser->parse( $this->get( static::ATTR_PARSED_TEXT, '' ) )
		);

		$status = parent::save( $user, $options );
		if ( !$status->isOK() ) {
			return $status;
		}
		return $status;
	}

	/**
	 *
	 * @return Title
	 */
	public function getBackLinkTitle() {
		return SpecialPage::getTitleFor( 'Blog' );
	}
}
