<?php

// SearchRealnames MediaWiki extension.
// Adds real names to Search results

// Copyright (C) 2009 - John Erling Blad.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

# Not a valid entry point, skip unless MEDIAWIKI is defined
if( !defined( 'MEDIAWIKI' ) ) {
	echo "SearchRealnames: This is an extension to the MediaWiki package and cannot be run standalone.\n";
	die( -1 );
}

#----------------------------------------------------------------------------
#    Extension initialization
#----------------------------------------------------------------------------

$wgSearchRealnamesVersion = '0.2';
$wgExtensionCredits['parserhook'][] = array(
	'name'=>'SearchRealnames',
	'version'=>$wgSearchRealnamesVersion,
	'author'=>'John Erling Blad',
	'url'=>'http://www.mediawiki.org/wiki/Extension:SearchRealnames',
	'description' => 'Adds real names to Search results'
);

$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['SearchRealnames'] = $dir . 'SearchRealnames.i18n.php';
$wgSearchRealnames = new SearchRealnames();

$wgHooks['SpecialSearchResults'][] = array( &$wgSearchRealnames, 'onSearchResults' );
$wgHooks['BeforePageDisplay'][] = array( &$wgSearchRealnames, 'onBeforePageDisplay' );

class SearchRealnames
{
	private $mUsers = array();

	# Scan the results
	function onSearchResults( $term, &$titleMatches, &$textMatches ) {
		foreach ($titleMatches as $key => $val) {
			if ($val instanceof ResultWrapper) {
				$p = $val;
				while( $row = $val->fetchObject() )
					if ($row->page_namespace == 2)
						$this->mUsers[$row->page_title]++;
				$val->rewind();
			}
		}
		foreach ($textMatches as $key => $val) {
			if ($val instanceof ResultWrapper) {
				$p = $val;
				while( $row = $p->fetchObject() )
					if ($row->page_namespace == 2)
						$this->mUsers[$row->page_title]++;
				$val->rewind();
			}
		}
		foreach ($this->mUsers as $key => $val) {
			$this->mUsers[$key] = $val = User::newFromName( $key );
		}
		return true;
	}

	# Replace callback
	private function onReplaceBeforePageDisplay( $m ) {
		global $wgSearchRealnamesInline;
		if ($this->mUsers[$m[3]] instanceof User) {
			$realName = htmlspecialchars( trim( $this->mUsers[$m[3]]->getRealname() ) );
			if ( $realName != "" ) {
				if ($wgSearchRealnamesInline)
					return $m[1] . wfMsg( 'search-realname-inline', $realName ) . $m[4];
				else
					return $m[1] . $m[2] . $m[3] . $m[4] . wfMsg( 'search-realname-append', $realName );
			}
		}
		return "$m[1]$m[2]$m[3]$m[4]";
	}

	# Replace text strings
	function onBeforePageDisplay( &$out, &$sk ) {
		global $wgTitle;
		if ( $wgTitle->getNamespace() >= 0)
			return true;
		if (!count($this->mUsers))
			return true;
		$text =& $out->mBodytext;
		$text = preg_replace_callback('/(<li><a\b[^>]*>)([^<:]*:)([^<]*)(<\\/a>)/', array( &$this, 'onReplaceBeforePageDisplay'), $text);
		return true;
	}

}