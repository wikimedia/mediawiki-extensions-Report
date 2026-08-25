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

class ReportHooks implements
	\MediaWiki\Actions\Hook\HistoryToolsHook,
	\MediaWiki\Diff\Hook\DiffToolsHook,
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook,
	\MediaWiki\Output\Hook\BeforePageDisplayHook
{

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sql_dir = dirname( __DIR__ ) . '/sql';
		$updater->addExtensionTable(
			'report_reports',
			$sql_dir . '/table.sql'
		);
		return true;
	}

	/**
	 * @param RevisionRecord $revRecord
	 * @param string[] &$links Array of HTML links#
	 * @param RevisionRecord|null $prevRevRecord RevisionRecord object, next in line
	 *   in page history, or null
	 * @param UserIdentity $userIdentity Current user
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onHistoryTools( $revRecord, &$links, $prevRevRecord, $userIdentity ) {
		return $this->insertReportLink( $revRecord, $links, $userIdentity );
	}

	/**
	 * @param RevisionRecord $newRevRecord New revision
	 * @param string[] &$links Array of HTML links
	 * @param RevisionRecord|null $oldRevRecord Old revision (may be null)
	 * @param UserIdentity $userIdentity Current user
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onDiffTools( $newRevRecord, &$links, $oldRevRecord, $userIdentity ) {
		return $this->insertReportLink( $newRevRecord, $links, $userIdentity );
	}

	private function insertReportLink( RevisionRecord $revRecord, array &$links, UserIdentity $userIdentity ) {
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
		if ( $user->isAllowed( 'report' ) && !$user->getBlock() &&
		!$user->isAllowed( 'handle-reports' ) ) {
			$links[] = $this->generateReportElement( $revRecord->getID(), $userIdentity );
		}
	}

	/**
	 * @param int $id
	 * @param UserIdentity $userIdentity
	 * @return string
	 */
	private function generateReportElement( $id, $userIdentity ) {
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
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$context = $out->getContext();
		if ( !$context->getUser()->isAllowed( 'handle-reports' ) ) {
			return;
		}
		$title = $context->getTitle();
		if ( !( $title->isSpecial( 'Recentchanges' ) || $title->isSpecial( 'Watchlist' ) ) ) {
			return;
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
	}

}
