<?php
/**
 * A content object represents page content, e.g. the text to show on a page.
 * Content objects have no knowledge about how they relate to Wiki pages.
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
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 1.21
 *
 * @file
 * @ingroup Content
 *
 * @author Daniel Kinzler
 */

/**
 * Base implementation for content objects.
 *
 * @ingroup Content
 */
abstract class AbstractContent implements Content {
	/**
	 * Name of the content model this Content object represents.
	 * Use with CONTENT_MODEL_XXX constants
	 *
	 * @since 1.21
	 *
	 * @var string $model_id
	 */
	protected $model_id;

	/**
	 * @param string $modelId
	 *
	 * @since 1.21
	 */
	public function __construct( $modelId = null ) {
		$this->model_id = $modelId;
	}

	/**
	 * @since 1.21
	 *
	 * @see Content::getModel
	 */
	public function getModel() {
		return $this->model_id;
	}

	/**
	 * @since 1.21
	 *
	 * @param string $modelId The model to check
	 *
	 * @throws MWException If the provided ID is not the ID of the content model supported by this
	 * Content object.
	 */
	protected function checkModelID( $modelId ) {
		if ( $modelId !== $this->model_id ) {
			throw new MWException(
				"Bad content model: " .
				"expected {$this->model_id} " .
				"but got $modelId."
			);
		}
	}

	/**
	 * @since 1.21
	 *
	 * @see Content::getContentHandler
	 */
	public function getContentHandler() {
		return ContentHandler::getForContent( $this );
	}

	/**
	 * @since 1.21
	 *
	 * @see Content::getDefaultFormat
	 */
	public function getDefaultFormat() {
		return $this->getContentHandler()->getDefaultFormat();
	}

	/**
	 * @since 1.21
	 *
	 * @see Content::getSupportedFormats
	 */
	public function getSupportedFormats() {
		return $this->getContentHandler()->getSupportedFormats();
	}

	/**
	 * @since 1.21
	 *
	 * @param string $format
	 *
	 * @return bool
	 *
	 * @see Content::isSupportedFormat
	 */
	public function isSupportedFormat( $format ) {
		if ( !$format ) {
			return true; // this means "use the default"
		}

		return $this->getContentHandler()->isSupportedFormat( $format );
	}

	/**
	 * @since 1.21
	 *
	 * @param string $format The serialization format to check.
	 *
	 * @throws MWException If the format is not supported by this content handler.
	 */
	protected function checkFormat( $format ) {
		if ( !$this->isSupportedFormat( $format ) ) {
			throw new MWException(
				"Format $format is not supported for content model " .
				$this->getModel()
			);
		}
	}

	/**
	 * @since 1.21
	 *
	 * @param string $format
	 *
	 * @return string
	 *
	 * @see Content::serialize
	 */
	public function serialize( $format = null ) {
		return $this->getContentHandler()->serializeContent( $this, $format );
	}

	/**
	 * @since 1.21
	 *
	 * @return bool
	 *
	 * @see Content::isEmpty
	 */
	public function isEmpty() {
		return $this->getSize() === 0;
	}

	/**
	 * Subclasses may override this to implement (light weight) validation.
	 *
	 * @since 1.21
	 *
	 * @return bool Always true.
	 *
	 * @see Content::isValid
	 */
	public function isValid() {
		return true;
	}

	/**
	 * @since 1.21
	 *
	 * @param Content $that
	 *
	 * @return bool
	 *
	 * @see Content::equals
	 */
	public function equals( Content $that = null ) {
		if ( is_null( $that ) ) {
			return false;
		}

		if ( $that === $this ) {
			return true;
		}

		if ( $that->getModel() !== $this->getModel() ) {
			return false;
		}

		return $this->getNativeData() === $that->getNativeData();
	}

	/**
	 * Returns a list of DataUpdate objects for recording information about this
	 * Content in some secondary data store.
	 *
	 * This default implementation calls
	 * $this->getParserOutput( $content, $title, null, null, false ),
	 * and then calls getSecondaryDataUpdates( $title, $recursive ) on the
	 * resulting ParserOutput object.
	 *
	 * Subclasses may override this to determine the secondary data updates more
	 * efficiently, preferably without the need to generate a parser output object.
	 *
	 * @since 1.21
	 *
	 * @param Title $title
	 * @param Content $old
	 * @param bool $recursive
	 * @param ParserOutput $parserOutput
	 *
	 * @return DataUpdate[]
	 *
	 * @see Content::getSecondaryDataUpdates()
	 */
	public function getSecondaryDataUpdates( Title $title, Content $old = null,
		$recursive = true, ParserOutput $parserOutput = null ) {
		if ( $parserOutput === null ) {
			$parserOutput = $this->getParserOutput( $title, null, null, false );
		}

		return $parserOutput->getSecondaryDataUpdates( $title, $recursive );
	}

	/**
	 * @since 1.21
	 *
	 * @return Title[]|null
	 *
	 * @see Content::getRedirectChain
	 */
	public function getRedirectChain() {
		global $wgMaxRedirects;
		$title = $this->getRedirectTarget();
		if ( is_null( $title ) ) {
			return null;
		}
		// recursive check to follow double redirects
		$recurse = $wgMaxRedirects;
		$titles = array( $title );
		while ( --$recurse > 0 ) {
			if ( $title->isRedirect() ) {
				$page = WikiPage::factory( $title );
				$newtitle = $page->getRedirectTarget();
			} else {
				break;
			}
			// Redirects to some special pages are not permitted
			if ( $newtitle instanceof Title && $newtitle->isValidRedirectTarget() ) {
				// The new title passes the checks, so make that our current
				// title so that further recursion can be checked
				$title = $newtitle;
				$titles[] = $newtitle;
			} else {
				break;
			}
		}

		return $titles;
	}

	/**
	 * Subclasses that implement redirects should override this.
	 *
	 * @since 1.21
	 *
	 * @return null
	 *
	 * @see Content::getRedirectTarget
	 */
	public function getRedirectTarget() {
		return null;
	}

	/**
	 * @note Migrated here from Title::newFromRedirectRecurse.
	 *
	 * @since 1.21
	 *
	 * @return Title|null
	 *
	 * @see Content::getUltimateRedirectTarget
	 */
	public function getUltimateRedirectTarget() {
		$titles = $this->getRedirectChain();

		return $titles ? array_pop( $titles ) : null;
	}

	/**
	 * @since 1.21
	 *
	 * @return bool
	 *
	 * @see Content::isRedirect
	 */
	public function isRedirect() {
		return $this->getRedirectTarget() !== null;
	}

	/**
	 * This default implementation always returns $this.
	 * Subclasses that implement redirects should override this.
	 *
	 * @since 1.21
	 *
	 * @param Title $target
	 *
	 * @return Content $this
	 *
	 * @see Content::updateRedirect
	 */
	public function updateRedirect( Title $target ) {
		return $this;
	}

	/**
	 * @since 1.21
	 *
	 * @return null
	 *
	 * @see Content::getSection
	 */
	public function getSection( $sectionId ) {
		return null;
	}

	/**
	 * @since 1.21
	 *
	 * @return null
	 *
	 * @see Content::replaceSection
	 */
	public function replaceSection( $section, Content $with, $sectionTitle = '' ) {
		return null;
	}

	/**
	 * @since 1.21
	 *
	 * @return Content $this
	 *
	 * @see Content::preSaveTransform
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		return $this;
	}

	/**
	 * @since 1.21
	 *
	 * @return Content $this
	 *
	 * @see Content::addSectionHeader
	 */
	public function addSectionHeader( $header ) {
		return $this;
	}

	/**
	 * @since 1.21
	 *
	 * @return Content $this
	 *
	 * @see Content::preloadTransform
	 */
	public function preloadTransform( Title $title, ParserOptions $popts, $params = array() ) {
		return $this;
	}

	/**
	 * @since 1.21
	 *
	 * @return Status
	 *
	 * @see Content::prepareSave
	 */
	public function prepareSave( WikiPage $page, $flags, $baseRevId, User $user ) {
		if ( $this->isValid() ) {
			return Status::newGood();
		} else {
			return Status::newFatal( "invalid-content-data" );
		}
	}

	/**
	 * @since 1.21
	 *
	 * @param WikiPage $page
	 * @param ParserOutput $parserOutput
	 *
	 * @return LinksDeletionUpdate[]
	 *
	 * @see Content::getDeletionUpdates
	 */
	public function getDeletionUpdates( WikiPage $page, ParserOutput $parserOutput = null ) {
		return array(
			new LinksDeletionUpdate( $page ),
		);
	}

	/**
	 * This default implementation always returns false. Subclasses may override
	 * this to supply matching logic.
	 *
	 * @since 1.21
	 *
	 * @param MagicWord $word
	 *
	 * @return bool Always false.
	 *
	 * @see Content::matchMagicWord
	 */
	public function matchMagicWord( MagicWord $word ) {
		return false;
	}

	/**
	 * This base implementation calls the hook ConvertContent to enable custom conversions.
	 * Subclasses may override this to implement conversion for "their" content model.
	 *
	 * @param string $toModel
	 * @param string $lossy
	 *
	 * @return Content|bool
	 *
	 * @see Content::convert()
	 */
	public function convert( $toModel, $lossy = '' ) {
		if ( $this->getModel() === $toModel ) {
			//nothing to do, shorten out.
			return $this;
		}

		$lossy = ( $lossy === 'lossy' ); // string flag, convert to boolean for convenience
		$result = false;

		wfRunHooks( 'ConvertContent', array( $this, $toModel, $lossy, &$result ) );

		return $result;
	}

	/**
	 * Returns a ParserOutput object containing information derived from this content.
	 * Most importantly, unless $generateHtml was false, the return value contains an
	 * HTML representation of the content.
	 *
	 * Subclasses that want to control the parser output may override this, but it is
	 * preferred to override fillParserOutput() instead.
	 *
	 * Subclasses that override getParserOutput() itself should take care to call the
	 * ContentGetParserOutput hook.
	 *
	 * @since 1.24
	 *
	 * @param Title $title Context title for parsing
	 * @param int|null $revId Revision ID (for {{REVISIONID}})
	 * @param ParserOptions|null $options Parser options
	 * @param bool $generateHtml Whether or not to generate HTML
	 *
	 * @return ParserOutput Containing information derived from this content.
	 */
	public function getParserOutput( Title $title, $revId = null,
		ParserOptions $options = null, $generateHtml = true
	) {
		if ( $options === null ) {
			$options = $this->getContentHandler()->makeParserOptions( 'canonical' );
		}

		$po = new ParserOutput();

		if ( wfRunHooks( 'ContentGetParserOutput',
			array( $this, $title, $revId, $options, $generateHtml, &$po ) ) ) {

			$this->fillParserOutput( $title, $revId, $options, $generateHtml, $po );
		}

		return $po;
	}

	/**
	 * Fills the provided ParserOutput with information derived from the content.
	 * Unless $generateHtml was false, this includes an HTML representation of the content.
	 *
	 * This is called by getParserOutput() after consulting the ContentGetParserOutput hook.
	 * Subclasses are expected to override this method (or getParserOutput(), if need be).
	 * Subclasses of TextContent should generally override getHtml() instead.
	 *
	 * This placeholder implementation always throws an exception.
	 *
	 * @since 1.24
	 *
	 * @param Title $title Context title for parsing
	 * @param int|null $revId Revision ID (for {{REVISIONID}})
	 * @param ParserOptions|null $options Parser options
	 * @param bool $generateHtml Whether or not to generate HTML
	 * @param ParserOutput &$output The output object to fill (reference).
	 *
	 * @throws MWException
	 */
	protected function fillParserOutput( Title $title, $revId,
		ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		// Don't make abstract, so subclasses that override getParserOutput() directly don't fail.
		throw new MWException( 'Subclasses of AbstractContent must override fillParserOutput!' );
	}
}
