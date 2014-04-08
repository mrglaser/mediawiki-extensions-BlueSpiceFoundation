<?php
/**
 * This class contains helpful methods to find and process tags within a text (string).
 *
 * @copyright Copyright (c) 2013, HalloWelt! Medienwerkstatt GmbH, All rights reserved.
 * @author Thomas Lorenz, Robert Vogel
 * @version 0.2.0 beta

 */
class BsTagFinder {

	/**
	 * Searches s string for occurence of given tags and returns array of tags with some meta information
	 * @param string $sText Some kind of text with containing xml formatted string. For example:
	 * ...text...<div>Some Text <bookmeta subtitle="Some subtitle" classified="false">There is <b>Text</b> with markup</bookmeta> Again some Text <bookmeta /> and <sometag /></div>...text...
	 * @param array $aTagnames Array of tagnames to be searched for. For example:
	 * array( 'bookmeta', 'sometag', ... );
	 * @return array Multidimensional array with found tags and their data or empty array if tag was not found. For example:
	 * array( array( 'tagname'    => 'bookmeta',
	 *				 'isempty'    => false,
	 *				 'attributes' => array( 'subtitle'  => 'Some subtitle',
	 *									    'classified => 'false' ),
	 *				 'content     => 'There is <b>Text</b> with markup'
	 *				),
	 *		 array( 'tagname' => 'bookmeta', ...),
	 *		 array( 'tagname' => 'sometag', ...),
	 *		 ...
	 * );
	 */
	public static function find( &$sText, $aTagnames ) {
		wfProfileIn( 'BS::'.__METHOD__ );
		wfSuppressWarnings();
		$aResult = array();
		
		$sXML = '<xml xmlns:bs="http://www.blue-spice.org/XML/Schema-2011-09">'.$sText.'</xml>';
		$sXML = UtfNormal::cleanUp($sXML);

		$oDOMDoc = new DOMDocument();
		$oDOMDoc->recover = true;
		$oDOMDoc->loadHTML( $sXML ); //Formerly was loadXML but that caused a 
		//lot of warnings. Also the input is propbably more HTML than XML.

		foreach( $aTagnames as $aTagname ) {
			$oElements = $oDOMDoc->getElementsByTagName( $aTagname );
			if( $oElements->length > 0 ) {
				foreach( $oElements as $oElement ) {
					$aTag = array();
					$aTag['name']    = $oElement->nodeName;
					$aTag['content'] = trim( $oElement->textContent );
					$aTag['isempty'] = !$oElement->hasChildNodes();

					if( $oElement->hasAttributes() ) {
						$aTag['attributes'] = array();
						foreach( $oElement->attributes as $oAttribute) {
							$aTag['attributes'][ $oAttribute->name ] = $oAttribute->value;
						}
					}

					$aResult[] = $aTag;
				}
			}
		}
		wfRestoreWarnings();
		wfProfileOut( 'BS::'.__METHOD__ );
		return $aResult;
	}
}