# Phase 1 Validation Report

Version: 1.1.0  
Validation date: 2026-08-11

## Final result

**PASS after completeness repair.** The earlier 15-key package was incomplete. The regenerated package contains all 29 Institute families and all 14 approved Diploma identities, with exact coverage in every catalog-keyed contract.

## Explicit catalog totals

**Institute active course families: 29**  
**Diploma active course families: 14**  
**Total active course identities: 29 + 14 = 43**

- Institute levels/options: **49** across the 29 families.
- Diploma levels: **9 Diploma + 5 PG Diploma = 14 identities**.
- Combined family-option/level records: **63** (49 Institute options + 14 Diploma identities).

## ATAL Institute — complete active family list

1. institute_basic_health_care — Basic Health Care
2. institute_hospital_management — Hospital Management
3. institute_first_aid_care — First Aid Care
4. institute_natural_pharmacy — Natural Pharmacy
5. institute_pg_basic_health_care — PG Certificate – Basic Health Care
6. institute_pg_hospital_management — PG Certificate – Hospital Management
7. institute_pg_dietetics_nutrition_management — PG Certificate – Dietetics & Nutrition Management
8. institute_dialysis_technology — Dialysis Technology
9. institute_ecg_cardiac_care — ECG & Cardiac Care
10. institute_first_aid_emergency_care — First Aid & Emergency Care
11. institute_hospital_healthcare_administration — Hospital & Healthcare Administration
12. institute_medical_billing_coding — Medical Billing & Coding
13. institute_medical_record_front_office_management — Medical Record & Front Office Management
14. institute_nursing_patient_care — Nursing & Patient Care
15. institute_operation_theatre_technology — Operation Theatre Technology
16. institute_pharmacy_healthcare_support — Pharmacy & Healthcare Support
17. institute_phlebotomy_blood_collection — Phlebotomy & Blood Collection
18. institute_cms_ed — CMS & ED Program
19. institute_general_duty_assistant — General Duty Assistant (GDA)
20. institute_electro_homeopathy_wellness — Electro Homeopathy & Wellness
21. institute_panchakarma_ayurvedic_therapy — Panchakarma & Ayurvedic Therapy
22. institute_acupressure_wellness_therapy — Acupressure & Wellness Therapy
23. institute_bone_joint_therapy — Bone & Joint Therapy
24. institute_neuro_therapy_rehabilitation — Neuro Therapy & Rehabilitation
25. institute_physiotherapy_rehabilitation — Physiotherapy & Rehabilitation
26. institute_yoga_naturopathy_therapy — Yoga & Naturopathy Therapy
27. institute_bems — BEMS Program
28. institute_cch — Certificate in Community Health (CCH)
29. institute_medical_laboratory_technology — Medical Laboratory Technology

## Atal Diploma — complete active identity list

1. diploma_first_aid_treatment — Diploma in First Aid Treatment
2. diploma_basic_health_care — Diploma in Basic Health Care
3. diploma_electro_homeopathy — Diploma in Electro Homeopathy
4. diploma_dnys — Diploma in Naturopathy & Yoga (DNYS)
5. diploma_hospital_management — Diploma in Hospital Management
6. diploma_natural_pharma — Diploma in Natural Pharma
7. diploma_sports_injury_diagnosis_management — Diploma in Sports Injury Diagnosis & Management
8. diploma_industrial_safety — Diploma in Industrial Safety
9. diploma_fire_safety_hazard_management — Diploma in Fire Safety & Hazard Management
10. diploma_pg_basic_health_care — PG Diploma in Basic Health Care
11. diploma_pg_dietetics_public_health_nutrition — PG Diploma in Dietetics & Public Health Nutrition
12. diploma_pg_sports_injury_diagnosis_management — PG Diploma in Sports Injury Diagnosis & Management
13. diploma_pg_disaster_management — PG Diploma in Disaster Management
14. diploma_pg_industrial_safety — PG Diploma in Industrial Safety

## Why the previous validator reported 15

The former course masters contained only 3 Institute keys and 12 Diploma keys. The former validator formed its expected set from those same incomplete masters and then reported 15/15 cross-file coverage. It did not compare the result with an independent approved cardinality or expected key list. That is why uniqueness passed while completeness failed.

## Exact validation results

~~~text
PACKAGE_FILES: PASS (12/12)
JSON_PARSE: PASS (9/9)
INSTITUTE_ACTIVE_FAMILIES: PASS (29/29)
INSTITUTE_LEVELS_OPTIONS: PASS (49/49 from 51 source rows)
DIPLOMA_ACTIVE_IDENTITIES: PASS (14/14; 9 Diploma + 5 PG Diploma)
TOTAL_ACTIVE_IDENTITIES: PASS (43/43)
COURSE_KEY_UNIQUENESS: PASS (43/43 unique)
TARGET_SITE_IDENTITY: PASS (43/43)
FEE_DURATION_SOURCE_REFS: PASS (43/43 course records; 49/49 Institute options)
CROSS_CONTRACT_COVERAGE: PASS (syllabus, URL, image, internal-link: 43/43 each)
ALIAS_COLLISIONS: PASS (0)
DUPLICATE_ACTIVE_IDENTITIES: PASS (0)
SOURCE_OPTION_DEDUPLICATION: PASS (1 duplicate Neuro row + 1 CCH alias row merged)
CROSS_SITE_RELATED_LINKS: PASS (0)
OPEN_MISSING_BLOCK_ALLOWLIST: PASS (6/6)
LOCKED_FACTS: PASS
TEST_FIXTURES: PASS (30/30)
ALL_12_CATALOG_CONSISTENCY: PASS
PHASE_1_VALIDATION: PASS
~~~

## Missing and unexpected records repaired

- Added the 26 omitted Institute families, including the verified remaining family, Medical Laboratory Technology.
- Added the 3 omitted Diploma identities: Diploma Sports Injury, PG Diploma Dietetics & Public Health Nutrition, and PG Diploma Sports Injury.
- Removed diploma_disaster_management from the active set and retained it explicitly in the inactive identity register.
- No alias or option is stored as a competing active course.

## All 12-file consistency

- Files 01 and 02 define the 43 active identities.
- Files 03, 04, 05, and 08 contain exactly one record for every active key.
- Files 06, 07, and 09 declare the same 29/14/43 scope and enforce family/alias behavior.
- File 10 records the resolved omissions and the six remaining data blocks.
- File 11 snapshots all 43 keys and validates cardinality.
- This file lists every active key and the exact validation totals.

## Locked decisions verified

CMS & ED remains 2 Years and ₹17,000; Institute normal-post eligibility is OMIT for all 29 families; Diploma eligibility is course-by-course; locked Diploma fees and eligibility remain unchanged; identities never cross sites; missing syllabus blocks only syllabus-specific content.

## Failures and warnings

- Validation failures: none.
- Unexpected active courses after repair: none.
- Duplicate active identities after repair: none.
- Expected open missing-data blocks: six, unchanged.
- Warning: source workbook portfolio-risk recommendations remain supporting evidence only and do not override the current approved active catalog.
