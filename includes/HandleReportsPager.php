<?php
namespace MediaWiki\Extension\Report;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use ReverseChronologicalPager;
use SpecialPage;

class HandleReportsPager extends ReverseChronologicalPager {
	/** @var array */
	private $conds;

	/**
	 * @param array $conds
	 */
	public function __construct( $conds ) {
		$this->conds = $conds;

		parent::__construct();
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		return [
			'tables' => 'report_reports',
			'fields' => [
				'report_id',
				'report_reason',
				'report_user',
				'report_revid',
				'report_timestamp'
			],
			'conds' => $this->conds
		];
	}

	/** @inheritDoc */
	public function getIndexField() {
		return 'report_timestamp';
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function formatRow( $row ) {
		$out = Html::openElement( 'tr' );
		$out .= Html::element(
			'td', [],
			wfTimestamp( TS_ISO_8601, $row->report_timestamp )
		);
		$out .= Html::rawElement( 'td', [], Html::element(
			'textarea',
			[
				'readonly' => '',
				'class' => 'mw-report-handling-textarea'
			],
			$row->report_reason
		) );
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $row->report_user );
		$out .= Html::rawElement( 'td', [], Html::element(
			'a',
			[
				'href' => $user->getUserPage()->getLocalURL(),
				'target' => '_new'
			],
			$user->getName()
		) );
		$out .= Html::rawElement( 'td', [], Html::element(
			'a',
			[
				'href' => SpecialPage::getTitleFor(
					'Diff', $row->report_revid )->getLocalURL(),
				'target' => '_new'
			],
			$row->report_revid
		) );
		$out .= Html::rawElement( 'td', [], Html::element(
			'a',
			// don't bother opening new tab, there are return links here
			[ 'href' => SpecialPage::getTitleFor(
				'HandleReports', $row->report_id )->getLocalURL() ],
			wfMessage( 'report-handling-view-report' )->text()
		) );
		$out .= Html::closeElement( 'tr' );

		return $out;
	}
}
