# Phase 1 Conflicts and Missing Data

Version: 1.1.0  
Controlling date: 2026-08-11

## Completeness audit result

- Institute active course families: **29**.
- Institute approved levels/options: **49** from 51 source rows.
- Diploma active course identities: **14** (9 Diploma + 5 PG Diploma).
- Total active course identities: **43**.
- The previously missing twenty-ninth Institute family is **Medical Laboratory Technology**, verified in the approved workbook as the category containing source rows 5 and 32.
- The source-row reduction is explicit: Neuro advanced rows 15 and 16 are duplicates, and CCH source rows 20 and 50 resolve to one canonical family/option. Neither creates another active course.

## Open missing-data blocks

These are the only open Phase 1 missing-data blocks:

| Course key | Missing approved data | Content blocked | Course master blocked |
|---|---|---|---|
| institute_cms_ed | CMS & ED 2-year syllabus | syllabus, subjects, papers, modules | No |
| institute_cch | CCH syllabus and assessment | syllabus-specific and assessment-specific content | No |
| institute_bems | BEMS syllabus and assessment | syllabus-specific and assessment-specific content | No |
| diploma_industrial_safety | Diploma Industrial Safety syllabus and assessment | syllabus-specific and assessment-specific content | No |
| diploma_pg_industrial_safety | PG Diploma Industrial Safety syllabus and assessment | syllabus-specific and assessment-specific content | No |
| diploma_pg_disaster_management | PG Diploma Disaster Management approved syllabus | syllabus, subjects, papers, modules | No |

Missing syllabus or assessment never blocks the corresponding course master, fee, duration, eligibility where applicable, admission, documents, URL, image, or overview.

## Resolved catalog conflicts

### Previous 15-key result

The earlier package physically contained only 3 Institute records and 12 Diploma records. Its validator checked uniqueness and cross-file coverage only against that incomplete 15-record union, so “15/15” was internally consistent but not a completeness test against the approved catalog cardinality.

### Institute family 29

Medical Laboratory Technology was omitted even though the approved workbook has a dedicated category with a 6-month Certificate in Medical Lab Technology and a 1-year Advanced Certificate in Clinical Lab Technology. It is now one active family with two options.

### Diploma active set

The earlier active diploma_disaster_management record is not in the approved 14-identity catalog. It is now explicit inactive evidence. The three omitted approved identities—Diploma in Sports Injury Diagnosis & Management, PG Diploma in Dietetics & Public Health Nutrition, and PG Diploma in Sports Injury Diagnosis & Management—are active.

### CMS & ED

The controlling values remain **2 Years** and **₹17,000**. Any conflicting 6-month, 18-month, or ₹16,999 CMS & ED value is rejected.

### Site identities and aliases

Institute and Diploma identities remain separate. Aliases, spelling variants, source duplicates, and family options never receive competing active course keys.

## Unresolved conflicts

No catalog identity conflict remains unresolved. Only the six course-specific syllabus/assessment blocks above remain open.
