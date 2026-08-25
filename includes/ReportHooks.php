<?php
namespace MediaWiki\Extension\Report;

use MediaWiki\Html\Html;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;

class ReportHooks {

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$sql_dir = dirname( __DIR__ ) . '/sql';
		$updater->addExtensionTable(
			'report_reports',
			$sql_dir . '/table.sql'
		);
		return true;
	}

	/**
	 * @param RevisionRecord $revRecord
	 * @param array &$links
	 * @param RevisionRecord|null $oldRevRecord
	 * @param UserIdentity $userIdentity
	 */
	public static function insertReportLink( $revRecord, &$links, $oldRevRecord, $userIdentity ) {
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
		if ( $user->isAllowed( 'report' ) && !$user->getBlock() &&
		!$user->isAllowed( 'handle-reports' ) ) {
			$links[] = self::generateReportElement( $revRecord->getID(), $userIdentity );
		}
	}

	/**
	 * @param int $id
	 * @param UserIdentity $userIdentity
	 * @return string
	 */
	protected static function generateReportElement( $id, $userIdentity ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		if ( $dbr->selectRow( 'report_reports', [ 'report_id' ], [
			'report_revid' => $id,
			'report_user' => $userIdentity->getId()
		], __METHOD__ ) ) {
			return Html::element(
				'span', [ 'class' => 'mw-report-reported' ],
				wfMessage( 'report-reported' )->text()
			);
		} else {
			return Html::element(
				'a',
				[
					'class' => 'mw-report-report-link',
					'href' => SpecialPage::getTitleFor( 'Report', (string)$id )->getLocalURL(),
				],
				wfMessage( 'report-report' )->text()
			);
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @return bool
	 */
	public static function reportsAwaitingNotice( &$out, &$skin ) {
		$context = $out->getContext();
		if ( !$context->getUser()->isAllowed( 'handle-reports' ) ) {
			return true;
		}
		$title = $context->getTitle();
		if ( !( $title->isSpecial( 'Recentchanges' ) || $title->isSpecial( 'Watchlist' ) ) ) {
			return true;
		}
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		if ( ( $count = $dbr->selectRowCount( 'report_reports', '*', [
			'report_handled != 1',
		], __METHOD__ ) ) > 0 ) {
			$out->prependHtml( Html::rawElement(
				'div', [ 'id' => 'mw-report-reports-awaiting' ],
				wfMessage( 'report-reports-awaiting' )->rawParams( Html::rawElement(
					'a',
					[ 'href' => SpecialPage::getTitleFor( 'HandleReports' )->getLocalURL() ],
					wfMessage( 'report-reports-awaiting-linktext', $count )->parse()
				) )->params( $count )->parse()
			) );
			$out->addModules( 'ext.report' );
		}
		return true;
	}

}
