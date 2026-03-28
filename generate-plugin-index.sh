#!/bin/bash
# =============================================================================
# generate-plugin-index.sh
#
# Generates one indexes/INDEX-{plugin}.md per plugin + root INDEX.md
#
# Repo structure expected:
#   esc-plugins-to-analyze/
#   ├── INDEX.md                  <- global entry point (Claude reads this first)
#   ├── indexes/                  <- one INDEX-{plugin}.md per plugin
#   ├── plugins/                  <- plugin source code
#   └── themes/                   <- theme source code (future)
#
# Usage:
#   # Index ALL plugins inside plugins/:
#   bash generate-plugin-index.sh --all /path/to/repo
#
#   # Index a SINGLE plugin (updates its INDEX + global INDEX.md):
#   bash generate-plugin-index.sh --plugin /path/to/repo/plugins/woocommerce
#
# =============================================================================

set -e

REPO_BASE="https://raw.githubusercontent.com/E-Solutions-Consulting/esc-plugins-to-analyze/main"
REPO_WEB="https://github.com/E-Solutions-Consulting/esc-plugins-to-analyze"
MODE=""
TARGET=""
REPO_ROOT=""

# --- Parse arguments ---------------------------------------------------------
while [[ $# -gt 0 ]]; do
    case "$1" in
        --all)    MODE="all";    TARGET="$2"; shift 2 ;;
        --plugin) MODE="plugin"; TARGET="$2"; shift 2 ;;
        *) echo "Unknown argument: $1"; exit 1 ;;
    esac
done

if [[ -z "$MODE" || -z "$TARGET" ]]; then
    echo "Usage:"
    echo "  bash generate-plugin-index.sh --all /path/to/repo"
    echo "  bash generate-plugin-index.sh --plugin /path/to/repo/plugins/woocommerce"
    exit 1
fi

if [[ ! -d "$TARGET" ]]; then
    echo "ERROR: Directory not found: $TARGET"
    exit 1
fi

# Resolve repo root:
# --all    -> TARGET is the repo root
# --plugin -> TARGET is plugins/{name}, repo root is two levels up
if [[ "$MODE" == "all" ]]; then
    REPO_ROOT="$(realpath "$TARGET")"
else
    REPO_ROOT="$(realpath "$TARGET/../..")"
fi

INDEXES_DIR="$REPO_ROOT/indexes"
PLUGINS_DIR="$REPO_ROOT/plugins"
GLOBAL_INDEX="$REPO_ROOT/INDEX.md"

mkdir -p "$INDEXES_DIR"
mkdir -p "$PLUGINS_DIR"

echo "Repo root : $REPO_ROOT"
echo "Indexes   : $INDEXES_DIR"
echo ""

# =============================================================================
# Core function: index one plugin directory
# =============================================================================
index_plugin() {
    local plugin_path="$1"
    local plugin_name
    plugin_name=$(basename "$plugin_path")
    local out="$INDEXES_DIR/INDEX-${plugin_name}.md"

    echo "-> Indexing: $plugin_name"

    cat > "$out" << EOF
# INDEX -- ${plugin_name}
# Generated  : $(date '+%Y-%m-%d %H:%M')
# Source     : ${REPO_WEB}/tree/main/plugins/${plugin_name}
# Raw base   : ${REPO_BASE}/plugins/${plugin_name}
#
# Usage: paste any raw URL below into Claude web_fetch to read that file.
# -----------------------------------------------------------------------------

EOF

    local current_section=""

    while IFS= read -r filepath; do
        local rel="${filepath#$plugin_path/}"
        local dir
        dir=$(dirname "$rel")

        if [[ "$dir" != "$current_section" ]]; then
            current_section="$dir"
            if [[ "$dir" == "." ]]; then
                echo "## / (root)" >> "$out"
            else
                echo "## ${dir}/" >> "$out"
            fi
            echo "" >> "$out"
        fi

        local filename
        filename=$(basename "$rel")
        local raw_url="${REPO_BASE}/plugins/${plugin_name}/${rel}"

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

        echo "- \`${rel}\`${hint}" >> "$out"
        echo "  ${raw_url}" >> "$out"
        echo "" >> "$out"

    done < <(find "$plugin_path" -name "*.php" -type f \
                -not -path "*/vendor/*" \
                -not -name "*__*" \
             | sort)

    local count total_lines
    count=$(grep -c "^- \`" "$out" 2>/dev/null || echo 0)
    total_lines=$(wc -l < "$out")

    # EOF flag — Claude must confirm seeing this to validate full read
    cat >> "$out" << EOF

# -----------------------------------------------------------------------------
# EOF -- INDEX-${plugin_name}.md
# Total PHP files indexed : ${count}
# Total lines in this file: ${total_lines}
# Generated               : $(date '+%Y-%m-%d %H:%M')
# If you can read this line, you have read the complete index.
# -----------------------------------------------------------------------------
EOF

    echo "  OK ${count} PHP files -- ${total_lines} lines -> indexes/INDEX-${plugin_name}.md"
}

# =============================================================================
# Rebuild global INDEX.md from all INDEX files currently in indexes/
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
# Read this file first, then fetch the INDEX-{plugin}.md for the plugin you need.
# -----------------------------------------------------------------------------

## Repo structure

esc-plugins-to-analyze/
  INDEX.md        <- you are here (global entry point)
  indexes/        <- one INDEX-{plugin}.md per plugin
  plugins/        <- plugin source code
  themes/         <- theme source code (future)

## Available plugins

EOF

    local total_plugins=0
    for index_file in "$INDEXES_DIR"/INDEX-*.md; do
        [[ -f "$index_file" ]] || continue
        local fname plugin_name count raw_index_url
        fname=$(basename "$index_file")
        plugin_name="${fname#INDEX-}"
        plugin_name="${plugin_name%.md}"
        count=$(grep -c "^- \`" "$index_file" 2>/dev/null || echo "?")
        raw_index_url="${REPO_BASE}/indexes/INDEX-${plugin_name}.md"

        echo "### ${plugin_name}" >> "$GLOBAL_INDEX"
        echo "- PHP files indexed : ${count}" >> "$GLOBAL_INDEX"
        echo "- INDEX url         : ${raw_index_url}" >> "$GLOBAL_INDEX"
        echo "" >> "$GLOBAL_INDEX"
        (( total_plugins++ )) || true
    done

    cat >> "$GLOBAL_INDEX" << EOF

## How to use

1. Read this INDEX.md to discover available plugins
2. Fetch the INDEX-{plugin}.md url for the plugin you need
3. Find the relevant file(s) in that index
4. Fetch the raw url of that file to read its source
5. Confirm the EOF flag at the bottom of each INDEX --
   if missing, the file was truncated and needs to be split

EOF

    echo "  OK INDEX.md updated -- ${total_plugins} plugins listed"
}

# =============================================================================
# Main
# =============================================================================
INDEXED_PLUGINS=()

if [[ "$MODE" == "all" ]]; then
    if [[ ! -d "$PLUGINS_DIR" ]]; then
        echo "ERROR: plugins/ directory not found at $PLUGINS_DIR"
        echo "Make sure your plugins are inside $REPO_ROOT/plugins/"
        exit 1
    fi

    for dir in "$PLUGINS_DIR"/*/; do
        [[ -d "$dir" ]] || continue
        php_count=$(find "$dir" -name "*.php" -not -path "*/vendor/*" -not -name "*__*" -type f | wc -l)
        if [[ "$php_count" -gt 0 ]]; then
            index_plugin "${dir%/}"
            INDEXED_PLUGINS+=("$(basename "${dir%/}")")
        fi
    done

elif [[ "$MODE" == "plugin" ]]; then
    index_plugin "$(realpath "$TARGET")"
    INDEXED_PLUGINS+=("$(basename "$TARGET")")
fi

rebuild_global_index

# =============================================================================
# Summary
# =============================================================================
echo ""
echo "========================================================"
echo " Done."
echo ""
echo " Repo root : $REPO_ROOT"
echo ""
echo " Generated:"
echo "   $GLOBAL_INDEX"
for p in "${INDEXED_PLUGINS[@]}"; do
    echo "   $INDEXES_DIR/INDEX-${p}.md"
done
echo ""
echo " Next steps:"
echo "   cd $REPO_ROOT"
echo "   git add INDEX.md indexes/"
echo "   git commit -m 'Update indexes: ${INDEXED_PLUGINS[*]}'"
echo "   git push"
echo "========================================================"