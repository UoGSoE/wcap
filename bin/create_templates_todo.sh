#!/usr/bin/env bash
# make_templates_todo.sh
# Find Blade templates to migrate and write a markdown checklist to TEMPLATES_TODO.md
# - Excludes any files under directories named mail, email, or emails
# - Excludes files that already look Flux-ified (grep for "flux:" or "<flux")
# Usage:
#   ./make_templates_todo.sh [PROJECT_DIR] [--include-converted]
# Examples:
#   ./make_templates_todo.sh
#   ./make_templates_todo.sh /path/to/project
#   ./make_templates_todo.sh . --include-converted

set -euo pipefail

PROJECT_DIR="${1:-.}"
INCLUDE_CONVERTED="${2:-}"

# Normalise to an absolute path (portable)
cd "$PROJECT_DIR"
PROJECT_DIR="$(pwd)"

OUTFILE="$PROJECT_DIR/TEMPLATES_TODO.md"
TMPFILE="$(mktemp)"

# Find all *.blade.php under resources/views, pruning mail/email/emails dirs
# Use -print0 and a NUL-delimited loop to be robust with spaces/newlines
while IFS= read -r -d '' file; do
  # Detect if file looks converted to Flux (case-insensitive)
  if grep -qiE '(^|\s|<)flux[:>]' "$file"; then
    converted=1
  else
    converted=0
  fi

  # Skip converted files unless user asked to include them
  if [[ "$converted" -eq 1 && "$INCLUDE_CONVERTED" != "--include-converted" ]]; then
    continue
  fi

  # Make path relative to project root
  rel="${file#"$PROJECT_DIR"/}"

  # Checklist marker
  if [[ "$converted" -eq 1 ]]; then
    mark="[x]"
  else
    mark="[ ]"
  fi

  printf -- "- %s %s\n" "$mark" "$rel" >> "$TMPFILE"
done < <(
  find "$PROJECT_DIR/resources/views" \
    -type d \( -name mail -o -name email -o -name emails \) -prune -o \
    -type f -name '*.blade.php' -print0
)

# Sort for stable output
{
  echo "# Templates to Migrate"
  echo ""
  echo "_Generated on $(date -u +'%Y-%m-%d %H:%M:%S UTC')_"
  echo ""
  if [[ -s "$TMPFILE" ]]; then
    # shellcheck disable=SC2002
    cat "$TMPFILE" | LC_ALL=C sort
  else
    echo "_(No matching templates found.)_"
  fi
} > "$OUTFILE"

rm -f "$TMPFILE"

echo "Wrote checklist to: $OUTFILE"

