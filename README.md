# ISO Library — Laravel Starter Skeleton

This package contains starter files you can drop into an existing Laravel project (Laragon-friendly) to implement a minimal Document Library for ISO/TÜV workflows (PDF-only for initial deployment).

**What is included**
- Migrations: departments, documents, document_versions
- Models: Department, Document, DocumentVersion
- Controllers: DocumentController, DocumentVersionController (basic)
- Blade views: documents index & show (very simple)
- Artisan command: documents:import for bulk import from local `imports/` folder
- Example filesystem disk config snippet to add to `config/filesystems.php`
- README with quick deploy steps for Laragon / local dev

**Important**: This is a skeleton to integrate into your existing Laravel project. Copy files into your project root (preserve paths) and run migrations. See 'Quick Install' below.

## Quick Install (Laragon / Local)
1. Copy the contents of this package into your Laravel project root (merge files). Keep backups.
2. Add this disk to `config/filesystems.php` inside `disks` array:
```php
'documents' => [
    'driver' => 'local',
    'root' => storage_path('app/documents'),
    'visibility' => 'private',
],
```
3. Run migrations:
```bash
php artisan migrate
```
4. Link storage (if you want public access later):
```bash
php artisan storage:link
```
5. Place import folders at `base_path('imports')` with structure: `imports/DEPTCODE/*.pdf`
   Example: `imports/PD01/DOC-0001_Title_v1_signed_BY-KABAG-LOG_2024-09-10.pdf`
6. Run import command (optional):
```bash
php artisan documents:import imports --move-to=imports/processed
```
7. Visit `/documents` (add auth as needed).

## Notes and next steps
- This skeleton uses PDF-only flow (fast deploy). Master DOCX handling and conversions can be added later.
- Add authentication (Laravel Breeze/Jetstream) and role-based permissions before giving write access.
- Backup DB and storage before running on production.

