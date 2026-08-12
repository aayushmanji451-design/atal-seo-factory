<?php
/**
 * Independent canonical catalog allowlist.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Data;

/**
 * Locked active identity lists used to prevent self-referential completeness checks.
 */
final class CanonicalCatalog {

	/**
	 * Return the 29 approved Institute family keys.
	 *
	 * @return list<string>
	 */
	public static function institute_keys(): array {
		return array(
			'institute_basic_health_care',
			'institute_hospital_management',
			'institute_first_aid_care',
			'institute_natural_pharmacy',
			'institute_pg_basic_health_care',
			'institute_pg_hospital_management',
			'institute_pg_dietetics_nutrition_management',
			'institute_dialysis_technology',
			'institute_ecg_cardiac_care',
			'institute_first_aid_emergency_care',
			'institute_hospital_healthcare_administration',
			'institute_medical_billing_coding',
			'institute_medical_record_front_office_management',
			'institute_nursing_patient_care',
			'institute_operation_theatre_technology',
			'institute_pharmacy_healthcare_support',
			'institute_phlebotomy_blood_collection',
			'institute_cms_ed',
			'institute_general_duty_assistant',
			'institute_electro_homeopathy_wellness',
			'institute_panchakarma_ayurvedic_therapy',
			'institute_acupressure_wellness_therapy',
			'institute_bone_joint_therapy',
			'institute_neuro_therapy_rehabilitation',
			'institute_physiotherapy_rehabilitation',
			'institute_yoga_naturopathy_therapy',
			'institute_bems',
			'institute_cch',
			'institute_medical_laboratory_technology',
		);
	}

	/**
	 * Return the 14 approved Diploma identity keys.
	 *
	 * @return list<string>
	 */
	public static function diploma_keys(): array {
		return array(
			'diploma_first_aid_treatment',
			'diploma_basic_health_care',
			'diploma_electro_homeopathy',
			'diploma_dnys',
			'diploma_hospital_management',
			'diploma_natural_pharma',
			'diploma_sports_injury_diagnosis_management',
			'diploma_industrial_safety',
			'diploma_fire_safety_hazard_management',
			'diploma_pg_basic_health_care',
			'diploma_pg_dietetics_public_health_nutrition',
			'diploma_pg_sports_injury_diagnosis_management',
			'diploma_pg_disaster_management',
			'diploma_pg_industrial_safety',
		);
	}

	/**
	 * Return all 43 active identity keys.
	 *
	 * @return list<string>
	 */
	public static function all_keys(): array {
		return array_merge( self::institute_keys(), self::diploma_keys() );
	}
}
