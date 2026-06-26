# D1 — IDENTITY & DEPARTMENT GOVERNANCE AUDIT REPORT

> [!NOTE]
> This audit report has been compiled in preparation for implementing **Department Boundaries** and **Role-Based Visibility** within the Library-ISO system. It provides a complete map of roles, users, and departments to ensure clean implementation.

---

## 1. EXECUTIVE SUMMARY

| Metric | Count | Findings & Status |
| :--- | :--- | :--- |
| **Total Active Roles** | **5** | 5 configured in database. `auditor` role is missing/inactive. |
| **Total Active Users** | **26** | Only 3 users currently assigned to a department. **23 users are orphaned.** |
| **Total Departments** | **30** | **100% of departments (30/30) lack an assigned PIC** in the database. |
| **Multi-role Users** | **1** | Only `direktur` holds multiple roles (`admin` and `director`). |
| **Email Anomalies** | **2** | Typo domain `@peronik.com` detected in 2 user email addresses. |

---

## 2. ROLE INVENTORY

Audit conducted on `roles` and `model_has_roles` tables.

### Role Distribution Table

| Role | Total Users | Status / DB Check |
| :--- | :---: | :--- |
| **admin** | 1 | Active |
| **mr** | 3 | Active |
| **kabag** | 17 | Active |
| **viewer** | 5 | Active |
| **director** | 1 | Active |
| **auditor** | 0 | **Inactive / Missing from DB** |

### Role Status Findings
* **Roles without Users**: `auditor` (This role is defined in specifications but does not exist in the `roles` table. Currently, external auditor `tuv@peroniks.com` is assigned the `mr` role).
* **Duplicate Roles**: None. The database enforces a unique constraint on the `name` and `guard_name` columns.
* **Legacy Roles**: None. All 5 roles present in the table are currently assigned to users.

---

## 3. USER INVENTORY

Below is the complete audit of the 26 users registered in the system, grouped by their department assignment.

### A. Users WITH Assigned Department

These users are properly configured in the system with their respective department boundaries.

| ID | Name | Email | Role(s) | Department Code | Department Name |
| :-: | :--- | :--- | :--- | :---: | :--- |
| 1 | direktur | direktur@peroniks.com | admin, director | **DIR** | Direktur |
| 2 | MR | MR@peroniks.com | mr | **MR** | Management Representative |
| 7 | kabagqc | kabagqc@peroniks.com | kabag | **QA-FL** | QC Flange |

---

### B. Users WITHOUT Assigned Department (Action Required)

These 23 users have `department_id = NULL` in the database. They must be mapped to their respective departments before the boundary logic is activated.

| ID | Name | Email | Role | Expected Department | Status Recommendation |
| :-: | :--- | :--- | :--- | :---: | :--- |
| 3 | managerppic | managerppic@peroniks.com | kabag | **PPIC** | Map to PPIC |
| 4 | managerhr | managerhr@peroniks.com | kabag | **PRS** | Map to HR (PRS) |
| 5 | managermarketing | managermarketing@peroniks.com | kabag | **MKT** | Map to Marketing (MKT) |
| 6 | managerpurchasing| managerpurchasing@peroniks.com| kabag | **PBL** | Map to Pembelian (PBL) |
| 8 | managerACC | managerACC@peroniks.com | kabag | **ACC & FIN**| Map to Accounting & Finance |
| 9 | managertax | managertax@peroniks.com | kabag | **TAX** | Map to Manajer Pajak (TAX) |
| 10| kabagcorflange | kabagcorflange@peroniks.com | kabag | **COR-FL** | Map to Cor Flange |
| 11| kabagcorfitting | kabagcorfitting@peronik.com | kabag | **COR-PF** | Map to Cor Fitting |
| 12| kabagflange | kabagflange@peroniks.com | kabag | **PRD-FL** | Map to Produksi Flange |
| 13| kabagfitting | kabagfitting@peroniks.com | kabag | **PRD-PF** | Map to Produksi Fitting |
| 14| kabagnettoflange | kabagnettoflange@peronik.com| kabag | **NT-FL** | Map to Netto Flange |
| 15| kabagnettofitting| kabagnettofitting@peroniks.com| kabag | **NT-PF** | Map to Netto Fitting |
| 16| kabagbubutflange | kabagbubutflange@peroniks.com| kabag | **BBT-FL** | Map to Bubut Flange |
| 17| kabagbubutfitting| kabagbubutfitting@peroniks.com| kabag | **BBT-PF** | Map to Bubut Fitting |
| 18| kabagmaintenance| kabagmaintenance@peroniks.com| kabag | **MTC** | Map to Maintenance (MTC) |
| 19| kabagga | kabagga@peroniks.com | kabag | **PRS** / **GA** | Map to HR/GA (or create new code) |
| 20| adminflange | adminflange@peroniks.com | viewer | **PRD-FL** | Map to Produksi Flange |
| 21| adminfitting | adminfitting@peroniks.com | viewer | **PRD-PF** | Map to Produksi Fitting |
| 22| adminqcflange | adminqcflange@peroniks.com | viewer | **QA-FL** | Map to QC Flange |
| 23| adminqcfitting | adminqcfitting@peroniks.com | viewer | **QA-PF** | Map to QC Fitting |
| 24| adminmarketing | adminmarketing@peroniks.com | viewer | **MKT** | Map to Marketing |
| 25| dc | dc@peroniks.com | mr | *None* | **SYSTEM EXEMPT** (Doc Controller) |
| 26| tuv | tuv@peroniks.com | mr | *None* | **SYSTEM EXEMPT** (Ext. Auditor) |

---

## 4. DEPARTMENT INVENTORY & PIC COVERAGE

The system has **30 departments** defined in the `departments` table.

> [!WARNING]
> * **100% of departments** (30 out of 30) have `manager_id = NULL`, meaning no official manager/PIC is assigned in the database.
> * **27 departments** currently have **0 users** mapped to them.

### Department Mapping & Coverage Table

| ID | Code | Department Name | Assigned Users | Manager/PIC | Coverage Status |
| :-: | :---: | :--- | :---: | :---: | :--- |
| 1 | **QA** | QA | 0 | *None* | **WARNING: Empty** |
| 4 | **PRD** | Produksi | 0 | *None* | **WARNING: Empty** |
| 5 | **DIR** | Direktur | 1 (`direktur`) | *None* | Covered (No official PIC) |
| 6 | **MR** | Management Representative | 1 (`MR`) | *None* | Covered (No official PIC) |
| 7 | **PRS** | HR | 0 | *None* | **WARNING: Empty** |
| 8 | **PPIC** | PPIC | 0 | *None* | **WARNING: Empty** |
| 9 | **MKT** | Marketing | 0 | *None* | **WARNING: Empty** |
| 10| **PBL** | Pembelian | 0 | *None* | **WARNING: Empty** |
| 11| **PRD-FL**| Produksi Flange | 0 | *None* | **WARNING: Empty** |
| 12| **PRD-PF**| Produksi Fitting | 0 | *None* | **WARNING: Empty** |
| 13| **QA-FL** | QC Flange | 1 (`kabagqc`) | *None* | Covered (No official PIC) |
| 14| **QA-PF** | QC Fitting | 0 | *None* | **WARNING: Empty** |
| 15| **QA-BHN**| QC Bahan Baku | 0 | *None* | **WARNING: Empty** |
| 16| **QA-AL** | QC Aluminium | 0 | *None* | **WARNING: Empty** |
| 17| **MTC** | Maintenance | 0 | *None* | **WARNING: Empty** |
| 18| **TAX** | Manajer Pajak | 0 | *None* | **WARNING: Empty** |
| 19| **ACC & FIN** | Accounting & Finance | 0 | *None* | **WARNING: Empty** |
| 20| **GUD-JFL**| Gudang Jadi Flange | 0 | *None* | **WARNING: Empty** |
| 21| **GUD-JPF**| Gudang Jadi Fitting | 0 | *None* | **WARNING: Empty** |
| 22| **GUD-BHN**| Gudang Bahan | 0 | *None* | **WARNING: Empty** |
| 23| **PAM** | Keamanan | 0 | *None* | **WARNING: Empty** |
| 24| **LILIN-PF**| Lilin Fitting | 0 | *None* | **WARNING: Empty** |
| 25| **COR-PF**| Cor Fitting | 0 | *None* | **WARNING: Empty** |
| 26| **COR-FL**| Cor Flange | 0 | *None* | **WARNING: Empty** |
| 27| **NT-PF** | Netto Fitting | 0 | *None* | **WARNING: Empty** |
| 28| **NT-FL** | Netto Flange | 0 | *None* | **WARNING: Empty** |
| 29| **BBT-FL**| Bubut Flange | 0 | *None* | **WARNING: Empty** |
| 30| **BOR-FL**| Bor Flange | 0 | *None* | **WARNING: Empty** |
| 31| **BBT-PF**| Bubut Fitting | 0 | *None* | **WARNING: Empty** |
| 32| **MNJ** | Manajemen | 0 | *None* | **WARNING: Empty** |

---

## 5. MULTI-ROLE AUDIT

Only one user has multiple roles assigned in the `model_has_roles` table:

| User (Email) | Assigned Roles | Current Recommendation | Rationale |
| :--- | :--- | :--- | :--- |
| **direktur@peroniks.com** | `admin`, `director` | **KEEP / REVIEW** | The director is the executive head. He/she needs the `director` role for executive visibility, but holding the `admin` role grants IT administration power. It is recommended to keep this during the transition but eventually split administrative actions to a dedicated IT Administrator account for strict audit trails. |

---

## 6. EMAIL GOVERNANCE AUDIT

An audit of the 26 user email addresses reveals a highly structured pattern.

### Email Classifications

* **Department & Position Based** (22 Users / 84.6%):
  Formed as `{position}{department_code}@peroniks.com` (e.g., `managerppic@peroniks.com`, `kabagqc@peroniks.com`, `adminflange@peroniks.com`).
* **General Position Based** (3 Users / 11.5%):
  General functional accounts: `direktur@peroniks.com`, `MR@peroniks.com`, `dc@peroniks.com`.
* **Personal/Organization Name Based** (1 User / 3.9%):
  External certification body auditor: `tuv@peroniks.com`.

### Dominant Pattern
The dominant pattern is **Department & Position Based**, where the prefix represents a specific organizational role and department abbreviation, rather than a personal name.

### Governance Typo Alerts
There are **two critical email domain errors** in the database:
1. **kabagcorfitting@peronik.com** (Missing 's' in `peroniks.com`)
2. **kabagnettoflange@peronik.com** (Missing 's' in `peroniks.com`)

> [!CAUTION]
> If email notifications are active in the system, any system alerts or document updates sent to these two users will fail, causing communication breakdowns. These typos must be fixed in the database.

---

## 7. SYSTEM EXEMPT USERS (NO DEPARTMENT REQUIRED)

The following users must be exempted from department boundaries:

| User | Role | Exemption Reason |
| :--- | :---: | :--- |
| **dc@peroniks.com** | `mr` | **Document Controller**: Responsible for publishing, distributing, stamping, and organizing ISO/QMS documents across the entire organization. |
| **MR@peroniks.com** | `mr` | **Management Representative**: The overall owner of the QMS system. Must be able to audit and review documents from every single department. |
| **tuv@peroniks.com** | `mr` | **External Auditor**: Needs read-only visibility across all departments to perform compliance audits on the organization's QMS structure. |
| **direktur@peroniks.com** | `admin, director` | **Executive Director**: Needs full operational view of the entire organization's documentation. |

---

## 8. FUTURE PERMISSION MODEL (DRAFT MATRIX)

This is the proposed boundary model to restrict document visibility while maintaining global access for governance roles.

```mermaid
graph TD
    subgraph Global Access (No Boundary)
        Admin[Admin / Director / MR / DC]
        Auditor[External Auditor - Read Only]
    end

    subgraph Department Boundary (Restricted)
        KabagQA[Kabag QA] -->|Access Only| QA[QA Docs]
        ViewerQA[Viewer QA] -->|Read Only| QA[QA Docs]
        
        KabagPRD[Kabag PRD] -->|Access Only| PRD[PRD Docs]
        ViewerPRD[Viewer PRD] -->|Read Only| PRD[PRD Docs]
    end
```

### Role + Department Visibility Matrix

| Role | Department Scope | Document Action Permissions |
| :--- | :--- | :--- |
| **admin** | All Departments | Create, Edit, Delete, Read, Manage Settings |
| **director**| All Departments | Read All, Approve high-level policies |
| **mr** | All Departments | Read, Review, Publish, Stamp, Distribute All |
| **auditor** | All Departments | **Read-Only All** (including distribution logs & trails) |
| **kabag** | **Own Department Only** | Read, Create Drafts, Edit, Delete Drafts of own dept |
| **viewer** | **Own Department Only** | **Read-Only / Download** of approved documents of own dept |

---

## 9. IMPLEMENTATION RECOMMENDATIONS

Based on this audit, the following steps must be completed before implementing Department Boundary logic in the code:

1. **Fix User Assignments**:
   Run a DB patch script to map the 23 orphaned users to their respective `department_id`.
2. **Correct Email Domains**:
   Update `kabagcorfitting@peronik.com` and `kabagnettoflange@peronik.com` to use `@peroniks.com`.
3. **Assign Department PICs**:
   Map the respective `kabag` (or `manager`) user ID to the `manager_id` column in the `departments` table.
4. **Create / Register the `auditor` Role**:
   Create the missing `auditor` role in the `roles` table, and reassign the `tuv@peroniks.com` user from `mr` to `auditor` for better separation of duties.
5. **Implement Query Scoping**:
   Apply Eloquent Global Scopes or query modifiers in `DocumentController@index` to restrict results for non-exempt users based on `auth()->user()->department_id`.
