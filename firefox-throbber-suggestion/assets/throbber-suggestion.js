(function () {
	'use strict';

	var STORAGE_KEY = 'ftsDismissed';
	var DISMISS_TTL_MS = 30 * 24 * 60 * 60 * 1000; // 30 days ("1 month")

	// Stores the expiry timestamp itself (not just a flag), so "dismissed"
	// is simply "now is before the stored time" - no separate expiry key
	// to keep in sync.
	function isDismissed() {
		try {
			var expiresAt = Number(localStorage.getItem(STORAGE_KEY));
			return expiresAt > 0 && Date.now() < expiresAt;
		} catch (e) {
			// Private browsing / blocked storage: fail open (chip just
			// shows every time), not closed (never show at all).
			return false;
		}
	}

	// Explicit, deliberate long-term dismissal ("Don't show again" / a
	// completed install) - expires after DISMISS_TTL_MS rather than being
	// permanent, so a visitor who changes their mind eventually sees it
	// again instead of it being suppressed forever.
	function markDismissed() {
		try {
			localStorage.setItem(STORAGE_KEY, String(Date.now() + DISMISS_TTL_MS));
		} catch (e) {
			/* storage unavailable; chip may reappear next page load, harmless */
		}
	}

	// Only macOS and Windows have a script built for them (install.sh,
	// install.ps1). Checked via userAgent since that's available in every
	// browser; userAgentData (more modern, structured) isn't supported
	// everywhere yet, so this isn't used as the primary signal.
	function detectOS() {
		var ua = navigator.userAgent || '';
		if (/Windows/i.test(ua)) {
			return 'windows';
		}
		if (/Macintosh|Mac OS X/i.test(ua)) {
			return 'mac';
		}
		return 'other';
	}

	document.addEventListener('DOMContentLoaded', function () {
		var prompt = document.getElementById('fts-prompt');
		if (!prompt) {
			return;
		}

		if (isDismissed()) {
			// Default CSS already hides the chip (display: none), so
			// there's nothing more to do and no visible flash.
			return;
		}

		var yesBtn = document.getElementById('fts-yes');
		var noBtn = document.getElementById('fts-no');
		var closeBtn = document.getElementById('fts-close');
		var dontShowBtn = document.getElementById('fts-dont-show');
		var step1 = document.getElementById('fts-step-1');
		var step2 = document.getElementById('fts-step-2');
		var stepUnsupported = document.getElementById('fts-step-unsupported');
		var copyBtn = document.getElementById('fts-copy');
		var cmdText = document.getElementById('fts-command-text');
		var openWhere = document.getElementById('fts-open-where');
		var openHow = document.getElementById('fts-open-how');
		var viewScriptLink = document.getElementById('fts-view-script-link');

		// X and No just hide this view - no localStorage write, so the
		// chip is free to show again on the visitor's next page/visit.
		function hideOnly() {
			prompt.style.display = 'none';
		}

		// Checking "Don't show again" sets the (expiring) long-term
		// dismissal but doesn't hide the chip by itself - only No/X
		// actually close it, so checking it doesn't cut a visitor off
		// from finishing the Yes flow in the same view.
		function dismissLongTerm() {
			markDismissed();
		}

		if (noBtn) {
			noBtn.addEventListener('click', hideOnly);
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', hideOnly);
		}
		if (dontShowBtn) {
			dontShowBtn.addEventListener('change', function () {
				if (dontShowBtn.checked) {
					dismissLongTerm();
				}
			});
		}

		var PER_OS = {
			mac: {
				scriptUrlKey: 'scriptUrlMac',
				filename: 'install.sh',
				openWhere: 'Open Terminal',
				openHow: '(press ⌘ Space, type Terminal, press Enter)',
				command: 'sh ~/Downloads/install.sh'
			},
			windows: {
				scriptUrlKey: 'scriptUrlWindows',
				filename: 'install.ps1',
				openWhere: 'Open PowerShell',
				openHow: '(click Start, type PowerShell, press Enter)',
				command: 'powershell -ExecutionPolicy Bypass -File "$env:USERPROFILE\\Downloads\\install.ps1"'
			}
		};

		if (yesBtn && typeof ftsData !== 'undefined') {
			yesBtn.addEventListener('click', function () {
				var os = detectOS();
				var info = PER_OS[os];

				if (!info) {
					step1.style.display = 'none';
					if (stepUnsupported) {
						stepUnsupported.style.display = 'block';
					}
					return;
				}

				var scriptUrl = ftsData[info.scriptUrlKey];

				var link = document.createElement('a');
				link.href = scriptUrl;
				link.download = info.filename;
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);

				if (openWhere) {
					openWhere.textContent = info.openWhere;
				}
				if (openHow) {
					openHow.textContent = info.openHow;
				}
				if (cmdText) {
					cmdText.textContent = info.command;
				}
				if (viewScriptLink) {
					viewScriptLink.href = scriptUrl;
				}

				step1.style.display = 'none';
				step2.style.display = 'block';
			});
		}

		if (copyBtn && cmdText) {
			copyBtn.addEventListener('click', function () {
				var text = cmdText.textContent;
				var done = function () {
					var original = copyBtn.textContent;
					copyBtn.textContent = 'Copied!';
					setTimeout(function () {
						copyBtn.textContent = original;
					}, 1500);
				};

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done, function () {
						/* clipboard write failed silently; visitor can still select/copy manually */
					});
				}
			});
		}

		// Reveal after a short delay: showing it immediately on
		// DOMContentLoaded caused a visible glitch right as localStorage
		// finishes being read/checked.
		setTimeout(function () {
			prompt.style.display = 'block';
		}, 3000);
	});
})();
