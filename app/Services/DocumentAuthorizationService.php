<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

/**
 * DocumentAuthorizationService
 *
 * Phase D3A — Authorization Engine Foundation.
 *
 * This is the SINGLE SOURCE OF TRUTH for all document-related authorization
 * decisions in Library-ISO. No duplicated role/ownership checks should exist
 * anywhere else in the application.
 *
 * Business Matrix
 * ───────────────
 * Role          View  DL-PDF  DL-Master  Edit  Revision  Submit  Approve  Delete
 * viewer         ✔      ✔        ✘         ✘      ✘        ✘        ✘        ✘
 * auditor        ✔      ✔        ✘         ✘      ✘        ✘        ✘        ✘
 * kabag (owner)  ✔      ✔        ✔         ✔      ✔        ✔        ✘        ✔
 * kabag (other)  ✔      ✔        ✘         ✘      ✘        ✘        ✘        ✘
 * mr             ✔      ✔        ✔         ✔      ✔        ✔        ✔        ✘
 * director       ✔      ✔        ✔         ✘      ✘        ✘        ✔        ✘
 * admin          ✔      ✔        ✔         ✔      ✔        ✔        ✔        ✔
 *
 * Global Users
 * ─────────────
 * Users whose department_id is null are treated as GLOBAL and bypass
 * department-ownership restrictions. This covers cross-department accounts
 * such as dc@peroniks.com and tuv@peroniks.com.
 */
class DocumentAuthorizationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public Authorization API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Can the user view the document (metadata + approved version)?
     * All authenticated roles can view.
     */
    public function canView(User $user, Document $document): bool
    {
        return true; // All authenticated users may view approved documents.
    }

    /**
     * Can the user download the stamped Controlled Copy PDF?
     * All authenticated roles can download the PDF.
     */
    public function canDownloadPdf(User $user, Document $document): bool
    {
        return true; // All authenticated users receive the FPDI-stamped copy.
    }

    /**
     * Can the user download the raw master file (DOCX/XLSX)?
     * Restricted to: kabag (own dept or global), mr, director, admin.
     */
    public function canDownloadMaster(User $user, Document $document): bool
    {
        if ($this->isAdmin($user)) return true;
        if ($this->isMr($user))   return true;
        if ($this->isDirector($user)) return true;

        if ($this->isKabag($user)) {
            return $this->isOwnerDepartment($user, $document);
        }

        return false;
    }

    /**
     * Can the user edit the document's metadata (title, dept, review date)?
     * Restricted to: kabag (own dept or global), mr, admin.
     */
    public function canEditMetadata(User $user, Document $document): bool
    {
        if ($this->isAdmin($user)) return true;
        if ($this->isMr($user))   return true;

        if ($this->isKabag($user)) {
            return $this->isOwnerDepartment($user, $document);
        }

        return false;
    }

    /**
     * Can the user create a new revision (draft version) for the document?
     * Restricted to: kabag (own dept or global), mr, admin.
     */
    public function canCreateRevision(User $user, Document $document): bool
    {
        if ($this->isAdmin($user)) return true;
        if ($this->isMr($user))   return true;

        if ($this->isKabag($user)) {
            return $this->isOwnerDepartment($user, $document);
        }

        return false;
    }

    /**
     * Can the user submit a draft revision into the approval workflow?
     * Restricted to: kabag (own dept or global), mr, admin.
     */
    public function canSubmitRevision(User $user, Document $document): bool
    {
        if ($this->isAdmin($user)) return true;
        if ($this->isMr($user))   return true;

        if ($this->isKabag($user)) {
            return $this->isOwnerDepartment($user, $document);
        }

        return false;
    }

    /**
     * Can the user approve a submitted version?
     * Restricted to: mr, director, admin.
     */
    public function canApprove(User $user, Document $document): bool
    {
        if ($this->isAdmin($user))    return true;
        if ($this->isMr($user))       return true;
        if ($this->isDirector($user)) return true;

        return false;
    }

    /**
     * Can the user trash/delete a document version?
     * Restricted to: kabag (own dept or global), admin.
     * MR and Director explicitly excluded per business rules.
     */
    public function canDelete(User $user, Document $document): bool
    {
        if ($this->isAdmin($user)) return true;

        if ($this->isKabag($user)) {
            return $this->isOwnerDepartment($user, $document);
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Role Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the user holds the 'admin' role.
     */
    private function isAdmin(User $user): bool
    {
        return $this->userHasRole($user, 'admin');
    }

    /**
     * Returns true if the user holds the 'mr' (Management Representative) role.
     */
    private function isMr(User $user): bool
    {
        return $this->userHasRole($user, 'mr');
    }

    /**
     * Returns true if the user holds the 'director' role.
     */
    private function isDirector(User $user): bool
    {
        return $this->userHasRole($user, 'director');
    }

    /**
     * Returns true if the user holds the 'kabag' (Kepala Bagian) role.
     */
    private function isKabag(User $user): bool
    {
        return $this->userHasRole($user, 'kabag');
    }

    /**
     * Returns true if the user holds the 'viewer' role.
     * (Currently unrestricted — all authenticated users may view.)
     */
    private function isViewer(User $user): bool
    {
        return $this->userHasRole($user, 'viewer');
    }

    /**
     * Returns true if the user holds the 'auditor' role.
     * Auditors have the same read-only access as viewers.
     */
    private function isAuditor(User $user): bool
    {
        return $this->userHasRole($user, 'auditor');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Ownership Helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the user "owns" the document's department.
     *
     * A user is the owner when:
     *   (a) user.department_id === document.department_id   (same department), OR
     *   (b) user.department_id is NULL                      (global user — bypass)
     *
     * Global users (e.g. dc@peroniks.com, tuv@peroniks.com) have no department
     * restriction and are treated as department-agnostic by design.
     */
    private function isOwnerDepartment(User $user, Document $document): bool
    {
        // Global user: no department assigned → bypass ownership check
        if (is_null($user->department_id)) {
            return true;
        }

        // Department match check
        return (int) $user->department_id === (int) $document->department_id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Role Resolution (Spatie compatibility)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Safely resolves whether a user holds a given role name.
     *
     * Uses Spatie's HasRoles trait if available, with a fallback
     * to direct roles relationship pluck for resilience.
     */
    private function userHasRole(User $user, string $role): bool
    {
        // Spatie HasRoles primary path
        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) $user->hasRole($role);
            } catch (\Throwable) {
                // fall through to fallback
            }
        }

        // Fallback: query roles relationship directly
        if (method_exists($user, 'roles')) {
            try {
                return $user->roles()
                    ->where('name', $role)
                    ->exists();
            } catch (\Throwable) {
                // fall through
            }
        }

        return false;
    }
}
