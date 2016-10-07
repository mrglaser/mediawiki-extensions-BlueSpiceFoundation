<?php
/**
 * BSEntity base class for BlueSpice
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
 * This file is part of BlueSpice for MediaWiki
 * For further information visit http://bluespice.com
 *
 * @author     Patric Wirth <wirth@hallowelt.com>
 * @version    2.27.0
 * @package    BlueSpiceFoundation
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License v2 or later
 * @filesource
 */
class BSEntity implements JsonSerializable {
	const NS = '';
	protected static $aEntitiesByID = array();
	protected $bUnsavedChanges = true;

	/**
	 * @var BSEntityConfig
	 */
	protected $oConfig = null;

	protected $iID = 0;
	protected $iOwnerID = 0;
	protected $sType = '';

	protected function __construct( stdClass $oStdClass, BSEntityConfig $oConfig ) {
		$this->oConfig = $oConfig;
		if( !empty($oStdClass->id) ) {
			$this->iID = (int) $oStdClass->id;
			$this->bUnsavedChanges = false;
		}
		if( !empty($oStdClass->ownerid) ) {
			$this->iOwnerID = $oStdClass->ownerid;
		}
		if( !empty($oStdClass->type) ) {
			$this->sType = $oStdClass->type;
		}
	}

	protected static function factory( $sType, $oData ) {
		$oConfig = BSEntityConfig::factory( $sType );
		if( !$oConfig instanceof BSEntityConfig ) {
			//TODO: Return a DummyEntity instead of null.
			return null;
		}

		$sEntity = $oConfig->get( 'EntityClass' );
		$oInstance = new $sEntity( $oData, $oConfig );
		return $oInstance;
	}

	protected function generateID() {
		//this is the case if the current BSEntity is new (no Title created yet)
		//Get the page_title of the last created title in entity namespace and
		//add +1. Entities are stored like: MYEntityNamespace:1,
		//MYEntityNamespace:2, MYEntityNamespace:3
		if ( (int) $this->getID() === 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->selectRow(
				'page',
				'page_title',
				array( 'page_namespace' => static::NS ),
				__METHOD__,
				array( 'ORDER BY' => 'page_id DESC' )
			);

			if ( $res ) {
				$this->iID = (int) $res->page_title + 1;
			} else {
				$this->iID = 1;
			}
		}
	}

	/**
	 * Get BSEntity by BSEntityContent Object, wrapper for newFromObject
	 * @param BSEntityContent $sContent
	 * @return BSEntity
	 */
	public static function newFromContent( BSEntityContent $sContent ) {
		$aContent = $sContent->getJsonData();
		if ( empty($aContent) ) {
			return null;
		}

		return static::newFromObject( (object) $aContent );
	}

	/**
	 * Get BSEntity by Json Object
	 * @param Object $oObject
	 * @return BSEntity
	 */
	public static function newFromObject( $oObject ) {
		if( !is_object($oObject) ) {
			return null;
		}

		if( empty($oObject->type) ) {
			//$oObject->type = BSEntityRegistry::getDefaultHandlerType();
			return null;
		}
		if( !BSEntityRegistry::isRegisteredType($oObject->type) ) {
			return null;
		}

		if( !empty($oObject->id) && (int) $oObject->id !== 0 ) {
			return static::newFromID( $oObject->id );
		}

		$oInstance = static::factory(
			$oObject->type,
			$oObject
		);
		return static::appendCache( $oInstance );
	}

	/**
	 * Gets the related config object
	 * @return BSEntityConfig
	 */
	public function getConfig() {
		return $this->oConfig;
	}

	/**
	 * Gets the related Title object by ID
	 * @return Title
	 */
	public static function getTitleFor( $iID ) {
		return Title::newFromText( $iID, static::NS );
	}

	/**
	 * Gets the related Title object
	 * @return Title
	 */
	public function getTitle() {
		return static::getTitleFor( $this->iID );
	}

	public function getTimestampTouched() {
		return $this->getTitle()->getTouched();
	}

	public function getTimestampCreated() {
		return $this->getTitle()->getEarliestRevTime();
	}

	/**
	 * Get BSEntity from ID, wrapper for newFromTitle
	 * @param int $iID
	 * @param boolean $bForceReload
	 * @return BSEntity | null
	 */
	public static function newFromID( $iID, $bForceReload = false ) {
		if ( !is_numeric( $iID ) ) {
			return null;
		}
		$iID = (int) $iID;

		if ( !$bForceReload && static::hasCacheEntry( $iID ) ) {
			return static::getInstanceFromCacheByID( $iID );
		}

		$oTitle = static::getTitleFor( $iID );

		if( is_null($oTitle) || !$oTitle->exists() ) {
			return null;
		}

		$sText = BsPageContentProvider::getInstance()->getContentFromTitle(
			$oTitle
		);

		$oBSEntityContent = new JsonContent( $sText );
		$oData = (object) $oBSEntityContent->getJsonData();

		if( empty($oData->type) ) {
			return null;
			//$oData->type = BSEntityRegistry::getDefaultHandlerType();
		}
		if( !BSEntityRegistry::isRegisteredType($oData->type) ) {
			return null;
		}

		$oInstace = static::factory(
			$oData->type,
			$oData
		);
		return static::appendCache( $oInstace );
	}

	/**
	 * Main method for getting a BSEntity from a Title
	 * @param Title $oTitle
	 * @param boolean $bForceReload
	 * @return BSEntity
	 */
	public static function newFromTitle( Title $oTitle, $bForceReload = false ) {
		if ( is_null( $oTitle ) || $oTitle->getNamespace() !== static::NS ) {
			return null;
		}
		$iID = (int) $oTitle->getText();

		return static::newFromID( $iID, $bForceReload );
	}

	/**
	 * Saves the current BSEntity
	 * @return Status
	 */
	public function save( User $oUser = null, $aOptions = array() ) {
		if( !$oUser instanceof User ) {
			return Status::newFatal( 'No User' );
		}
		if( !$this->hasUnsavedChanges() ) {
			return Status::newGood('success');
		}
		if( empty($this->getID()) ) {
			$this->generateID();
		}
		if( empty($this->getID()) ) {
			return Status::newFatal( 'No ID generated' );
		}
		if( empty($this->getOwnerID()) ) {
			$this->setOwnerID( (int) $oUser->getId() );
		}
		$sType = $this->getType();
		if( empty($sType) ) {
			return Status::newFatal('Related Type error');
		}

		$oTitle = $this->getTitle();
		if ( is_null( $oTitle ) ) {
			return Status::newFatal('Related Title error');
		}

		$oWikiPage = WikiPage::factory( $oTitle );
		$sContentClass = $this->getConfig()->get('ContentClass');
		try {
			$oStatus = $oWikiPage->doEditContent(
				new $sContentClass( json_encode( $this ) ),
				"",
				0,
				0,
				$oUser,
				null
			);
		} catch( Exception $e ) {
			//Something probalby breaks json
			return Status::newFatal( $e->getMessage() );
		}
		//TODO: check why this is not good
		if ( !$oStatus->isOK() ) {
			return $oStatus;
		}

		$this->setUnsavedChanges( false );

		wfRunHooks( 'BSEntitySaveComplete', array( $this, $oStatus, $oUser ) );

		$this->invalidateCache();
		return $oStatus;
	}

	/**
	 * Deletes the current BSEntity
	 * @return Status
	 */
	public function delete( User $oUser = null, $aOptions = array() ) {
		$oTitle = $this->getTitle();

		$oStatus = null;
		$oWikiPage = WikiPage::factory( $oTitle );

		wfRunHooks( 'BSEntityDelete', array( $this, $oStatus, $oUser ) );
		if( $oStatus instanceof Status && $oStatus->isOK() ) {
			return $oStatus;
		}

		try {
			$sError = '';
			$b = $oWikiPage->doDeleteArticle(
				'',
				false,
				0,
				true,
				$sError,
				$oUser
			);
		} catch( Exception $e ) {
			error_log( $e->getMessage() );
			return Status::newFatal( $e->getMessage() );
		}

		$oStatus = Status::newGood( 'success' );
		wfRunHooks( 'BSEntityDeleteComplete', array( $this, $oStatus ) );
		if( !$oStatus->isOK() ) {
			return $oStatus;
		}

		$this->invalidateCache();
		return $oStatus;
	}

	/**
	 * Gets the BSEntity attributes formated for the api
	 * @return object
	 */
	public function getFullData( $a = array() ) {
		return array_merge(
			$a,
			array(
				'id' => $this->getID(),
				'ownerid' => $this->getOwnerID(),
				'type' => $this->getType(),
			)
		);
	}

	/**
	 * Adds a BSEntity to the cache
	 * @param BSEntity $oInstance
	 * @return BSEntity
	 */
	protected static function appendCache( BSEntity &$oInstance ) {
		if( static::hasCacheEntry($oInstance->getID()) ) {
			return $oInstance;
		}
		static::$aEntitiesByID[$oInstance->getID()] = $oInstance;
		return $oInstance;
	}

	/**
	 * Removes a BSEntity from the cache if it's in
	 * @param BSEntity $oInstance
	 * @return BSEntity
	 */
	protected static function detachCache( BSEntity &$oInstance ) {
		if( !static::hasCacheEntry($oInstance->getID()) ) {
			return $oInstance;
		}
		unset( static::$aEntitiesByID[$oInstance->getID()] );
		return $oInstance;
	}

	/**
	 * Gets a instance of the BSEntity from the cache by ID
	 * @param int $iID
	 * @return BSEntity
	 */
	protected static function getInstanceFromCacheByID( $iID ) {
		if( !static::hasCacheEntry( $iID ) ) {
			return null;
		}
		return static::$aEntitiesByID[(int) $iID];
	}

	protected static function hasCacheEntry( $iID ) {
		return isset( static::$aEntitiesByID[(int) $iID] );
	}

	/**
	 * Checks, if the current BSEntity exists in the Wiki
	 * @return boolean
	 */
	public function exists() {
		$bExists = $this->getID() > 0 ? true : false;
		if ( !$bExists ) {
			return false;
		}
		$oTitle = $this->getTitle();
		if ( is_null( $oTitle ) ) {
			return false;
		}
		return $oTitle->exists();
	}

	/**
	 * Checks if there are unsaved changes
	 * @return boolean
	 */
	public function hasUnsavedChanges() {
		return (bool) $this->bUnsavedChanges;
	}

	/**
	 * Returns the current id for the BSEntity
	 * @return int
	 */
	public function getID() {
		return (int) $this->iID;
	}

	/**
	 * Returns the current user id for the BSEntity
	 * @return int
	 */
	public function getOwnerID() {
		return (int) $this->iOwnerID;
	}

	/**
	 * Returns the current type for the BSSocialEntity
	 * @return String
	 */
	public function getType() {
		return $this->sType;
	}

	/**
	 * Sets the current BSEntity to an unsaved changes mode, refreshes cache
	 * @param String $bStatus
	 * @return BSEntity
	 */
	public function setUnsavedChanges( $bStatus = true ) {
		$this->bUnsavedChanges = (bool) $bStatus;
		return $this;
	}

	/**
	 * Sets the current user ID
	 * @param int
	 * @return BSEntity
	 */
	public function setOwnerID( $iOwnerID ) {
		return $this->setUnsavedChanges(
			$this->iOwnerID = (int) $iOwnerID
		);
	}

	/**
	 * Subclass needs to return the current BSEntity as a Json encoded
	 * string
	 * @deprecated since 2.27.0 - Use json_encode( $oInstance ) instead
	 * @return stdObject - Subclass needs to return encoded string!
	 */
	public function toJson() {
		return json_encode( (object) static::getFullData() );
	}

	/**
	 * Returns a json serializeable stdClass
	 * @return stdClass
	 */
	public function jsonSerialize() {
		return (object) static::getFullData();
	}

	/**
	 * @param stdClass $oData
	 */
	public function setValuesByObject( stdClass $oData ) {
		if( isset($oData->ownerid) ) {
			$this->setOwnerID( $oData->ownerid );
		}
	}

	/**
	 * Checks if the given User is the owner of this entity
	 * @param User $oUser
	 * @return boolean
	 */
	public function userIsOwner( User $oUser ) {
		if( $oUser->isAnon() || $this->getOwnerID() < 1) {
			return false;
		}
		return $oUser->getId() == $this->getOwnerID();
	}

	/**
	 * Invalidated the cache
	 * @return BSEntity
	 */
	public function invalidateCache() {
		$this->getTitle()->invalidateCache();
		static::detachCache( $this );
		return $this;
	}
}