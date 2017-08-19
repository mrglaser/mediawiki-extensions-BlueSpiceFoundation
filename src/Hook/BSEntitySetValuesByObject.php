<?php
/**
 * Hook handler base class for BlueSpice hook BSEntitySetValuesByObject
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
 * For further information visit http://bluespice.com
 *
 * @author     Patric Wirth <wirth@hallowelt.com>
 * @package    BlueSpiceFoundation
 * @copyright  Copyright (C) 2017 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License v2 or later
 * @filesource
 */
namespace BlueSpice\Hook;
use BlueSpice\Hook;
use BlueSpice\Entity;

abstract class BSEntitySetValuesByObject extends Hook {
	/**
	 * The Entity which should store the values
	 * @var Entity
	 */
	protected $entity = null;

	/**
	 * An obect of values to store in the entity (object)[ key => mixed value ].
	 * @var \stdClass
	 */
	protected $data = null;

	/**
	 * Located in \BlueSpice\Entity::setValuesByObject. After the known values
	 * are stored in the entity
	 * @param Entity $entity
	 * @param \stdClass $data
	 * @return boolean
	 */
	public static function callback( $entity, &$data ) {
		$className = static::class;
		$hookHandler = new $className(
			null,
			null,
			$entity,
			$data
		);
		return $hookHandler->process();
	}

	/**
	 * @param \IContextSource $context
	 * @param \Config $config
	 * @param Entity $entity
	 * @param \stdClass $data
	 */
	public function __construct( $context, $config, $entity, &$data ) {
		parent::__construct( $context, $config );

		$this->entity = $entity;
		$this->data = &$data;
	}
}