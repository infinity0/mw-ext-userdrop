<?php
/** \file
* \brief Contains code for the UserDrop class (extends SpecialPage) and
* UserDropPager class (extends UsersPager).
*/

/**
* Class for displaying users and their contribution-related stats.
* Based on UsersPager (Special:Listusers)
*/
class UsersDropPager extends UsersPager {

	function __construct() {
		global $wgRequest;
		$wgRequest->setVal('creationSort', '1');
		$wgRequest->setVal('editsOnly', '0');

		$editsCap = $wgRequest->getVal('editsCap') + 0;
		if ($editsCap < 0) { $editsCap = 0; }
		$this->editsCap = $editsCap;

		parent::__construct();
	}

	function getTitle() {
		return Title::newFromText('Special:UserDrop');
	}

	function getQueryInfo() {
		$dbr = wfGetDB( DB_SLAVE );
		$conds = array();
		// Don't show hidden names
		$conds[] = 'ipb_deleted IS NULL OR ipb_deleted = 0';
		if( $this->requestedGroup != '' ) {
			$conds['ug_group'] = $this->requestedGroup;
			$useIndex = '';
		} else {
			$useIndex = $dbr->useIndexClause( $this->creationSort ? 'PRIMARY' : 'user_name');
		}
		if( $this->requestedUser != '' ) {
			# Sorted either by account creation or name
			if( $this->creationSort ) {
				$conds[] = 'user_id >= ' . User::idFromName( $this->requestedUser );
			} else {
				$conds[] = 'user_name >= ' . $dbr->addQuotes( $this->requestedUser );
			}
		}

		$conds[] = 'user_editcount <= ' . $this->editsCap;

		list ($user,$user_groups,$ipblocks) = $dbr->tableNamesN('user','user_groups','ipblocks');

		$query = array(
			'tables' => " $user $useIndex LEFT JOIN $user_groups ON user_id=ug_user
				LEFT JOIN $ipblocks ON user_id=ipb_user AND ipb_auto=0 ",
			'fields' => array(
				$this->creationSort ? 'MAX(user_name) AS user_name' : 'user_name',
				$this->creationSort ? 'user_id' : 'MAX(user_id) AS user_id',
				'MAX(user_editcount) AS edits',
				'COUNT(ug_group) AS numgroups',
				'MAX(ug_group) AS singlegroup',
				'MIN(user_registration) AS creation'),
			'options' => array('GROUP BY' => $this->creationSort ? 'user_id' : 'user_name'),
			'conds' => $conds
		);

		wfRunHooks( 'SpecialListusersQueryInfo', array( $this, &$query ) );
		return $query;
	}

	function formatRow( $row ) {
		global $wgLang;

		$userPage = Title::makeTitle( NS_USER, $row->user_name );
		$name = $this->getSkin()->makeLinkObj( $userPage, htmlspecialchars( $userPage->getText() ) );

		if( $row->numgroups > 1 || ( $this->requestedGroup && $row->numgroups == 1 ) ) {
			$list = array();
			foreach( self::getGroups( $row->user_id ) as $group )
				$list[] = self::buildGroupLink( $group );
			$groups = $wgLang->commaList( $list );
		} elseif( $row->numgroups == 1 ) {
			$groups = self::buildGroupLink( $row->singlegroup );
		} else {
			$groups = '';
		}

		$item = wfSpecialList( $name, $groups );
		$contribs = $this->getSkin()->makeKnownLinkObj(SpecialPage::getTitleFor( 'Contributions', $row->user_name), 'contribs');
		$checkusr = $this->getSkin()->link( Title::makeTitle(NS_SPECIAL, 'CheckUser'), 'checkuser', array(), array('user'=>$row->user_name), 'known' );
		$droplink = $this->getSkin()->link( Title::makeTitle(NS_SPECIAL, 'UserMerge'), 'DELETE', array(), array('deleteuser'=>'1','olduser'=>$row->user_name), 'known' );

		if ( true ) {
			$editCount = $wgLang->formatNum( $row->edits );
			$edits = ' [' . wfMsgExt( 'usereditcount', 'parsemag', $editCount ) . ']';
			$edits .= ' ('.$contribs.') ('.$checkusr.') ';
		}

		$created = '';
		# Some rows may be NULL
		if( $row->creation ) {
			$d = $wgLang->date( wfTimestamp( TS_MW, $row->creation ), true );
			$t = $wgLang->time( wfTimestamp( TS_MW, $row->creation ), true );
			$created = ' (' . wfMsgHtml( 'usercreated', $d, $t ) . ')';
			$created .= ' [ '.$droplink.' ] ';
		}

		wfRunHooks( 'SpecialListusersFormatRow', array( &$item, $row ) );
		return "<li>{$item}{$edits}{$created}</li>";
	}

	function getPageHeader( ) {
		global $wgScript, $wgRequest;
		$self = $this->getTitle();

		# Form tag
		$out  = Xml::openElement( 'form', array( 'method' => 'get', 'action' => $wgScript, 'id' => 'mw-listusers-form' ) ) .
			Xml::fieldset( wfMsg( 'listusers' ) ) .
			Html::Hidden( 'title', $self->getPrefixedDbKey() );

		# Username field
		$out .= Xml::label( wfMsg( 'listusersfrom' ), 'offset' ) . ' ' .
			Xml::input( 'username', 20, $this->requestedUser, array( 'id' => 'offset' ) ) . ' ';

		# Group drop-down list
		$out .= Xml::label( wfMsg( 'group' ), 'group' ) . ' ' .
			Xml::openElement('select',  array( 'name' => 'group', 'id' => 'group' ) ) .
			Xml::option( wfMsg( 'group-all' ), '' );
		foreach( $this->getAllGroups() as $group => $groupText )
			$out .= Xml::option( $groupText, $group, $group == $this->requestedGroup );
		$out .= Xml::closeElement( 'select' ) . '<br/>';
		$out .= Xml::label( wfMsg( 'userdrop-editscap' ), 'editsCap' ) . ' ' .
			Xml::input( 'editsCap', 2, $this->editsCap ) . ' ';
		$out .= '<br/>';

		wfRunHooks( 'SpecialListusersHeaderForm', array( $this, &$out ) );

		# Submit button and form bottom
		$out .= Html::Hidden( 'limit', $this->mLimit );
		$out .= Xml::submitButton( wfMsg( 'allpagessubmit' ) );
		wfRunHooks( 'SpecialListusersHeader', array( $this, &$out ) );
		$out .= Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' );

		return $out;
	}

}

///Special page class for the User Drop extension
/**
 * Special page that shows users to drop.
 *
 * @ingroup Extensions
 * @author Ximin Luo <infinity0@gmx.com>
 */
class UserDrop extends SpecialPage {
	function __construct() {
		parent::__construct( 'UserDrop', 'userdrop' );
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser;

		wfLoadExtensionMessages( 'UserDrop' );

		$this->setHeaders();

		if ( !$wgUser->isAllowed( 'userdrop' ) ) {
			$wgOut->permissionRequired( 'userdrop' );
			return;
		}

		// init variables
		$olduser_text = '';
		$newuser_text = '';
		$deleteUserCheck = false;
		$validNewUser = false;

		$pager = new UsersDropPager();

 		$wgOut->addHTML($pager->getPageHeader());
 		$wgOut->addHTML($pager->getNavigationBar());
 		$wgOut->addHTML('<ul>'. $pager->getBody() . '</ul>');
		//$wgOut->addHTML('<pre>'. $pager->extraDebugInfo .'</pre>');

	}

}
