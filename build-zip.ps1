<#
.SYNOPSIS
    Builds the installable ZIP for XTX Integration for Netatmo.

.DESCRIPTION
    Packs the plugin as <slug>.<version>.zip one level above the plugin
    directory, dropping everything listed in .distignore.

    This replaces two things that both go wrong by hand:

      * Zipping the folder yourself ships tests/, docs/ and the working
        notes.
      * GitHub's "Download ZIP" names the archive root after the repo and
        branch (Netatmo-main), so WordPress installs a SECOND plugin next
        to the existing xtx-integration-for-netatmo instead of updating it.

    The archive root here is always the plugin slug, which is the directory
    name WordPress expects.

    ASCII only, on purpose: Windows PowerShell 5.1 reads .ps1 files without
    a BOM as ANSI, so a stray en dash or umlaut turns into mojibake and the
    script stops parsing.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\build-zip.ps1
#>

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$slug = 'xtx-integration-for-netatmo'
$main = Join-Path $root "$slug.php"

if (-not (Test-Path $main)) {
    throw "Main plugin file not found: $main"
}

# ---- Version comes from the plugin header, never typed twice ------------
$header  = Get-Content $main -TotalCount 20 -Encoding UTF8
$verLine = $header | Where-Object { $_ -match '^\s*\*\s*Version:\s*(.+)$' } | Select-Object -First 1
if (-not $verLine) { throw "No 'Version:' line found in the plugin header." }
$version = $Matches[1].Trim()

Write-Host "Plugin  : $slug"
Write-Host "Version : $version"

# ---- Read .distignore --------------------------------------------------
$ignoreFile = Join-Path $root '.distignore'
if (-not (Test-Path $ignoreFile)) { throw "Missing .distignore next to this script." }

$patterns = Get-Content $ignoreFile -Encoding UTF8 |
    ForEach-Object { $_.Trim() } |
    Where-Object { $_ -ne '' -and -not $_.StartsWith('#') }

Write-Host "Excluded: $($patterns.Count) patterns from .distignore"

function Test-Excluded {
    param([string]$RelativePath)

    # Compare with forward slashes so .distignore stays platform neutral.
    $rel = $RelativePath -replace '\\', '/'

    foreach ($p in $patterns) {
        $pat = $p -replace '\\', '/'

        if ($pat.EndsWith('/')) {
            $dir = $pat.TrimEnd('/')
            if ($rel -eq $dir -or $rel.StartsWith("$dir/")) { return $true }
            continue
        }

        if ($pat.Contains('*')) {
            $leaf = Split-Path $rel -Leaf
            if ($leaf -like $pat) { return $true }
            continue
        }

        if ($rel -eq $pat) { return $true }
        if ($rel.StartsWith("$pat/")) { return $true }
    }
    return $false
}

# ---- Pack --------------------------------------------------------------
# Entry names are built by hand with forward slashes, because neither
# helper gets this right on Windows PowerShell 5.1:
#   Compress-Archive             writes backslashes in nested entry names
#   ZipFile::CreateFromDirectory does the same on .NET Framework
# The ZIP format mandates forward slashes. With backslashes, PHP's
# unzipper creates files whose names literally contain a backslash, all
# flat in the archive root, and the plugin cannot install. The check at
# the end makes sure this never silently comes back.
# Both assemblies are needed: ZipArchive/ZipArchiveMode live in
# System.IO.Compression, while ZipFile and ZipFileExtensions live in
# System.IO.Compression.FileSystem.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = Join-Path (Split-Path $root -Parent) "$slug.$version.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }

$copied  = 0
$skipped = 0
$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    foreach ($file in (Get-ChildItem $root -Recurse -File -Force)) {
        $rel = $file.FullName.Substring($root.Length).TrimStart('\', '/')

        if (Test-Excluded -RelativePath $rel) {
            $skipped++
            continue
        }

        $entryName = "$slug/" + ($rel -replace '\\', '/')

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $file.FullName, $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null

        $copied++
    }
} finally {
    $archive.Dispose()
}

# ---- Verify ------------------------------------------------------------
$check = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
    $names     = $check.Entries | ForEach-Object { $_.FullName }
    $backslash = @($names | Where-Object { $_ -match '\\' })
    $roots     = @($names | ForEach-Object { ($_ -split '/')[0] } | Sort-Object -Unique)
} finally {
    $check.Dispose()
}

if ($backslash.Count -gt 0) {
    throw "$($backslash.Count) entries use backslashes; the archive would not install."
}
if ($roots.Count -ne 1 -or $roots[0] -ne $slug) {
    throw "Unexpected archive root: $($roots -join ', '). Expected exactly '$slug'."
}

$sizeKb = [math]::Round((Get-Item $zip).Length / 1KB, 1)

Write-Host ""
Write-Host "Packed  : $copied files ($skipped skipped)"
Write-Host "Root    : $($roots[0])/  (separators verified)"
Write-Host "Archive : $zip"
Write-Host "Size    : $sizeKb KB"
