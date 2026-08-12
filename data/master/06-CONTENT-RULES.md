# Phase 1 Content Rules

Version: 1.1.0  
Controlling date: 2026-08-11

## Canonical catalog scope

- ATAL Institute: **29 active course families** represented by **49 approved levels/options**.
- Atal Diploma: **14 active course identities**: 9 Diploma and 5 PG Diploma.
- Total active course identities: **43**.
- The verified twenty-ninth Institute family is **Medical Laboratory Technology**.
- A family option or alias is not a second active course. The two duplicated Neuro source rows resolve to one advanced option, and “Therapy and Specialised Certificate” resolves only to CCH.
- Diploma in Disaster Management is retained only as inactive evidence; it is not one of the 14 active Diploma identities.

## Source precedence

1. Current approved Phase 1 decisions and locked repository rules.
2. Canonical course masters in this package.
3. Approved official syllabus references in 03-SYLLABUS-MASTER.json.
4. Approved first-party course and URL records.
5. Older audits, brochures, generated SEO batches, and legacy pages as evidence only.

An older source never overrides a locked value. Generated SEO prose is never a syllabus source.

## Site identity boundary

- atal_institute means ATAL Institute at atalinstitute.com and uses Rank Math.
- atal_diploma means Atal Diploma at ataldiploma.com and uses AIOSEO.
- A course belongs to exactly one target_site.
- Never present an Institute program as a university Diploma/PG Diploma, or the reverse.
- Cross-site course links are prohibited.

## Canonical identity, families, options, and aliases

- Every active identity has exactly one unique course_key.
- Institute course records are families. When a family has multiple options, option-specific fee or duration content must select an explicit option_key.
- Aliases are lookup terms only and never create an active record.
- Resolve aliases before selecting facts, links, images, intents, or options.
- Stop on any alias resolving to more than one active identity.

## ATAL Institute rules

- Normal posts must omit eligibility; do not add an eligibility heading, field, FAQ, sentence, or disclaimer.
- CMS & ED is exactly **2 Years** and **₹17,000**.
- Use https://atalinstitute.com/all-courses/ when no approved individual page exists.
- Do not add a disclaimer section.
- Do not use the prohibited negative wording or unsupported doctor, licence, government-authority, clinic-authority, registration, prescription, treatment, or guaranteed-job claims.

## Atal Diploma rules

- University Diploma and PG Diploma identities remain separate from Institute programs.
- Store and publish eligibility course-by-course.
- Diploma in Basic Health Care and DNYS are **12th Pass**.
- Diploma in First Aid Treatment is **₹25,800**.
- Diploma in Hospital Management is **₹25,000**.
- The general applicable fee is **₹30,000**.
- PG Diploma eligibility is **Graduation Pass** where recorded in the course master.
- Use a matching approved individual course URL when available; otherwise use the Diploma hub.
- Never invent a syllabus, paper, mark, assessment, or internship detail.

## Missing syllabus and assessment behavior

- A missing syllabus never disables the course master, fee, duration, eligibility where applicable, admission, document, or overview facts.
- It blocks only syllabus-, subjects-, papers-, and module-specific content.
- A separately missing assessment blocks only assessment-, exam-pattern-, marks-, and passing-rule content.
- Exactly six open missing-data blocks are permitted, as listed in files 03 and 10.

## Quality gates

Before publication, validate facts, target site, title/H1/SEO/focus-keyword alignment, a complete 140–160 character meta description, same-site internal links, duplicate/cannibalization risk, attached featured media, blocked claims, and conclusion-last ordering. Any failure remains draft/review.

## Source references and phase boundary

- Every course-level and option-level fee and duration carries non-empty source_refs.
- This package contains knowledge contracts only: no WordPress plugin code, release ZIP, credential, secret, or publishing automation.
