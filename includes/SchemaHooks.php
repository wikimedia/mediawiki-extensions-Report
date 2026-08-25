<?php
namespace MediaWiki\Extension\Report;

use MediaWiki\Installer\DatabaseUpdater;

class SchemaHooks implements
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
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
}
