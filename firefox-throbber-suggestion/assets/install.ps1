#!/usr/bin/env pwsh
<#
.SYNOPSIS
  Installs the .tab-throbber userChrome.css rule into a Firefox profile and
  enables the pref required for Firefox to load userChrome.css at all.

.DESCRIPTION
  Windows/PowerShell port of install.sh. Run by hand only - never invoke
  this from a webpage or on page load.

  NOTE: This script has not been tested on a real Windows machine. It was
  written to mirror install.sh's verified behavior using standard
  PowerShell/Windows conventions, but has not been run end-to-end the way
  install.sh was. Verify it on Windows before relying on it with real users.

.PARAMETER ProfileDir
  Target this Firefox profile directory directly, skipping profiles.ini
  auto-detection.

.PARAMETER DryRun
  Print what would change without writing anything, and without touching
  Firefox.

.EXAMPLE
  .\install.ps1
.EXAMPLE
  .\install.ps1 -DryRun
.EXAMPLE
  .\install.ps1 -ProfileDir "C:\Users\me\AppData\Roaming\Mozilla\Firefox\Profiles\abc123.default-release"
#>

[CmdletBinding()]
param(
    [string]$ProfileDir,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$ThrobberCss = @'
/* Firefox 158: Chrome-style spinning circle (pure CSS border-spinner, Chrome blue) */

@keyframes tab-throbber-spin {
  to {
    transform: rotate(360deg);
  }
}

.tab-throbber {
  &[busy] {
    position: relative !important;
    z-index: 10 !important;
    overflow: visible !important;

    &::before {
      content: "" !important;
      position: relative !important;
      z-index: 10 !important;
      display: block !important;
      animation: tab-throbber-spin 0.8s linear infinite !important;
      background-image: none !important;
      background-color: transparent !important;
      width: 16px !important;
      height: 16px !important;
      box-sizing: border-box !important;
      border-radius: 50% !important;
      border: 3px solid rgba(66, 133, 244, 0.25) !important;
      border-top-color: #4285f4 !important;
    }
  }
}
'@

$PrefLine = 'user_pref("toolkit.legacyUserProfileCustomizations.stylesheets", true);'

$Summary = New-Object System.Collections.Generic.List[string]

# ---- Profile resolution -----------------------------------------------

function Resolve-ProfileDir {
    if ($ProfileDir) {
        if (-not (Test-Path -LiteralPath $ProfileDir -PathType Container)) {
            Write-Error "-ProfileDir path does not exist: $ProfileDir"
        }
        $prefsPath = Join-Path $ProfileDir 'prefs.js'
        if (-not (Test-Path -LiteralPath $prefsPath -PathType Leaf)) {
            Write-Error "-ProfileDir path doesn't look like a Firefox profile (no prefs.js found): $ProfileDir"
        }
        return $ProfileDir
    }

    $base = Join-Path $env:APPDATA 'Mozilla\Firefox'
    $ini = Join-Path $base 'profiles.ini'
    if (-not (Test-Path -LiteralPath $ini -PathType Leaf)) {
        Write-Error "profiles.ini not found at: $ini"
    }

    $lines = Get-Content -LiteralPath $ini

    # Modern Firefox tracks the active default profile per-installation in an
    # [InstallXXXXXXXX] section's Default= line; this takes priority because
    # it reflects what Firefox itself actually launches, and can disagree
    # with the legacy per-profile Default=1 flag (e.g. after a migration).
    $relPath = $null
    $inInstall = $false
    foreach ($line in $lines) {
        if ($line -match '^\[Install') {
            $inInstall = $true
            continue
        }
        if ($line -match '^\[') {
            $inInstall = $false
            continue
        }
        if ($inInstall -and $line -match '^Default=(.*)$') {
            $relPath = $Matches[1]
            break
        }
    }

    if (-not $relPath) {
        # Fall back to legacy [ProfileN] blocks: prefer Default=1, then
        # Name=default-release, then Name=default. Each block's state is
        # flushed into the candidate variables before moving to the next
        # block, so an earlier block's Default=1 isn't lost when a later
        # block resets the scratch variables.
        #
        # Deliberately inlined rather than a nested helper function: a
        # nested PowerShell function gets its own scope, and writing to the
        # caller's locals from inside one needs either dot-sourcing or
        # script-scope - both easy to get subtly wrong. Inlining avoids the
        # whole class of bug.
        $path = ''
        $isDefault = $false
        $name = ''
        $default1Path = $null
        $releasePath = $null
        $defaultPath = $null

        foreach ($line in $lines) {
            if ($line -match '^\[Profile') {
                if ($path -ne '') {
                    if ($isDefault -and -not $default1Path) { $default1Path = $path }
                    if ($name -eq 'default-release' -and -not $releasePath) { $releasePath = $path }
                    if ($name -eq 'default' -and -not $defaultPath) { $defaultPath = $path }
                }
                $path = ''; $isDefault = $false; $name = ''
                continue
            }
            if ($line -match '^Path=(.*)$') { $path = $Matches[1]; continue }
            if ($line -match '^Default=1') { $isDefault = $true; continue }
            if ($line -match '^Name=default-release') { $name = 'default-release'; continue }
            if ($line -match '^Name=default$') { $name = 'default'; continue }
            if ($line -match '^\[') {
                if ($path -ne '') {
                    if ($isDefault -and -not $default1Path) { $default1Path = $path }
                    if ($name -eq 'default-release' -and -not $releasePath) { $releasePath = $path }
                    if ($name -eq 'default' -and -not $defaultPath) { $defaultPath = $path }
                }
                $path = ''; $isDefault = $false; $name = ''
                continue
            }
        }
        if ($path -ne '') {
            if ($isDefault -and -not $default1Path) { $default1Path = $path }
            if ($name -eq 'default-release' -and -not $releasePath) { $releasePath = $path }
            if ($name -eq 'default' -and -not $defaultPath) { $defaultPath = $path }
        }

        if ($default1Path) { $relPath = $default1Path }
        elseif ($releasePath) { $relPath = $releasePath }
        elseif ($defaultPath) { $relPath = $defaultPath }
    }

    if (-not $relPath) {
        Write-Error "could not find a default profile in: $ini"
    }

    # profiles.ini on Windows uses backslashes or forward slashes depending
    # on version; Join-Path handles either via normalization below.
    $relPathNormalized = $relPath -replace '/', '\'
    $absPath = Join-Path $base $relPathNormalized

    if (-not (Test-Path -LiteralPath $absPath -PathType Container) -or
        -not (Test-Path -LiteralPath (Join-Path $absPath 'prefs.js') -PathType Leaf)) {
        Write-Error "resolved profile path is not a valid profile: $absPath"
    }

    return $absPath
}

# ---- Firefox lifecycle: auto quit (if needed) and relaunch ------------

function Get-FirefoxProcesses {
    Get-Process -Name 'firefox' -ErrorAction SilentlyContinue
}

function Test-NeedsChanges([string]$ResolvedProfileDir) {
    $cssFile = Join-Path $ResolvedProfileDir 'chrome\userChrome.css'
    $prefsFile = Join-Path $ResolvedProfileDir 'prefs.js'

    if (-not (Test-Path -LiteralPath $cssFile -PathType Leaf)) { return $true }
    if (-not (Select-String -LiteralPath $cssFile -Pattern '\.tab-throbber' -Quiet)) { return $true }
    if (-not (Test-Path -LiteralPath $prefsFile -PathType Leaf)) { return $true }
    if (-not (Select-String -LiteralPath $prefsFile -SimpleMatch -Pattern $PrefLine -Quiet)) { return $true }
    return $false
}

function Stop-FirefoxGracefully {
    $procs = Get-FirefoxProcesses
    if (-not $procs) { return }

    foreach ($p in $procs) {
        try { $p.CloseMainWindow() | Out-Null } catch { }
    }

    $waited = 0
    while (Get-FirefoxProcesses) {
        Start-Sleep -Seconds 1
        $waited++
        if ($waited -ge 20) {
            Write-Error "Firefox did not quit within 20s. Please quit it manually and re-run."
        }
    }
    $Summary.Add("Quit Firefox (was running) so its settings could be updated")
}

function Start-FirefoxApp {
    # Relies on Firefox being registered/launchable by name. If this fails
    # on a given system, the common install paths are tried as a fallback.
    $candidates = @(
        'firefox',
        "$env:ProgramFiles\Mozilla Firefox\firefox.exe",
        "${env:ProgramFiles(x86)}\Mozilla Firefox\firefox.exe"
    )
    foreach ($c in $candidates) {
        try {
            Start-Process -FilePath $c -ErrorAction Stop
            $Summary.Add("Relaunched Firefox")
            return
        } catch {
            continue
        }
    }
    $Summary.Add("Could not relaunch Firefox automatically - please open it manually")
}

# ---- CSS install/append/backup -----------------------------------------

function Install-ThrobberCss([string]$ResolvedProfileDir) {
    $chromeDir = Join-Path $ResolvedProfileDir 'chrome'
    $cssFile = Join-Path $chromeDir 'userChrome.css'
    $bakFile = "$cssFile.bak"

    if (-not (Test-Path -LiteralPath $chromeDir -PathType Container)) {
        if ($DryRun) {
            $Summary.Add("Would create directory: $chromeDir")
        } else {
            New-Item -ItemType Directory -Path $chromeDir -Force | Out-Null
            $Summary.Add("Created directory: $chromeDir")
        }
    }

    if (-not (Test-Path -LiteralPath $cssFile -PathType Leaf)) {
        if ($DryRun) {
            $Summary.Add("Would create file: $cssFile (with .tab-throbber rule)")
            return
        }
        Set-Content -LiteralPath $cssFile -Value $ThrobberCss -NoNewline:$false
        $Summary.Add("Created file: $cssFile")
        return
    }

    if (Select-String -LiteralPath $cssFile -Pattern '\.tab-throbber' -Quiet) {
        $Summary.Add("Already installed: $cssFile already contains .tab-throbber - left untouched")
        return
    }

    if ($DryRun) {
        $Summary.Add("Would back up: $cssFile -> $bakFile")
        $Summary.Add("Would append .tab-throbber rule to: $cssFile")
        return
    }

    Copy-Item -LiteralPath $cssFile -Destination $bakFile -Force
    Add-Content -LiteralPath $cssFile -Value ("`n" + $ThrobberCss)
    $Summary.Add("Backed up: $cssFile -> $bakFile")
    $Summary.Add("Appended .tab-throbber rule to: $cssFile")
}

function Install-Pref([string]$ResolvedProfileDir) {
    $prefsFile = Join-Path $ResolvedProfileDir 'prefs.js'

    if (Test-Path -LiteralPath $prefsFile -PathType Leaf) {
        if (Select-String -LiteralPath $prefsFile -SimpleMatch -Pattern $PrefLine -Quiet) {
            $Summary.Add("Already set: toolkit.legacyUserProfileCustomizations.stylesheets is already true in $prefsFile")
            return
        }
    }

    if ($DryRun) {
        $Summary.Add("Would append to ${prefsFile}: $PrefLine")
        return
    }

    Add-Content -LiteralPath $prefsFile -Value $PrefLine
    $Summary.Add("Set pref in ${prefsFile}: toolkit.legacyUserProfileCustomizations.stylesheets = true")
}

# ---- main ---------------------------------------------------------------
#
# Wrapped in try/catch with an explicit `exit 1`: unlike bash's `set -e`,
# PowerShell does not automatically give the process a non-zero exit code
# on an uncaught terminating error, so that has to be done by hand to match
# install.sh's documented "exits non-zero on failure" behavior.

try {
    $resolvedProfileDir = Resolve-ProfileDir
    $Summary.Add("Target profile: $resolvedProfileDir")

    $changeNeeded = Test-NeedsChanges $resolvedProfileDir

    if ($DryRun) {
        if ($changeNeeded) {
            if (Get-FirefoxProcesses) {
                $Summary.Add("Would quit Firefox (currently running), apply changes, then relaunch it")
            } else {
                $Summary.Add("Would launch Firefox once changes are applied")
            }
        }
        Install-ThrobberCss $resolvedProfileDir
        Install-Pref $resolvedProfileDir
    } else {
        if ($changeNeeded) {
            Stop-FirefoxGracefully
        }
        Install-ThrobberCss $resolvedProfileDir
        Install-Pref $resolvedProfileDir
        if ($changeNeeded) {
            Start-FirefoxApp
        }
    }

    Write-Host ""
    if ($DryRun) {
        Write-Host "=== Dry run - no files were changed ==="
    } else {
        Write-Host "=== Summary ==="
    }
    foreach ($line in $Summary) {
        Write-Host "- $line"
    }
} catch {
    Write-Host ""
    Write-Host "error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
