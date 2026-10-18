# Pramāna LMS — Information Security Incident Response Runbook

**Document Version:** 1.0  
**Effective Date:** October 2026  
**Applicability:** Pramāna Gurukula Infrastructure, Application Services, & Operations  
**Regulatory References:** CERT-In Cyber Incident Directions (Sec. 70B IT Act), DPDP Act 2023 Sec. 8(6), GDPR Art. 33/34.

---

## 1. Incident Severity Matrix & SLA

| Severity Level | Classification Definition | Initial Response SLA | Status Update Cadence | Escalation Team |
| :--- | :--- | :--- | :--- | :--- |
| **SEV-1 (Critical)** | Active database compromise, mass PII exfiltration, system-wide ransomware, unauthenticated root/admin takeover, or complete service outage. | **< 15 minutes** | Every 30 minutes | CTO, Lead Security Architect, Legal Counsel, CEO |
| **SEV-2 (High)** | Confirmed IDOR vulnerability allowing access to multi-student records, unauthorized mass payment tampering, or partial persistent denial of service. | **< 1 hour** | Every 2 hours | Lead Security Architect, DevOps Lead, Engineering Lead |
| **SEV-3 (Medium)** | Isolated credential stuffing against rate limits, single account takeover without privilege escalation, or SSRF attempt blocked by firewall/validation. | **< 4 hours** | Daily | Senior Software Engineer, Sysadmin |
| **SEV-4 (Low)** | Minor security header discrepancy, scanner noise, or unexploitable informational finding. | **< 24 hours** | Standard sprint cycle | Triaging Engineer |

---

## 2. Six-Phase Incident Response Lifecycle

```
┌──────────────┐     ┌─────────────┐     ┌────────────────┐
│ 1. Detection │ ──> │ 2. Triage   │ ──> │ 3. Containment │
└──────────────┘     └─────────────┘     └────────────────┘
                                                  │
┌──────────────┐     ┌─────────────┐              ▼
│ 6. Post-     │ <── │ 5. Recovery │ <── ┌────────────────┐
│    Mortem    │     │    & Verify │     │ 4. Eradication │
└──────────────┘     └─────────────┘     └────────────────┘
```

---

## 3. Phase-by-Phase Response Playbook

### Phase 1: Detection & Triage
1. **Trigger Channels:** Alert from Sentry, CloudWatch, AWS GuardDuty, Horizon queue failure spikes, or user report to `security@pramana-gurukula.in`.
2. **Initial Assessment:** Verify whether the alert is a false positive or confirmed compromise.
3. **Establish War Room:** For SEV-1 and SEV-2, spin up a dedicated incident response Slack channel (`#incident-YYYYMMDD-sevX`) and start an incident log document with strict chronological timestamps.

### Phase 2: Immediate Containment Protocol
Execute isolation actions depending on the vector:
- **Compromised Web Session / Credentials:**
  ```bash
  # Invalidate user session and force re-authentication
  php artisan auth:clear-resets
  # Revoke specific user tokens via database/Redis
  ```
- **Active Attacker IP Subnet:**
  Block immediately at the Cloudflare / WAF layer or host firewall:
  ```bash
  sudo ufw insert 1 deny from <ATTACKER_IP> to any
  ```
- **Application Exploit in Progress:**
  Enable Emergency Maintenance Mode with bypass secret:
  ```bash
  php artisan down --secret="incident-containment-secret" --render="errors.503"
  ```
- **Compromised API Key / Cloud Credentials:**
  Immediately rotate AWS keys, Stripe/Razorpay webhook secrets, and database passwords.

### Phase 3: Forensic Preservation
1. **Do NOT destroy or reboot running virtual instances** before memory and ephemeral state are preserved.
2. **Snapshot Storage Volumes:** Create immediate point-in-time EBS/disk snapshots of affected instances.
3. **Export System & Application Logs:**
   ```bash
   tar -czvf /tmp/forensic-logs-$(date +%s).tar.gz \
     /var/log/nginx/ \
     storage/logs/laravel*.log \
     /var/log/auth.log
   ```
4. **Compute SHA-256 Hashes** of all preserved forensic evidence artifacts to ensure chain of custody.

### Phase 4: Eradication
1. Identify and patch the root vulnerability (e.g. flawed policy check, unvalidated input, vulnerable composer package).
2. Scan all storage disks and database records for webshells, backdoor accounts, or injected admin users.
3. Deploy the emergency patch using the zero-downtime deployment script:
   ```bash
   ./deploy.sh
   ```

### Phase 5: Recovery & Verification
1. Verify database integrity using our automated drill verification tooling:
   ```bash
   php artisan db:restore storage/app/backups/latest-clean.sql.enc --drill
   ```
2. Disable maintenance mode:
   ```bash
   php artisan up
   ```
3. Monitor real-time logs, CPU/Memory load, and Horizon queues for 4 continuous hours.

---

## 4. Statutory Breach Notification Procedures

### 4.1 CERT-In Mandatory 6-Hour Reporting (India)
Under the Indian Information Technology (The Indian Computer Emergency Response Team and Manner of Performing Functions and Duties) Rules:
- **Mandatory Reporting Window:** Within **6 hours** of noticing or being brought to notice of cyber security incidents (e.g. data breach, ransomware, unauthorized access).
- **Reporting Portal:** https://www.cert-in.org.in or email to `incident@cert-in.org.in`.
- **Required Details:** Incident type, affected systems, impact assessment, chronological timeline, immediate containment steps taken.

### 4.2 Data Protection Board of India (DPDP Act 2023 Sec. 8(6))
- In the event of a personal data breach, the Data Fiduciary must give the Board and each affected Data Principal intimation of such breach in such form and manner as prescribed.
- Notification must outline:
  1. Nature of the breach.
  2. Categories and approximate number of Data Principals affected.
  3. Likely consequences and risks to Data Principals.
  4. Remedial action taken or planned.
  5. Contact details of the Data Protection Officer.

### 4.3 GDPR Supervisory Authority & Data Subject Notification (Art. 33 & 34)
- **Supervisory Authority:** Within **72 hours** after having become aware of a high-risk personal data breach.
- **Affected Subjects:** Communicated without undue delay if high risk to rights and freedoms of natural persons exists.

---

## 5. Post-Incident Review & Root-Cause Analysis (RCA)

Within 5 business days of incident closure, the Incident Commander drafts a formal RCA document:

### RCA Document Template
```markdown
# Security Incident Post-Mortem: [INCIDENT TITLE]

**Incident Date:** YYYY-MM-DD
**Incident Commander:** [Name]
**Severity Level:** SEV-1 / SEV-2
**Total Downtime / Exposure Time:** [Hours/Minutes]

## 1. Executive Summary
Brief high-level summary of what occurred, impact, and final resolution.

## 2. Chronological Timeline (UTC / IST)
- 10:14 IST: Anomaly detected by CloudWatch alert.
- 10:25 IST: War room established.
- 10:42 IST: Malicious IP blocked via WAF.
- 11:15 IST: Patch deployed; vulnerability resolved.

## 3. Root Cause Analysis (5 Whys)
- Why 1: ...
- Why 2: ...
- Why 3: ...
- Why 4: ...
- Why 5: ...

## 4. Impact Assessment
- Number of affected accounts / records:
- Financial / Regulatory implications:
- Data exfiltration verified (Yes/No):

## 5. Preventative Action Items & Preventative Measures
| Action Item | Owner | Target Completion Date | Ticket Link |
|:---|:---|:---|:---|
| Add regression test to CI | [Engineer] | YYYY-MM-DD | #1234 |
| Tighten rate limit rules | [DevOps] | YYYY-MM-DD | #1235 |
```
