#!/usr/bin/env bash
set -u

recommended_cron='*/5 * * * * cd /var/www/browser && /usr/bin/flock -n /tmp/browser-crawler.lock /usr/bin/php bin/browser crawl:run --limit=1 >> /var/www/browser/storage/logs/crawler.log 2>&1'
critical_missing=0

say() { printf '%s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*"; }

say '=== Browser crawler cron diagnostic ==='
say "Current user: $(id -un 2>/dev/null || echo unknown)"
say "Current path: $(pwd)"

if [ -d /var/www/browser ]; then
  say 'OK: /var/www/browser exists'
else
  warn '/var/www/browser does not exist'
fi

if [ -x bin/browser ]; then
  say 'OK: bin/browser exists and is executable'
else
  warn 'bin/browser missing or not executable (critical)'
  critical_missing=1
fi

if [ -d storage/logs ]; then
  say 'OK: storage/logs exists'
else
  warn 'storage/logs missing (critical)'
  critical_missing=1
fi

if [ -d storage/logs ] && [ -w storage/logs ]; then
  say 'OK: storage/logs is writable'
else
  warn 'storage/logs is not writable'
fi

if command -v php >/dev/null 2>&1; then
  say "OK: php found at $(command -v php)"
else
  warn 'php not found (critical)'
  critical_missing=1
fi

if command -v flock >/dev/null 2>&1; then
  say "OK: flock found at $(command -v flock)"
else
  warn 'flock not found (critical)'
  critical_missing=1
fi

if [ -f .env ]; then
  say 'OK: .env exists (content not shown)'
else
  warn '.env not found in current directory'
fi

if [ -x bin/browser ] && command -v php >/dev/null 2>&1; then
  say ''
  say '--- crawl:status output ---'
  php bin/browser crawl:status 2>&1 || warn 'could not execute crawl:status'
fi

say ''
say '--- user crontab ---'
crontab_output=''
if crontab_output=$(crontab -l 2>&1); then
  say "$crontab_output"
  if ! printf '%s' "$crontab_output" | grep -q 'crawl:run'; then
    warn 'No cron line with crawl:run found in current user crontab'
  fi

  if ! printf '%s' "$crontab_output" | grep -q 'flock'; then
    warn 'No cron line with flock found in current user crontab'
  fi
else
  warn "Cannot read user crontab: $crontab_output"
  warn 'Cron may not be installed for this user yet'
fi

say ''
say 'Recommended cron line:'
say "$recommended_cron"

if [ "$critical_missing" -eq 1 ]; then
  say ''
  say 'Result: FAIL (critical requirements missing)'
  exit 1
fi

say ''
say 'Result: OK (diagnostic completed; warnings may still apply)'
exit 0
