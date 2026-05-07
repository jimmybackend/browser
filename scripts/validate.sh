#!/usr/bin/env bash
set -u

IN_CI="${CI:-}"
EXIT_CODE=0

fail() {
  echo "ERROR: $1"
  EXIT_CODE=1
}

info() {
  echo "$1"
}

echo "== Codex repository validation =="

echo ""
echo "== PHP syntax validation =="
if command -v php >/dev/null 2>&1; then
  PHP_FILES=$(find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*")
  if [ -n "$PHP_FILES" ]; then
    while IFS= read -r file; do
      php -l "$file" || EXIT_CODE=1
    done <<< "$PHP_FILES"
  else
    info "No PHP files found."
  fi
else
  fail "PHP is not installed."
fi

echo ""
echo "== Composer validation =="
if [ -f composer.json ]; then
  if command -v composer >/dev/null 2>&1; then
    composer validate --no-check-publish || EXIT_CODE=1
    composer install --no-interaction --prefer-dist --no-progress || EXIT_CODE=1
  else
    if [ -n "$IN_CI" ]; then
      fail "composer.json exists but Composer is not installed in CI."
    else
      fail "composer.json exists but Composer is not installed. Install Composer to run expected validations."
    fi
  fi
else
  info "composer.json not found. Skipping Composer validation."
fi

echo ""
echo "== PHPUnit =="
if [ -f composer.json ]; then
  if [ ! -f vendor/bin/phpunit ]; then
    if [ -n "$IN_CI" ]; then
      fail "vendor/bin/phpunit not found after composer install in CI."
    else
      fail "vendor/bin/phpunit not found after composer install. PHPUnit was expected but could not run."
    fi
  elif [ ! -f phpunit.xml.dist ]; then
    fail "phpunit.xml.dist not found. CI requires explicit PHPUnit configuration."
  else
    vendor/bin/phpunit --configuration phpunit.xml.dist || EXIT_CODE=1
  fi
else
  if [ -f vendor/bin/phpunit ]; then
    if [ -f phpunit.xml.dist ]; then
      vendor/bin/phpunit --configuration phpunit.xml.dist || EXIT_CODE=1
    elif [ -f phpunit.xml ]; then
      vendor/bin/phpunit --configuration phpunit.xml || EXIT_CODE=1
    elif [ -d tests ]; then
      vendor/bin/phpunit tests || EXIT_CODE=1
    else
      fail "PHPUnit exists but no configuration or tests directory found."
    fi
  else
    info "No composer.json and no vendor/bin/phpunit. Skipping PHPUnit."
  fi
fi

echo ""
echo "== PHPStan =="
if [ -f vendor/bin/phpstan ]; then
  vendor/bin/phpstan analyse || EXIT_CODE=1
else
  info "PHPStan not found. Skipping static analysis."
fi

echo ""
echo "== Secret scan: basic filename check =="
SENSITIVE_FILES=$(find . \
  -name ".env" -o \
  -name "*.pem" -o \
  -name "*.key" -o \
  -name "*id_rsa*" -o \
  -name "*.p12" -o \
  -name "*.pfx" \
)

if [ -n "$SENSITIVE_FILES" ]; then
  echo "WARNING: Possible sensitive files found:"
  echo "$SENSITIVE_FILES"
  EXIT_CODE=1
else
  info "No obvious sensitive filenames found."
fi

echo ""
if [ "$EXIT_CODE" -eq 0 ]; then
  echo "Validation completed successfully."
else
  echo "Validation completed with errors or warnings."
fi

exit "$EXIT_CODE"
