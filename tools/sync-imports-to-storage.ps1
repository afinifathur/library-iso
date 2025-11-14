<#
tools/sync-imports-to-storage.ps1

Usage:
  # dry-run (lihat apa yg akan dilakukan)
  .\tools\sync-imports-to-storage.ps1 -Dry

  # jalankan real (copy files)
  .\tools\sync-imports-to-storage.ps1

Notes:
 - Script ini melakukan COPY (tidak memindahkan).
 - Hasil copy akan diletakkan di storage\app\<matchedFolder>\v1\
 - Jika ada lebih dari 1 candidate folder untuk sebuah file -> akan dilaporkan dan tidak disalin (cek manual).
 - Jika tidak ada candidate -> tercatat di unmatched.txt
#>

param(
    [switch]$Dry = $false,
    [string]$ProjectRoot = "$(Get-Location)",
    [string]$ImportsRelative = "imports",
    [string]$StorageRelative = "storage\app",
    [string[]]$Extensions = @("doc","docx","pdf")
)

# normalize helpers
function NormalizeString($s) {
    if (-not $s) { return "" }
    $s = $s.ToLowerInvariant()
    # remove accents? quick replacement - optional
    $s = [System.Text.Encoding]::ASCII.GetString([System.Text.Encoding]::UTF8.GetBytes($s))
    $s = $s -replace '[^a-z0-9]', ''
    return $s
}

$importsDir = Join-Path $ProjectRoot $ImportsRelative
$storageDir = Join-Path $ProjectRoot $StorageRelative

if (-not (Test-Path $importsDir)) {
    Write-Error "Imports folder not found: $importsDir"
    exit 1
}
if (-not (Test-Path $storageDir)) {
    Write-Error "Storage folder not found: $storageDir"
    exit 1
}

Write-Host "Project root : $ProjectRoot"
Write-Host "Imports dir  : $importsDir"
Write-Host "Storage dir  : $storageDir"
Write-Host "Dry-run      : $Dry"
Write-Host ""

# build a map of candidate directories under storage (only dirs)
$dirs = Get-ChildItem -Path $storageDir -Recurse -Directory -ErrorAction SilentlyContinue
$map = @()
foreach ($d in $dirs) {
    $name = $d.Name
    $norm = NormalizeString($name)
    $map += [PSCustomObject]@{ Path = $d.FullName; Name = $name; Norm = $norm }
}

Write-Host ("Found {0} candidate storage directories" -f $map.Count)

# gather files
$pattern = $Extensions | ForEach-Object { "*.$_" }
$files = Get-ChildItem -Path $importsDir -Recurse -File -Include $pattern -ErrorAction SilentlyContinue

Write-Host ("Found {0} files in imports to process" -f $files.Count)
Write-Host ""

$unmatched = @()
$ambiguous = @()
$copied = 0

foreach ($f in $files) {
    # token strategy: take filename without extension, take first token up to space or keep all,
    # but we will also try full normalized filename
    $fnameNoExt = [System.IO.Path]::GetFileNameWithoutExtension($f.Name)
    # sometimes the doc code is the first token (like IK.QA-FL.01....)
    $firstTok = ($fnameNoExt -split '\s+')[0]
    $candidatesToTry = @($firstTok, $fnameNoExt)

    $matchedDirs = @()
    foreach ($tok in $candidatesToTry) {
        $normTok = NormalizeString($tok)
        if ($normTok -eq "") { continue }
        # find any storage dir whose normalized name contains this token
        $found = $map | Where-Object { $_.Norm -like "*$normTok*" }
        if ($found) {
            foreach ($x in $found) { $matchedDirs += $x }
            # break we will consider matches collected
            break
        }
    }

    # if still none, try using normalized full filename search across directories' filenames inside
    if ($matchedDirs.Count -eq 0) {
        $normFull = NormalizeString($fnameNoExt)
        $found2 = $map | Where-Object { $_.Norm -like "*$normFull*" }
        if ($found2) { $matchedDirs += $found2 }
    }

    # deduplicate
    $matchedDirs = $matchedDirs | Select-Object -Unique

    if ($matchedDirs.Count -eq 1) {
        $targetDir = $matchedDirs[0].Path
        # ensure v1 subfolder exists (common import pattern)
        $v1dir = Join-Path $targetDir "v1"
        if (-not (Test-Path $v1dir)) {
            if ($Dry) { Write-Host "[DRY] Would create directory: $v1dir" }
            else {
                New-Item -ItemType Directory -Path $v1dir -Force | Out-Null
                Write-Host "Created: $v1dir"
            }
        }
        $dest = Join-Path $v1dir $f.Name
        if ($Dry) {
            Write-Host "[DRY] Would copy: $($f.FullName) -> $dest"
            $copied++
        } else {
            Copy-Item -Path $f.FullName -Destination $dest -Force
            Write-Host "Copied: $($f.Name) -> $dest"
            $copied++
        }
    }
    elseif ($matchedDirs.Count -gt 1) {
        $ambiguous += [PSCustomObject]@{ File = $f.FullName; Candidates = ($matchedDirs | ForEach-Object { $_.Path } ) -join '; ' }
        Write-Warning "Ambiguous: $($f.Name) -> {0} candidates" -f $matchedDirs.Count
    } else {
        $unmatched += $f.FullName
        Write-Host "Unmatched: $($f.FullName)"
    }
}

# write reports
$reportDir = Join-Path $ProjectRoot "tools\reports"
if (-not (Test-Path $reportDir)) { New-Item -ItemType Directory -Path $reportDir -Force | Out-Null }

$unmatchedFile = Join-Path $reportDir "unmatched.txt"
$ambiguousFile = Join-Path $reportDir "ambiguous.txt"

$unmatched | Out-File -FilePath $unmatchedFile -Encoding utf8
$ambiguous | ConvertTo-Json -Depth 3 | Out-File -FilePath $ambiguousFile -Encoding utf8

Write-Host ""
Write-Host "Done. Copied (or would copy) $copied files."
Write-Host "Unmatched saved to: $unmatchedFile  (count: $($unmatched.Count))"
Write-Host "Ambiguous saved to: $ambiguousFile  (count: $($ambiguous.Count))"
if ($Dry) { Write-Host "Dry-run mode: no files were actually copied." }
