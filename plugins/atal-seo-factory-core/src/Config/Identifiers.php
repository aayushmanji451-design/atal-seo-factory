<?php
/**
 * Core-owned identifiers.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Config;

/**
 * Centralizes every new plugin, option, REST, and storage identifier.
 */
final class Identifiers {

	public const PLUGIN_SLUG = 'atal-seo-factory-core';

	public const REST_NAMESPACE = 'atal-seo-factory-core/v1';

	public const OPTION_DATABASE_VERSION = 'atal_seo_factory_core_db_version';

	public const OPTION_PLUGIN_VERSION = 'atal_seo_factory_core_version';

	public const OPTION_KNOWLEDGE_FINGERPRINT = 'atal_seo_factory_core_knowledge_fingerprint';

	public const OPTION_LAST_IMPORT_AT = 'atal_seo_factory_core_last_import_at';

	public const OPTION_ACCEPTANCE_REPORT = 'atal_seo_factory_core_task_02_acceptance_report';

	public const OPTION_CANARY_REPORT = 'atal_seo_factory_core_task_04_canary_report';

	public const OPTION_ACTIVATION_SEQUENCE = 'atal_seo_factory_core_activation_sequence';

	public const OPTION_IMPORT_ACTIVATION_SEQUENCE = 'atal_seo_factory_core_import_activation_sequence';

	public const TABLE_PREFIX = 'atal_seo_factory_';

	public const DATABASE_VERSION = 1;

	/**
	 * This class contains constants only.
	 */
	private function __construct() {
	}
}
