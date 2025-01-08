<?php

namespace BlueSpice\QrCode\DynamicFileDispatcher;

use BlueSpice\DynamicFileDispatcher\File;
use BlueSpice\DynamicFileDispatcher\Module;
use BlueSpice\DynamicFileDispatcher\Params;

class QrCode extends Module {

	public const MODULE_NAME = 'qrcode';
	public const PAGENAME = 'pagename';
	public const QUERY = 'query';
	public const SIZE = 'size';

	/**
	 * @return File
	 */
	public function getFile() {
		return new QrCodeImage( $this );
	}

	/**
	 *
	 * @return array
	 */
	public function getParamDefinition() {
		return array_merge( parent::getParamDefinition(),
			[
				static::PAGENAME => [
					Params::PARAM_TYPE => Params::TYPE_STRING,
					Params::PARAM_DEFAULT => '',
				],
				static::QUERY => [
					Params::PARAM_TYPE => Params::TYPE_STRING,
					Params::PARAM_DEFAULT => '',
				],
				static::SIZE => [
					Params::PARAM_TYPE => Params::TYPE_INT,
					Params::PARAM_DEFAULT => 120,
				]
			]
		);
	}

}
