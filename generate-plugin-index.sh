#!/bin/bash
# =============================================================================
# generate-plugin-index.sh
#
# Generates INDEX files for plugins. Automatically splits into multiple parts
# when the file count exceeds --max-files (default: 200).
#
# Usage:
#   bash generate-plugin-index.sh --all /path/to/repo
#   bash generate-plugin-index.sh --plugin /path/to/repo/plugins/woocommerce
#   bash generate-plugin-index.sh --subdir /path/to/repo/plugins/woocommerce/includes
#   bash generate-plugin-index.sh --subdir /path/to/repo/plugins/woocommerce/includes --max-files 150
# =============================================================================

set -e

REPO_BASE="https://raw.githubusercontent.com/E-Solutions-Consulting/esc-plugins-to-analyze/main"
REPO_WEB="https://github.com/E-Solutions-Consulting/esc-plugins-to-analyze"
MODE=""
TARGET=""
REPO_ROOT=""
MAX_FILES=200

while [[ $# -gt 0 ]]; do
    case "$1" in
        --all)       MODE="all";    TARGET="$2"; shift 2 ;;
        --plugin)    MODE="plugin"; TARGET="$2"; shift 2 ;;
        --subdir)    MODE="subdir"; TARGET="$2"; shift 2 ;;
        --max-files) MAX_FILES="$2"; shift 2 ;;
        *) echo "Unknown argument: $1"; exit 1 ;;
    esac
done

if [[ -z "$MODE" || -z "$TARGET" ]]; then
    echo "Usage:"
    echo "  bash generate-plugin-index.sh --all /path/to/repo"
    echo "  bash generate-plugin-index.sh --plugin /path/to/repo/plugins/woocommerce"
    echo "  bash generate-plugin-index.sh --subdir /path/to/repo/plugins/woocommerce/includes"
    echo "  Add --max-files N to control split size (default: 200)"
    exit 1
fi

TARGET="$(realpath "$TARGET")"

if [[ ! -d "$TARGET" ]]; then
    echo "ERROR: Directory not found: $TARGET"
    exit 1
fi

if [[ "$MODE" == "all" ]]; then
    REPO_ROOT="$TARGET"
elif [[ "$MODE" == "plugin" ]]; then
    REPO_ROOT="$(realpath "$TARGET/../..")"
elif [[ "$MODE" == "subdir" ]]; then
    REPO_ROOT="$(realpath "$TARGET/../../..")"
fi

INDEXES_DIR="$REPO_ROOT/indexes"
PLUGINS_DIR="$REPO_ROOT/plugins"
GLOBAL_INDEX="$REPO_ROOT/INDEX.md"

mkdir -p "$INDEXES_DIR"
mkdir -p "$PLUGINS_DIR"

echo "Repo root  : $REPO_ROOT"
echo "Indexes    : $INDEXES_DIR"
echo "Max files  : $MAX_FILES per index"
echo ""

# =============================================================================
# Write EOF flag to an index file
# =============================================================================
write_eof() {
    local out="$1"
    local index_name="$2"
    local count
    count=$(grep -c "^- \`" "$out" 2>/dev/null || echo 0)
    local total_lines
    total_lines=$(wc -l < "$out")
    cat >> "$out" << EOF

# -----------------------------------------------------------------------------
# EOF -- INDEX-${index_name}.md
# Total PHP files indexed : ${count}
# Total lines in this file: ${total_lines}
# Generated               : $(date '+%Y-%m-%d %H:%M')
# If you can read this line, you have read the complete index.
# -----------------------------------------------------------------------------
EOF
    echo "  OK ${count} PHP files -- ${total_lines} lines"
}

# =============================================================================
# Core function: index a directory, splitting into parts if needed
# =============================================================================
index_directory() {
    local scan_path="$1"
    local index_name="$2"
    local plugin_name="$3"
    local source_label="$4"

    echo "-> Indexing: $index_name (scanning $scan_path)"

    # Collect all files first
    local tmpfile
    tmpfile=$(mktemp)
    find "$scan_path" -name "*.php" -type f \
        -not -path "*/vendor/*" \
        -not -path "*/assets/*" \
        -not -name "*__*" \
        -not -name "*.asset.php" \
        | sort > "$tmpfile"

    local total_files
    total_files=$(wc -l < "$tmpfile")
    echo "  Found: $total_files PHP files"

    if [[ "$total_files" -le "$MAX_FILES" ]]; then
        # Single file — no split needed
        local out="$INDEXES_DIR/INDEX-${index_name}.md"
        _write_index_file "$out" "$index_name" "$scan_path" "$plugin_name" "$source_label" "$tmpfile" 1 "$total_files"
        write_eof "$out" "$index_name"
        INDEXED+=("$index_name")
    else
        # Split into parts
        local num_parts=$(( (total_files + MAX_FILES - 1) / MAX_FILES ))
        echo "  Splitting into $num_parts parts (max $MAX_FILES files each)"

        local part=1
        local start=1
        while [[ "$start" -le "$total_files" ]]; do
            local end=$(( start + MAX_FILES - 1 ))
            [[ "$end" -gt "$total_files" ]] && end="$total_files"

            local part_name="${index_name}-part${part}"
            local out="$INDEXES_DIR/INDEX-${part_name}.md"

            # Extract lines start..end from tmpfile
            local parttmp
            parttmp=$(mktemp)
            sed -n "${start},${end}p" "$tmpfile" > "$parttmp"

            _write_index_file "$out" "$part_name" "$scan_path" "$plugin_name" "$source_label (part $part of $num_parts)" "$parttmp" "$start" "$end"
            write_eof "$out" "$part_name"
            INDEXED+=("$part_name")

            rm "$parttmp"
            (( part++ ))
            start=$(( end + 1 ))
        done
    fi

    rm "$tmpfile"
}

# =============================================================================
# Internal: write one index file from a list of filepaths
# =============================================================================
_write_index_file() {
    local out="$1"
    local index_name="$2"
    local scan_path="$3"
    local plugin_name="$4"
    local source_label="$5"
    local filelist="$6"  # temp file with one filepath per line

    cat > "$out" << EOF
# INDEX -- ${index_name}
# Generated  : $(date '+%Y-%m-%d %H:%M')
# Source     : ${REPO_WEB}/tree/main/plugins/${source_label}
# Raw base   : ${REPO_BASE}/plugins/${plugin_name}
#
# Usage: paste any raw URL below into Claude web_fetch to read that file.
# -----------------------------------------------------------------------------

EOF

    while IFS= read -r filepath; do
        [[ -z "$filepath" ]] && continue
        local rel="${filepath#$scan_path/}"
        local plugin_root="$PLUGINS_DIR/$plugin_name"
        local url_rel="${filepath#$plugin_root/}"
        local filename
        filename=$(basename "$rel")
        local raw_url="${REPO_BASE}/plugins/${plugin_name}/${url_rel}"

        local hint=""
        case "$filename" in
            class-wc-order*.php)         hint=" -- Order class" ;;
            class-wc-product*.php)       hint=" -- Product class" ;;
            class-wc-subscription*.php)  hint=" -- Subscription class" ;;
            class-wc-customer*.php)      hint=" -- Customer class" ;;
            class-wc-cart*.php)          hint=" -- Cart class" ;;
            class-wc-checkout*.php)      hint=" -- Checkout class" ;;
            class-wc-payment*.php)       hint=" -- Payment/Gateway class" ;;
            class-wc-email*.php)         hint=" -- Email class" ;;
            class-wc-rest-*.php)         hint=" -- REST API" ;;
            *data-store*.php)            hint=" -- Data store (HPOS)" ;;
            *webhook*.php)               hint=" -- Webhook handler" ;;
            *stripe*.php)                hint=" -- Stripe integration" ;;
            *admin*.php)                 hint=" -- Admin screen" ;;
            *cron*.php|*scheduler*.php)  hint=" -- Cron/Scheduler" ;;
            *ajax*.php)                  hint=" -- AJAX handler" ;;
            *template*.php)              hint=" -- Template" ;;
            *shortcode*.php)             hint=" -- Shortcode" ;;
            *widget*.php)                hint=" -- Widget" ;;
            *abstract*.php)              hint=" -- Abstract class" ;;
            *interface*.php)             hint=" -- Interface" ;;
            *trait*.php)                 hint=" -- Trait" ;;
        esac

        echo "- \`${rel}\`${hint} | ${raw_url}" >> "$out"
    done < "$filelist"
}

# =============================================================================
# Rebuild global INDEX.md
# =============================================================================
rebuild_global_index() {
    echo ""
    echo "-> Rebuilding INDEX.md..."

    cat > "$GLOBAL_INDEX" << EOF
# INDEX -- esc-plugins-to-analyze (Global)
# Generated : $(date '+%Y-%m-%d %H:%M')
# Repo      : ${REPO_WEB}
#
# Entry point for Claude.
# Read this file first, then fetch the INDEX-{name}.md for the plugin you need.
# -----------------------------------------------------------------------------

## Repo structure

esc-plugins-to-analyze/
  INDEX.md        <- you are here (global entry point)
  indexes/        <- INDEX files per plugin (may be split into parts)
  plugins/        <- plugin source code
  themes/         <- theme source code (future)

## Available indexes

EOF

    local total=0
    for index_file in "$INDEXES_DIR"/INDEX-*.md; do
        [[ -f "$index_file" ]] || continue
        local fname plugin_name count raw_index_url
        fname=$(basename "$index_file")
        plugin_name="${fname#INDEX-}"
        plugin_name="${plugin_name%.md}"
        count=$(grep -c "^- \`" "$index_file" 2>/dev/null || echo "?")
        raw_index_url="${REPO_BASE}/indexes/INDEX-${plugin_name}.md"

        echo "### ${plugin_name}" >> "$GLOBAL_INDEX"
        echo "- PHP files : ${count}" >> "$GLOBAL_INDEX"
        echo "- URL       : ${raw_index_url}" >> "$GLOBAL_INDEX"
        echo "" >> "$GLOBAL_INDEX"
        (( total++ )) || true
    done

    cat >> "$GLOBAL_INDEX" << EOF

## How to use

1. Read this INDEX.md to discover available indexes
2. Fetch the INDEX-{name}.md url for the plugin/section you need
3. Confirm the EOF flag at the bottom -- if missing, file was truncated
4. Fetch the raw url of any file listed to read its source code

EOF

    echo "  OK INDEX.md updated -- ${total} indexes listed"
}

# =============================================================================
# Main
# =============================================================================
INDEXED=()

if [[ "$MODE" == "all" ]]; then
    if [[ ! -d "$PLUGINS_DIR" ]]; then
        echo "ERROR: plugins/ not found at $PLUGINS_DIR"
        exit 1
    fi
    for dir in "$PLUGINS_DIR"/*/; do
        [[ -d "$dir" ]] || continue
        plugin_name=$(basename "${dir%/}")
        index_directory "${dir%/}" "$plugin_name" "$plugin_name" "$plugin_name"
    done

elif [[ "$MODE" == "plugin" ]]; then
    plugin_name=$(basename "$TARGET")
    index_directory "$TARGET" "$plugin_name" "$plugin_name" "$plugin_name"

elif [[ "$MODE" == "subdir" ]]; then
    subdir_name=$(basename "$TARGET")
    plugin_name=$(basename "$(dirname "$TARGET")")
    index_name="${plugin_name}-${subdir_name}"
    index_directory "$TARGET" "$index_name" "$plugin_name" "${plugin_name}/${subdir_name}"
fi

rebuild_global_index

echo ""
echo "========================================================"
echo " Done."
echo ""
echo " Generated indexes:"
for n in "${INDEXED[@]}"; do
    count=$(grep -c "^- \`" "$INDEXES_DIR/INDEX-${n}.md" 2>/dev/null || echo "?")
    echo "   INDEX-${n}.md  (${count} files)"
done
echo ""
echo " Next steps:"
echo "   cd $REPO_ROOT"
echo "   git add INDEX.md indexes/"
echo "   git commit -m 'Update indexes'"
echo "   git push"
echo "========================================================"