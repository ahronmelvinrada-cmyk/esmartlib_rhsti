#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v php >/dev/null 2>&1; then
    echo "PHP CLI is required to run this check." >&2
    exit 1
fi

while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
    printf 'PHP syntax OK: %s\n' "${file#"$root"/}"
done < <(find "$root/sourcecode" -type f -name '*.php' -print0)

for required in \
    "$root/README.md" \
    "$root/database/database.sql" \
    "$root/assets/app.js" \
    "$root/docker-compose.yml" \
    "$root/sourcecode/Dockerfile" \
    "$root/sourcecode/hosting-config.example.php"
do
    if [[ ! -f "$required" ]]; then
        echo "Missing required repository resource: ${required#"$root"/}" >&2
        exit 1
    fi
done

if grep -Eiq '^[[:space:]]*INSERT[[:space:]]+INTO' "$root/database/database.sql"; then
    echo "database/database.sql must remain schema-only; do not commit live records." >&2
    exit 1
fi

echo "Repository resource checks passed."
