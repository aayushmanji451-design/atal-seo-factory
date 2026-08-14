<?php
/** Receiver-owned identifiers. @package AtalDiplomaReceiver */

declare(strict_types=1);

namespace Atal\DiplomaReceiver\Config;

final class Identifiers {
	public const PLUGIN_SLUG              = 'atal-diploma-receiver';
	public const REST_NAMESPACE           = 'atal-diploma-receiver/v1';
	public const TARGET_HOST              = 'diplomanext.ataldiploma.com';
	public const TARGET_SITE              = 'atal_diploma';
	public const OPTION_DATABASE_VERSION  = 'atal_diploma_receiver_db_version';
	public const OPTION_PLUGIN_VERSION    = 'atal_diploma_receiver_version';
	public const OPTION_HMAC_SECRET       = 'atal_diploma_receiver_hmac_secret';
	public const OPTION_ACCEPTANCE_REPORT = 'atal_diploma_receiver_task_03_acceptance';
	public const OPTION_TASK05_STATE      = 'atal_diploma_receiver_task_05_state';
	public const OPTION_TASK05_REPORT     = 'atal_diploma_receiver_task_05_report';
	public const TABLE_PREFIX             = 'atal_diploma_receiver_';
	public const DATABASE_VERSION         = 1;

	private function __construct() {
	}
}
