<?php

namespace BlueSpice\Social\Blog\EntityConfig;

use BlueSpice\Social\Blog\Entity\GroupBlog as Entity;
use BlueSpice\Social\Data\Entity\Schema;
use MWStake\MediaWiki\Component\DataStore\FieldType;

class GroupBlog extends Blog {

	/**
	 *
	 * @return string
	 */
	protected function get_EntityClass() {
		return "\\BlueSpice\\Social\\Blog\\Entity\\GroupBlog";
	}

	/**
	 *
	 * @return array
	 */
	protected function get_ModuleScripts() {
		return array_merge( parent::get_ModuleScripts(), [
			'ext.bluespice.social.entity.groupblog'
		] );
	}

	/**
	 *
	 * @return string
	 */
	protected function get_TypeMessageKey() {
		return 'bs-socialblog-type-groupblog';
	}

	/**
	 *
	 * @return bool
	 */
	protected function get_EntityListSpecialBlogTypeAllowed() {
		return false;
	}

	/**
	 *
	 * @return array
	 */
	protected function get_AttributeDefinitions() {
		return array_merge(
			parent::get_AttributeDefinitions(),
			[
				Entity::ATTR_EDIT_GROUPS => [
					Schema::FILTERABLE => true,
					Schema::SORTABLE => true,
					Schema::TYPE => FieldType::LISTVALUE,
					Schema::INDEXABLE => true,
					Schema::STORABLE => true,
				],
				Entity::ATTR_READ_GROUPS => [
					Schema::FILTERABLE => true,
					Schema::SORTABLE => true,
					Schema::TYPE => FieldType::LISTVALUE,
					Schema::INDEXABLE => true,
					Schema::STORABLE => true,
				],
				Entity::ATTR_COMMENT_GROUPS => [
					Schema::FILTERABLE => true,
					Schema::SORTABLE => true,
					Schema::TYPE => FieldType::LISTVALUE,
					Schema::INDEXABLE => true,
					Schema::STORABLE => true,
				],
				Entity::ATTR_DELETE_GROUPS => [
					Schema::FILTERABLE => true,
					Schema::SORTABLE => true,
					Schema::TYPE => FieldType::LISTVALUE,
					Schema::INDEXABLE => true,
					Schema::STORABLE => true,
				],
			]
		);
	}

	/**
	 *
	 * @return string[]
	 */
	protected function get_ModuleEditScripts() {
		return array_merge( parent::get_ModuleEditScripts(), [
			'ext.bluespice.social.entity.editor.groupblog'
		] );
	}

	/**
	 *
	 * @return bool
	 */
	protected function get_IsSpawnable() {
		return false;
	}
}
