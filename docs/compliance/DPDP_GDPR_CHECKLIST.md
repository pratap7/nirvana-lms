# Pramāna LMS — DPDP Act 2023 & GDPR Compliance Checklist

**Document Version:** 1.0  
**Status:** Audit-Ready  
**Jurisdictions Covered:** India (Digital Personal Data Protection Act 2023), European Economic Area (General Data Protection Regulation 2016/679)

---

## 1. Compliance Verification Checklist

### Section A: Lawful Basis, Consent & Notice Architecture

| Control Item | Requirement Description | Implementation Status | Evidence / Code Reference |
| :--- | :--- | :--- | :--- |
| **A.1 Granular Consent** | Consent must be free, specific, informed, unconditional, and unambiguous. | **COMPLIANT** | Pre-ticked boxes prohibited on checkout & registration forms. |
| **A.2 Multilingual Notice** | Consent notice accessible in English and any 8th Schedule language (DPDP Sec 5). | **COMPLIANT** | Multi-language localized views (`resources/lang/{en,hi,sa}`). |
| **A.3 Consent Token Storage** | Timestamped consent record with IP, user agent, notice version hash, and purpose. | **COMPLIANT** | Consent tracking model logging privacy version and timestamp. |
| **A.4 Easy Withdrawal** | Withdrawing consent must be as effortless as giving it (DPDP Sec 6(4)). | **COMPLIANT** | Student Profile -> Privacy Settings -> "Revoke Marketing Consent" toggle. |
| **A.5 Verifiable Parental Consent** | Processing child personal data requires verifiable consent of parent/guardian (DPDP Sec 9). | **COMPLIANT** | Age verification step; for minors under 18, guardian email authorization required. |
| **A.6 Cookie & Storage Consent** | Cookie consent banner for non-essential cookies with opt-in mechanism before tracking. | **COMPLIANT** | Cookie banner component on public site with configurable preferences. |

---

### Section B: Data Principal / Data Subject Rights (DSR)

| Control Item | DSR Provision | SLA Window | System Implementation |
| :--- | :--- | :--- | :--- |
| **B.1 Right to Access** | Summary of personal data processed, processing identities, and third-party disclosures. | **30 Calendar Days** | Self-serve "Download My Data" JSON export in Student Portal. |
| **B.2 Right to Correction / Updating** | Correction of inaccurate or misleading personal data. | **15 Calendar Days** | Edit Profile in Student & Teacher portals. |
| **B.3 Right to Erasure ("To Be Forgotten")** | Deletion of personal data unless required by statutory tax/certification retention rules. | **30 Calendar Days** | Anonymizes student PII while retaining financial tax invoice records as required by law. |
| **B.4 Right to Nominate** | Right to designate an individual to exercise rights upon death or incapacity (DPDP Sec 14). | **Immediate on form submission** | Student settings field: `nominated_person_name`, `contact`, `relationship`. |
| **B.5 Right to Data Portability** | Structured, commonly used, machine-readable format (JSON/CSV) for transfer (GDPR Art. 20). | **30 Calendar Days** | Student Export API providing machine-readable academic and attendance transcript. |

---

### Section C: Technical & Organizational Safeguards (TOMs)

| Safeguard Area | Standard / Implementation in Pramāna LMS | Validation Status |
| :--- | :--- | :--- |
| **Data Encryption at Rest** | Database backups encrypted using AES-256-CBC (`php artisan db:backup`). S3 server-side encryption enabled (SSE-S3/KMS). | **VERIFIED** |
| **Data Encryption in Transit** | TLS 1.3 enforced, HSTS header with `max-age=31536000; includeSubDomains; preload`. | **VERIFIED** |
| **Role-Based Access Control** | Least privilege enforcement via Spatie Permissions + scoped policies (Course/Branch). IDOR tests strictly pass across all portals. | **VERIFIED** |
| **Rate Limiting & Anti-Abuse** | Dedicated throttles on authentication (5/min), OTP (3/min), webhooks (120/min), and verification routes (30/min). | **VERIFIED** |
| **Zero Blind Logging (PII Redaction)** | Passwords, OTPs, auth tokens, and raw credit card numbers stripped from Monolog log context. | **VERIFIED** |
| **SSRF & File Injection Defense** | Cloud metadata (`169.254.169.254`), private RFC1918 IPs, MIME-spoofed binaries, and Excel formula payloads (`=,+,-,@`) strictly sanitized. | **VERIFIED** |

---

### Section D: Cross-Border Transfers & Third-Party Processors

1. **Sub-processor Due Diligence:**
   - **Payment Gateway:** Razorpay Software Pvt Ltd / Stripe Inc. (PCI-DSS Level 1 certified; DPDP/GDPR compliant data processing addendum signed).
   - **Cloud Infrastructure:** AWS Asia Pacific (Mumbai) / DigitalOcean (Bangalore) (Data localization compliant under DPDP guidance).
   - **Email & SMS Delivery:** AWS SES / Exotel (DPIIT / TRAI DLT registered).
2. **Cross-Border Transfer Restrictions:**
   - No personal data of Indian citizens is transferred to countries blacklisted by the Central Government of India under Section 16 of the DPDP Act 2023.
   - For EEA users, standard contractual clauses (SCCs) are in place.

---

### Section E: Statutory Grievance Redressal (DPDP Act Section 13)

Under the Indian DPDP Act 2023, every Data Fiduciary must publish the contact details of a Data Protection / Grievance Officer:

- **Designation:** Grievance & Data Protection Officer (DPO)
- **Appointee:** Pramāna Gurukula Compliance Office
- **Official Address:** Pramāna Learning Systems Pvt. Ltd., Knowledge Campus, Bengaluru, Karnataka 560001, India
- **Email:** `grievance@pramana-gurukula.in` / `dpo@pramana-gurukula.in`
- **Response SLA:** Acknowledgment within **24 hours**; Resolution within **30 calendar days**.
- **Escalation Mechanism:** If unsatisfied with our grievance resolution, Data Principals may lodge a complaint with the **Data Protection Board of India**.
