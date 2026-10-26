#!/usr/bin/env bash
# Installs the .tab-throbber userChrome.css rule into a Firefox profile and
# enables the pref required for Firefox to load userChrome.css at all.
#
# Usage:
#   ./install.sh [--profile <dir>] [--dry-run]
#
# Run by hand only. Never invoke this from a webpage or on page load.
set -euo pipefail

DRY_RUN=0
PROFILE_OVERRIDE=""

THROBBER_CSS='/* Firefox 158: Chrome-style spinning circle (pure CSS border-spinner, Chrome blue) */

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
}'

PREF_LINE='user_pref("toolkit.legacyUserProfileCustomizations.stylesheets", true);'

# Collected for the final summary (Task 7).
SUMMARY=()

usage() {
  cat <<'EOF'
Usage: install.sh [--profile <dir>] [--dry-run]

  --profile <dir>  Target this Firefox profile directory directly, skipping
                    profiles.ini auto-detection.
  --dry-run        Print what would change without writing anything.
  -h, --help       Show this help.
EOF
}

# ---- Task 1: argument parsing -------------------------------------------

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --profile)
        [[ $# -ge 2 ]] || { echo "error: --profile requires an argument" >&2; usage >&2; exit 1; }
        PROFILE_OVERRIDE="$2"
        shift 2
        ;;
      --dry-run)
        DRY_RUN=1
        shift
        ;;
      -h|--help)
        usage
        exit 0
        ;;
      *)
        echo "error: unknown argument: $1" >&2
        usage >&2
        exit 1
        ;;
    esac
  done
}

# ---- Task 2: profile resolution ------------------------------------------

resolve_profile_dir() {
  if [[ -n "$PROFILE_OVERRIDE" ]]; then
    if [[ ! -d "$PROFILE_OVERRIDE" ]]; then
      echo "error: --profile path does not exist: $PROFILE_OVERRIDE" >&2
      exit 1
    fi
    if [[ ! -f "$PROFILE_OVERRIDE/prefs.js" ]]; then
      echo "error: --profile path doesn't look like a Firefox profile (no prefs.js found): $PROFILE_OVERRIDE" >&2
      exit 1
    fi
    printf '%s\n' "$PROFILE_OVERRIDE"
    return
  fi

  local base="$HOME/Library/Application Support/Firefox"
  local ini="$base/profiles.ini"
  if [[ ! -f "$ini" ]]; then
    echo "error: profiles.ini not found at: $ini" >&2
    exit 1
  fi

  # Modern Firefox tracks the active default profile per-installation in an
  # [InstallXXXXXXXX] section's Default= line; this takes priority because
  # it reflects what Firefox itself actually launches, and can disagree with
  # the legacy per-profile Default=1 flag (e.g. after a profile migration).
  local rel_path
  rel_path="$(awk '
    /^\[Install/ { in_install=1; next }
    /^\[/        { in_install=0 }
    in_install && /^Default=/ { sub(/^Default=/, ""); print; exit }
  ' "$ini")"

  if [[ -z "$rel_path" ]]; then
    # Fall back to legacy [ProfileN] blocks: prefer Default=1, then
    # Name=default-release, then Name=default. Each block's state must be
    # flushed into the candidate variables before moving to the next block,
    # otherwise an earlier block's Default=1 is lost once a later block
    # resets the scratch variables.
    rel_path="$(awk '
      function consider() {
        if (path == "") return
        if (is_default && default1_path == "") default1_path = path
        if (name == "default-release" && release_path == "") release_path = path
        if (name == "default" && default_path == "") default_path = path
      }
      /^\[Profile/ { consider(); path=""; is_default=0; name=""; next }
      /^Path=/     { sub(/^Path=/, ""); path=$0; next }
      /^Default=1/ { is_default=1; next }
      /^Name=default-release/ { name="default-release"; next }
      /^Name=default$/        { name="default"; next }
      /^\[/        { consider(); path=""; is_default=0; name=""; next }
      END {
        consider()
        if (default1_path != "") { print default1_path; exit }
        if (release_path != "") { print release_path; exit }
        if (default_path != "") { print default_path; exit }
      }
    ' "$ini")"
  fi

  if [[ -z "$rel_path" ]]; then
    echo "error: could not find a default profile in: $ini" >&2
    exit 1
  fi

  local abs_path="$base/$rel_path"
  if [[ ! -d "$abs_path" ]] || [[ ! -f "$abs_path/prefs.js" ]]; then
    echo "error: resolved profile path is not a valid profile: $abs_path" >&2
    exit 1
  fi

  printf '%s\n' "$abs_path"
}

# ---- Firefox lifecycle: auto quit (if needed) and relaunch ----------------

firefox_is_running() {
  pgrep -x firefox >/dev/null 2>&1 || pgrep -x firefox-bin >/dev/null 2>&1
}

# Determines whether applying the install would actually change anything,
# without writing anything. Used to decide whether Firefox needs to be
# quit/relaunched at all — a no-op run should never touch a running Firefox.
needs_changes() {
  local profile_dir="$1"
  local css_file="$profile_dir/chrome/userChrome.css"
  local prefs_file="$profile_dir/prefs.js"

  if [[ ! -f "$css_file" ]] || ! grep -q '\.tab-throbber' "$css_file"; then
    return 0
  fi
  if [[ ! -f "$prefs_file" ]] || ! grep -qF "$PREF_LINE" "$prefs_file"; then
    return 0
  fi
  return 1
}

# Gracefully quits Firefox if it's running, waiting (with a timeout) for it
# to fully exit before returning — never force-kills, since prefs.js is only
# safe to edit once Firefox has released it on its own.
quit_firefox_gracefully() {
  if ! firefox_is_running; then
    return
  fi
  osascript -e 'tell application "Firefox" to quit' >/dev/null 2>&1 || true
  local waited=0
  while firefox_is_running; do
    sleep 1
    waited=$((waited + 1))
    if [[ "$waited" -ge 20 ]]; then
      echo "error: Firefox did not quit within 20s. Please quit it manually and re-run." >&2
      exit 1
    fi
  done
  SUMMARY+=("Quit Firefox (was running) so its settings could be updated")
}

relaunch_firefox() {
  open -a Firefox >/dev/null 2>&1 || true
  SUMMARY+=("Relaunched Firefox")
}

# ---- Task 4: chrome/ directory creation -----------------------------------

ensure_chrome_dir() {
  local profile_dir="$1"
  local chrome_dir="$profile_dir/chrome"

  if [[ -d "$chrome_dir" ]]; then
    return
  fi

  if [[ "$DRY_RUN" -eq 1 ]]; then
    SUMMARY+=("Would create directory: $chrome_dir")
    return
  fi

  mkdir -p "$chrome_dir"
  SUMMARY+=("Created directory: $chrome_dir")
}

# ---- Task 5: userChrome.css install/append/backup -------------------------

install_css() {
  local profile_dir="$1"
  local css_file="$profile_dir/chrome/userChrome.css"
  local bak_file="$css_file.bak"

  if [[ ! -f "$css_file" ]]; then
    if [[ "$DRY_RUN" -eq 1 ]]; then
      SUMMARY+=("Would create file: $css_file (with .tab-throbber rule)")
      return
    fi
    printf '%s\n' "$THROBBER_CSS" > "$css_file"
    SUMMARY+=("Created file: $css_file")
    return
  fi

  if grep -q '\.tab-throbber' "$css_file"; then
    SUMMARY+=("Already installed: $css_file already contains .tab-throbber — left untouched")
    return
  fi

  if [[ "$DRY_RUN" -eq 1 ]]; then
    SUMMARY+=("Would back up: $css_file -> $bak_file")
    SUMMARY+=("Would append .tab-throbber rule to: $css_file")
    return
  fi

  cp "$css_file" "$bak_file"
  {
    printf '\n'
    printf '%s\n' "$THROBBER_CSS"
  } >> "$css_file"
  SUMMARY+=("Backed up: $css_file -> $bak_file")
  SUMMARY+=("Appended .tab-throbber rule to: $css_file")
}

# ---- Task 6: prefs.js pref injection --------------------------------------

ensure_pref() {
  local profile_dir="$1"
  local prefs_file="$profile_dir/prefs.js"

  if [[ -f "$prefs_file" ]] && grep -qF "$PREF_LINE" "$prefs_file"; then
    SUMMARY+=("Already set: toolkit.legacyUserProfileCustomizations.stylesheets is already true in $prefs_file")
    return
  fi

  if [[ "$DRY_RUN" -eq 1 ]]; then
    SUMMARY+=("Would append to $prefs_file: $PREF_LINE")
    return
  fi

  printf '%s\n' "$PREF_LINE" >> "$prefs_file"
  SUMMARY+=("Set pref in $prefs_file: toolkit.legacyUserProfileCustomizations.stylesheets = true")
}

# ---- Task 7: summary reporting --------------------------------------------

print_summary() {
  echo
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "=== Dry run — no files were changed ==="
  else
    echo "=== Summary ==="
  fi
  for line in "${SUMMARY[@]}"; do
    echo "- $line"
  done
}

# ---- main ------------------------------------------------------------------

main() {
  parse_args "$@"
  local profile_dir
  profile_dir="$(resolve_profile_dir)"
  SUMMARY+=("Target profile: $profile_dir")

  local change_needed=0
  if needs_changes "$profile_dir"; then
    change_needed=1
  fi

  if [[ "$DRY_RUN" -eq 1 ]]; then
    if [[ "$change_needed" -eq 1 ]]; then
      if firefox_is_running; then
        SUMMARY+=("Would quit Firefox (currently running), apply changes, then relaunch it")
      else
        SUMMARY+=("Would launch Firefox once changes are applied")
      fi
    fi
    ensure_chrome_dir "$profile_dir"
    install_css "$profile_dir"
    ensure_pref "$profile_dir"
    print_summary
    return
  fi

  if [[ "$change_needed" -eq 1 ]]; then
    quit_firefox_gracefully
  fi
  ensure_chrome_dir "$profile_dir"
  install_css "$profile_dir"
  ensure_pref "$profile_dir"
  if [[ "$change_needed" -eq 1 ]]; then
    relaunch_firefox
  fi
  print_summary
}

main "$@"
