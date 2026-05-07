#!/usr/bin/env bash
set -u

echo "== Codex repository validation =="

EXIT_CODE=0

echo ""
echo "== PHP syntax validation =="
if command -v php >/dev/null 2>&1; then
  PHP_FILES=$(find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*")
  if [ -n "$PHP_FILES" ]; then
    while IFS= read -r file; do
      php -l "$file" || EXIT_CODE=1
    done <<< "$PHP_FILES"
  else
    echo "No PHP files found."
  fi
else
  echo "PHP is not installed. Skipping PHP syntax validation."
fi

echo ""
echo "== Composer validation =="
if [ -f composer.json ]; then
  if command -v composer >/dev/null 2>&1; then
    composer validate --no-check-publish || EXIT_CODE=1
    composer install --no-interaction --prefer-dist --no-progress || EXIT_CODE=1
  else
    echo "Composer is not installed. Skipping Composer validation."
  fi
else
  echo "composer.json not found. Skipping Composer validation."
fi

echo ""
echo "== PHPUnit =="
if [ -f vendor/bin/phpunit ]; then
  vendor/bin/phpunit || EXIT_CODE=1
else
  echo "PHPUnit not found. Skipping tests."
fi

echo ""
echo "== PHPStan =="
if [ -f vendor/bin/phpstan ]; then
  vendor/bin/phpstan analyse || EXIT_CODE=1
else
  echo "PHPStan not found. Skipping static analysis."
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
  echo "No obvious sensitive filenames found."
fi

echo ""
if [ "$EXIT_CODE" -eq 0 ]; then
  echo "Validation completed successfully."
else
  echo "Validation completed with errors or warnings."
fi

exit "$EXIT_CODE"
