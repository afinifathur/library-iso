<#
tools/match-and-copy-by-filename.ps1

Goal: For each file in imports/*.{doc,docx,pdf} try to find a matching file under storage/app by filename.
If exactly one candidate found -> copy imports file into that candidate's folder/v1/ (so DB file_path likely matches).
If multiple candidates -> record ambiguous list (for manual review).
If none -> record unmatched.

Usage:
  # dry-run (no copy)
  .\tools\match-and-copy-by-filename.ps1 -Dry

  # run actual copy
  .\tools\match-and-copy-by-filename.ps1

Notes:
 - This is more aggressive: matching is done by normalized filename (alphanumeric only).
 - Script does COPY (ke storage/app/<candidateParent>/v1/<origName>) not move.
 - Always inspect reports in tools\reports after run.
#>

param(
    [switch]$Dry = $false,
    [string]$ProjectRoot = "$(Get-Location)",
    [string]$ImportsRelative = "imports",
    [string]$StorageRelative = "storage\app",
    [string[]]$Extensions = @("doc","docx","pdf")
)

function Normalize($s) {
    if (-not $s) { return "" }
    $n = $s.ToLowerInvariant()
    # remove accents (best-effort), then remove non-alphanumeric
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($n)
    $ascii = [System.Text.Encoding]::ASCII.GetString($bytes)
    return ($ascii -replace '[^a-z0-9]','')
}

$importsDir = Join-Path $ProjectRoot $ImportsRelative
$storageDir = Join-Path $ProjectRoot $StorageRelative
$reportDir  = Join-Path $ProjectRoot "tools\reports"

if (-not (Test-Path $importsDir)) { Write-Error "Imports folder not found: $importsDir"; exit 1 }
if (-not (Test-Path $storageDir)) { Write-Error "Storage folder not found: $storageDir"; exit 1 }
if (-not (Test-Path $reportDir)) { New-Item -ItemType Directory -Path $reportDir -Force | Out-Null }

# build index of all files inside storage/app (map normalized filename -> fullpath)
$pattern = $Extensions | ForEach-Object { "*.$_" }
Write-Host "Indexing storage files (this may take a bit)..."
$storageFiles = Get-ChildItem -Path $storageDir -Recurse -File -Include $pattern -ErrorAction SilentlyContinue
$index = @{}
foreach ($sf in $storageFiles) {
    $norm = Normalize($sf.Name)
    if (-not $index.ContainsKey($norm)) { $index[$norm] = @() }
    $index[$norm] += $sf.FullName
}
Write-Host "Indexed $($storageFiles.Count) storage files."

# iterate imports
$importsFiles = Get-ChildItem -Path $importsDir -Recurse -File -Include $pattern -ErrorAction SilentlyContinue
Write-Host "Processing $($importsFiles.Count) import files..."

$unmatched = @()
$ambiguous = @()
$copied = 0
$autoMap = @()

foreach ($f in $importsFiles) {
    $name = $f.Name
    $normName = Normalize($name)
    $candidates = @()

    # exact normalized filename match
    if ($index.ContainsKey($normName)) {
        $candidates = $index[$normName]
    } else {
        # try partial matching: search index keys that contain token chunks
        # break filename into tokens by punctuation/spaces and try longest tokens
        $tokens = ($name -split '[\s\.\-_&]+') | Where-Object { $_ -ne "" } | ForEach-Object { Normalize($_) } | Where-Object { $_ -ne "" }
        foreach ($t in $tokens) {
            # skip tiny tokens
            if ($t.Length -lt 4) { continue }
            $matches = $index.Keys | Where-Object { $_ -like "*$t*" }
            foreach ($k in $matches) { $candidates += $index[$k] }
            if ($candidates.Count -gt 0) { break }
        }
    }

    $candidates = $candidates | Select-Object -Unique

    if ($candidates.Count -eq 1) {
        $targetFile = $candidates[0]
        $targetDir  = Split-Path $targetFile -Parent
        # choose v1 subfolder under parent folder
        $v1dir = Join-Path $targetDir "v1"
        $dest = Join-Path $v1dir $name
        if ($Dry) {
            Write-Host "[DRY] Would copy $($f.FullName) -> $dest"
            $copied++
            $autoMap += [PSCustomObject]@{ Import = $f.FullName; Target = $targetFile; Dest = $dest }
        } else {
            if (-not (Test-Path $v1dir)) { New-Item -ItemType Directory -Path $v1dir -Force | Out-Null }
            Copy-Item -Path $f.FullName -Destination $dest -Force
            Write-Host "Copied: $($f.Name) -> $dest"
            $copied++
            $autoMap += [PSCustomObject]@{ Import = $f.FullName; Target = $targetFile; Dest = $dest }
        }
    }
    elseif ($candidates.Count -gt 1) {
        $ambiguous += [PSCustomObject]@{ File = $f.FullName; Candidates = ($candidates -join '; ') }
        Write-Warning "Ambiguous matches for $name -> $($candidates.Count) candidates"
    } else {
        $unmatched += $f.FullName
        Write-Host "Unmatched: $($f.FullName)"
    }
}

# write reports
$unmatchedFile = Join-Path $reportDir "unmatched_by_name.txt"
$ambiguousFile = Join-Path $reportDir "ambiguous_by_name.json"
$autoMapFile   = Join-Path $reportDir "auto_map_by_name.json"

$unmatched | Out-File -FilePath $unmatchedFile -Encoding utf8
$ambiguous | ConvertTo-Json -Depth 4 | Out-File -FilePath $ambiguousFile -Encoding utf8
$autoMap  | ConvertTo-Json -Depth 4 | Out-File -FilePath $autoMapFile -Encoding utf8

Write-Host ""
Write-Host "Done. Copied (or would copy) $copied files."
Write-Host "Unmatched saved to: $unmatchedFile  (count: $($unmatched.Count))"
Write-Host "Ambiguous saved to: $ambiguousFile  (count: $($ambiguous.Count))"
Write-Host "Auto-map saved to: $autoMapFile (count: $($autoMap.Count))"
if ($Dry) { Write-Host "Dry-run mode: no files were actually copied." }
