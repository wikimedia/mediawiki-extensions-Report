<?php
namespace MediaWiki\Extension\Report;

use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;

class ReportHooks implements
	\MediaWiki\Actions\Hook\HistoryToolsHook,
	\MediaWiki\Diff\Hook\DiffToolsHook,
	\MediaWiki\Output\Hook\BeforePageDisplayHook
{
	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly UserFactory $userFactory,
	) {
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
		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
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
		$dbr = $this->dbProvider->getReplicaDatabase();
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
		$dbr = $this->dbProvider->getReplicaDatabase();
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
