<?php
namespace MediaWiki\Extension\Report;

use Html;
use MediaWiki\MediaWikiServices;
use SpecialPage;

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
	 * @param int $revRecord
	 * @param array &$links
	 * @param int $oldRevRecord
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
	 * @param User $user
	 * @return string
	 */
	protected static function generateReportElement( $id, $user ) {
		$dbr = wfGetDB( DB_REPLICA );
		if ( $dbr->selectRow( 'report_reports', [ 'report_id' ], [
			'report_revid' => $id,
			'report_user' => $user->getId()
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
					'href' => SpecialPage::getTitleFor( 'Report', $id )->getLocalURL(),
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
		$dbr = wfGetDB( DB_REPLICA );
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
