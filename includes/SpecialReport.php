<?php
namespace MediaWiki\Extension\Report;

use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialReport extends SpecialPage {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly RevisionLookup $revisionLookup,
	) {
		parent::__construct( 'Report' );
	}

	/**
	 * @param string $key
	 * @param null $par
	 */
	private function showError( string $key, $par = null ) {
		$out = $this->getOutput();
		$out->addHTML( Html::element(
			'p', [ 'class' => 'error' ],
			$par ? wfMessage( $key, $par )->text() :
			wfMessage( $key )->text()
		) );
	}

	/**
	 * @param string|null $par
	 * @return void
	 */
	public function execute( $par ) {
		$user = $this->getUser();
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage( 'report-title' )->escaped() );
		$out->addModules( 'ext.report' );
		$this->checkReadOnly();
		if ( $user->getBlock() || !$user->isAllowed( 'report' ) ) {
			$this->showError( 'report-error-missing-perms' );
			return;
		}
		if ( !ctype_digit( $par ) ) {
			$this->showError( 'report-error-invalid-revid', $par );
			return;
		}
		$rev = $this->revisionLookup->getRevisionById( (int)$par );
		if ( !$rev ) {
			$this->showError( 'report-error-invalid-revid', $par );
			return;
		}
		$dbr = $this->dbProvider->getReplicaDatabase();
		if ( $dbr->selectRow( 'report_reports', [ 'report_id' ], [
			'report_revid' => $rev->getId(),
			'report_user' => $user->getId()
		], __METHOD__ ) ) {
			$out->addWikiMsg( 'report-already-reported' );
			return;
		}
		$request = $this->getRequest();
		if ( $request->wasPosted() ) {
			return $this->onPost( $par, $out, $request, $user );
		}
		$out->setIndexPolicy( 'noindex' );
		$out->addWikiMsg( 'report-intro', $par );
		$out->addHTML( Html::openElement( 'form', [ 'method' => 'POST' ] ) );
		$out->addHTML( Html::hidden( 'revid', $par ) );
		$out->addHTML( Html::textarea( 'reason' ) );
		$out->addHTML( Html::hidden( 'token', $user->getEditToken() ) );
		$out->addHTML( Html::element(
			'button',
			[ 'type' => 'submit' ],
			wfMessage( 'report-submit' )->text()
		) );
		$out->addHTML( Html::closeElement( 'form' ) );
	}

	/**
	 * @param string|null $par
	 * @param OutputPage $out
	 * @param WebRequest $request
	 * @param User $user
	 */
	public function onPost( $par, $out, $request, $user ) {
		if ( !$user->matchEditToken( $request->getText( 'token' ) ) ) {
			$out->addWikiMsg( 'sessionfailure' );
			return;
		}
		if ( !$request->getText( 'reason' ) ) {
			$out->addHTML( Html::element(
				'p', [ 'class' => 'error ' ],
				wfMessage( 'report-error-missing-reason' )->text()
			) );
			return;
		}
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );
		$dbw->insert( 'report_reports', [
			'report_revid' => (int)$par,
			'report_reason' => $request->getText( 'reason' ),
			'report_user' => $user->getId(),
			'report_user_text' => $user->getName(),
			'report_timestamp' => wfTimestampNow()
		], __METHOD__ );
		$dbw->endAtomic( __METHOD__ );
		$out->addWikiMsg( 'report-success' );
		$out->addWikiMsg( 'returnto', '[[' . SpecialPage::getTitleFor( 'Diff', $par )->getPrefixedText() . ']]' );
	}

	/** @inheritDoc */
	public function getGroupName() {
		return 'wiki';
	}

}
