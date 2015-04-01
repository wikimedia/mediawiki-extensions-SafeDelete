<?php

/*
 * Copyright (c) 2015 The MITRE Corporation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

class SafeDelete extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'SafeDelete' );
	}

	function execute( $par ) {

		$this->setHeaders();
		$this->outputHeader();

		if ( !$this->getUser()->isAllowed( 'delete' ) ) {
			$this->displayRestrictionError();
			return;
		}

		if ( $par === null ) {
			$this->getOutput()->addHTML(
				$this->msg( 'safedelete-missingpagename' ) );
			return;
		}

		if ( !isset( $GLOBALS['SafeDeleteNamespaces'] ) ) {
			$this->getOutput()->addHTML(
				$this->msg( 'safedelete-missingnamespaces' ) );
			return;
		}

		$title = Title::newFromURL( $par );
		$this->getSkin()->setRelevantTitle( $title );

		if ( class_exists ("SemanticTitle" ) ) {
			$displaytitle = SemanticTitle::getText( $title );
		} else {
			$displaytitle = $title->getPrefixedText();
		}
		$this->getOutput()->setPageTitle( $this->msg( 'safedelete-title',
			$displaytitle ) );

		$this->getOutput()->addBacklinkSubtitle( $title );

		$dbr = wfGetDB( DB_SLAVE );

		$queryLimit = 1000;

		$sql1 = $dbr->selectSQLText(
			array(
				'pagelinks',
				'page'
			),
			array(
				'page_namespace',
				'page_title'
			),
			array(
				'pl_namespace' => $title->getNamespace(),
				'pl_title' => $title->getText(),
				'page_namespace' => $GLOBALS['SafeDeleteNamespaces'],
				'pl_from=page_id'
			),
			__METHOD__,
			array(
				'DISTINCT',
				'LIMIT' => $queryLimit
			)
		);

		$sql2 = $dbr->selectSQLText(
			array(
				'redirect',
				'page'
			),
			array(
				'page_namespace',
				'page_title'
			),
			array(
				'rd_namespace' => $title->getNamespace(),
				'rd_title' => $title->getText(),
				'page_namespace' => $GLOBALS['SafeDeleteNamespaces'],
				'rd_from=page_id'
			),
			__METHOD__,
			array(
				'DISTINCT',
				'LIMIT' => $queryLimit
			)
		);

		$sql = $dbr->unionQueries( array( $sql1, $sql2 ), false );

		$rows = $dbr->query( $sql );

		if ( $rows->numRows() > 0 ) {

			$this->getOutput()->addHTML(
				$this->msg( 'safedelete-cannotdelete', $displaytitle ) );

			$this->getOutput()->addHTML( Html::element( 'br' ) );
			$this->getOutput()->addHTML( Html::openElement( 'ul' ) );
			foreach ( $rows as $row ) {
				$nt = Title::makeTitle( $row->page_namespace,
					$row->page_title );
				$link = Linker::linkKnown( $nt );
				$element = Xml::tags( 'li', null, "$link" );
				$this->getOutput()->addHTML( $element );
			}

			$this->getOutput()->addHTML( Html::closeElement( 'ul' ) );

		} else {

			$this->getOutput()->redirect(
				$title->getLocalURL( 'action=delete' ) );

		}

	}

	public static function checkLink( SkinTemplate &$sktemplate,
		array &$links ) {

		if ( isset( $GLOBALS['SafeDeleteNamespaces'] ) &&
			isset( $links['actions']['delete'] ) ) {

			$title = $sktemplate->getTitle();
			$pagename = $title->getPrefixedText();

			$sd = "Special:SafeDelete/";
			if ( substr( $pagename, 0, strlen( $sd ) ) === $sd ) {

				unset( $links['actions']['delete'] );

			} elseif
				( $title->inNamespaces( $GLOBALS['SafeDeleteNamespaces'] ) ) {

				$links['actions']['delete']['href'] =
					SpecialPage::getTitleFor( 'SafeDelete', $pagename )->
					getLocalURL();
			}

		}

		return true;

	}
}
