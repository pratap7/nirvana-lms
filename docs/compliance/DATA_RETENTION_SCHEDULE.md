# Pramāna LMS — Data Retention Schedule

**Document Version:** 1.0  
**Effective Date:** October 2026  
**Applicability:** Pramāna Gurukula LMS & Associated Database Services  
**Compliance Mandates:** India Digital Personal Data Protection (DPDP) Act 2023, EU General Data Protection Regulation (GDPR) Art. 5(1)(e), IT Act 2000 & CERT-In Directives, Companies Act 2013, GST Act 2017.

---

## 1. Objective & Principle of Storage Limitation

Pramāna Gurukula collects and processes personal data strictly for defined educational, administrative, certification, and statutory compliance purposes. Personal data shall not be retained in identifiable form longer than necessary for the purposes for which it was collected.

Upon expiration of the retention window, data is either:
1. **Cryptographically Purged / Shredded:** Complete unrecoverable deletion across primary DB, replicas, and hot caches.
2. **Pseudonymized / Irreversibly Anonymized:** Stripped of personal identifiers (e.g. replacing email, name, IP with one-way salted hashes) for aggregate institutional reporting and curriculum efficacy analytics.
3. **Cold-Vaulted (Encrypted WORM):** Retained in tamper-evident write-once storage for statutory tax/financial compliance audits.

---

## 2. Retention Schedules by Data Domain

| Data Classification / Entity | Key Attributes | Retention Period | Post-Retention Action | Statutory / Operational Basis |
| :--- | :--- | :--- | :--- | :--- |
| **Academic Records & Transcripts** | Certificates, enrollment history, completion status, batch credentials | **Permanent (Institutional Record)** | Indefinite archival with cryptographic verification hash | Verification of credentials & historical transcript validation |
| **Financial & Tax Records** | Invoices, payment receipts, GST breakdown, Stripe/Razorpay transaction references | **8 Years from end of Financial Year** | Cold-vault archive; encrypted with audit-only access | Indian Companies Act 2013 (Sec. 128), GST Act 2017, Income Tax Act 1961 |
| **Student Practical Assessments & Submissions** | Audio files, written assignments, mentor marks, grading feedback | **3 Years post-course completion** | Secure purging of binary media; grade summary retained | Academic dispute resolution & re-evaluation window |
| **Attendance & Live Class Logs** | Session attendance logs, Zoom/BigBlueButton join timestamps, IP addresses | **1 Year post-batch completion** | Automated DB cleanup job; aggregate attendance % retained | Batch evaluation verification & anti-proxy auditing |
| **System Audit Trails & Security Logs** | User login attempts, privilege changes, admin actions, IP addresses, user agents | **1 Year rolling window** | Daily roll-off to cold storage; deleted after 365 days | CERT-In Cybersecurity Directives (6-month minimum; 1 year best practice) |
| **Session & OTP Authentication Tokens** | OTP codes, temporary reset tokens, two-factor auth secrets | **15 Minutes (OTP) / 7 Days (Remember tokens)** | Automated immediate cache eviction / DB garbage collection | OWASP ASVS v4.0 Authentication Hygiene |
| **Inactive / Abandoned Accounts** | Accounts with zero enrollments and no login activity | **3 Years from last activity** | Automated soft-delete notification -> hard purge after 60-day notice | DPDP Act 2023 Section 8(7) (Purpose cessation requirement) |
| **Marketing & Inquiry Leads** | Contact inquiries, newsletter subscriptions, uncompleted course checkouts | **1 Year or upon consent withdrawal** | Immediate purge upon unsubscribe / right-to-be-forgotten request | DPDP Act 2023 / GDPR Consent-based processing |
| **Customer Support & Grievance Tickets** | Support chats, escalation emails, resolution notes | **3 Years from resolution date** | Redacted of personal PII; ticket resolution pattern anonymized | Consumer Protection Rules & legal defense limitation |
| **Encrypted Database Backups** | Daily AES-256-CBC database dumps | **30 Days rolling rotation** | Automated S3 lifecycle policy expiry (`DeleteMarker`) | Disaster recovery RPO/RTO without perpetual data hoarding |

---

## 3. Data Disposal & Purging Procedures

### 3.1 Automated Purging Jobs
The application leverages scheduled Artisan commands executed via cron:
```bash
# Automated retention sweep (runs weekly at 02:00 Sunday)
php artisan lms:retention-purge
```
- **Audit Logs:** Rotates logs older than 365 days out of `audit_logs` table.
- **Temporary Uploads:** Cleans up orphaned files in `storage/app/tmp/` older than 24 hours.
- **Export Files:** Cleans up queued CSV/Excel exports from `storage/app/exports/` after the signed download token expires (max 24 hours).

### 3.2 Secure Media Disposal (Object Storage / S3)
- Media attachments and private student submissions slated for deletion are issued S3 `DeleteObject` API calls.
- S3 Bucket Lifecycle Configuration enforces automatic deletion of non-current versions after 30 days and aborts incomplete multipart uploads after 7 days.

---

## 4. Legal Holds & Exceptions

If a formal legal inquiry, regulatory audit, subpoena, or pending litigation arises:
1. The **Compliance Officer / Legal Counsel** issues a formal **Legal Hold Notice**.
2. The engineering team applies a database freeze flag to the relevant tenant or student UUID (`legal_hold = true`).
3. Automated retention purge scripts bypass records flagged with `legal_hold = true` until formal written release is issued by legal counsel.

---

## 5. Review & Governance
This schedule is reviewed annually by the Data Protection Officer and Technical Lead to reflect legislative amendments to the DPDP Rules and CERT-In guidelines.
