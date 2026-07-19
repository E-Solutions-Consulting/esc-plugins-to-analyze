/**
 * Patch order_status_history from mrb_comments (WooCommerce order notes).
 *
 * Reads "Order status changed from X to Y." entries from the mrb_comments
 * GCS export and updates order_status_history in Supabase — without running
 * a full Phase 2 re-import.
 *
 * Usage:
 *   deno run --allow-run --allow-env --allow-net \
 *     scripts/patch-order-timeline-from-mrb-comments.ts \
 *     [--tenant-slug brello] [--dry-run] [--woo-order-ids 1234,5678]
 *
 * Requires:
 *   SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY env vars (or .env.local)
 *   Active gcloud auth (for gsutil access to allia-woocommerce-raw-prod)
 */

import { createClient } from "jsr:@supabase/supabase-js@2";
import { parse as parseCsv } from "jsr:@std/csv@1";

// ---------------------------------------------------------------------------
// WC status name → PP status key (mirrors brelloMigrationPhase2 mapping)
// ---------------------------------------------------------------------------
const WC_STATUS_NAME_TO_PP_KEY = new Map<string, string>([
  ["processing", "payment_collected"],
  ["completed", "delivered"],
  ["cancelled", "order_cancelled"],
  ["failed", "payment_failed"],
  ["failed payment", "payment_failed"],
  ["on hold", "order_on_hold"],
  ["pending payment", "payment_pending"],
  ["pending", "payment_pending"],
  ["send to telegra", "order_sent_to_pharmacy"],
  ["provider review", "provider_review_pending"],
  ["admin review", "provider_review_pending"],
  ["error review", "provider_review_pending"],
  ["collect payment", "payment_pending"],
  ["prerequisites", "payment_collected"],
  ["waiting room", "payment_collected"],
  ["pending cancel", "order_pending_cancellation"],
]);

const WC_STATUS_CHANGE_RE = /Order status changed from .+ to (.+)\./i;

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------
function parseArgs() {
  const args = Deno.args;
  const get = (flag: string) => {
    const i = args.indexOf(flag);
    return i !== -1 ? args[i + 1] : undefined;
  };
  return {
    tenantSlug: get("--tenant-slug") ?? "brello",
    dryRun: args.includes("--dry-run"),
    wooOrderIdFilter: get("--woo-order-ids")
      ? new Set(get("--woo-order-ids")!.split(",").map((s) => s.trim()))
      : null,
    gcsBucket: get("--gcs-bucket") ?? "allia-woocommerce-raw-prod",
    batchSize: parseInt(get("--batch-size") ?? "50", 10),
  };
}

// ---------------------------------------------------------------------------
// GCS helpers (via gsutil subprocess)
// ---------------------------------------------------------------------------
async function gsutilRun(...args: string[]): Promise<string> {
  const cmd = new Deno.Command("gsutil", { args, stdout: "piped", stderr: "piped" });
  const { code, stdout, stderr } = await cmd.output();
  if (code !== 0) {
    throw new Error(`gsutil ${args.join(" ")} failed: ${new TextDecoder().decode(stderr)}`);
  }
  return new TextDecoder().decode(stdout);
}

async function findLatestGcsFile(bucket: string, prefix: string): Promise<string> {
  const listing = await gsutilRun("ls", `gs://${bucket}/${prefix}`);
  const files = listing.trim().split("\n").filter(Boolean);
  if (files.length === 0) throw new Error(`No files found at gs://${bucket}/${prefix}`);
  files.sort();
  return files[files.length - 1];
}

async function downloadGzCsv(gcsUri: string): Promise<string> {
  console.log(`Downloading ${gcsUri} …`);
  const cmd = new Deno.Command("bash", {
    args: ["-c", `gsutil cat "${gcsUri}" | gunzip`],
    stdout: "piped",
    stderr: "piped",
  });
  const { code, stdout, stderr } = await cmd.output();
  if (code !== 0) {
    throw new Error(`Download failed: ${new TextDecoder().decode(stderr)}`);
  }
  return new TextDecoder().decode(stdout);
}

// ---------------------------------------------------------------------------
// Parse mrb_comments CSV into woo_order_id → timeline entries
// ---------------------------------------------------------------------------
interface CommentEntry {
  comment_id: string;
  order_id: string;
  pp_status_key: string;
  raw_to_status: string;
  occurred_at: string | null;
  note: string;
}

function parseMrbComments(
  csvText: string,
  orderIdFilter: Set<string> | null,
): Map<string, CommentEntry[]> {
  const rows = parseCsv(csvText, { skipFirstRow: true, columns: undefined });
  const byOrderId = new Map<string, CommentEntry[]>();
  let skipped = 0;
  let kept = 0;

  for (const row of rows as Record<string, string>[]) {
    const orderId = String(row["comment_post_ID"] ?? "").trim();
    const commentId = String(row["comment_ID"] ?? "").trim();
    const content = String(row["comment_content"] ?? "").trim();

    if (!orderId || !commentId) { skipped++; continue; }
    if (orderIdFilter && !orderIdFilter.has(orderId)) { skipped++; continue; }

    const match = WC_STATUS_CHANGE_RE.exec(content);
    if (!match) { skipped++; continue; }

    const toStatusName = match[1].trim().toLowerCase();
    const ppStatusKey = WC_STATUS_NAME_TO_PP_KEY.get(toStatusName);
    if (!ppStatusKey) { skipped++; continue; }

    const dateStr = String(row["comment_date_gmt"] ?? "").trim();
    const occurredAt = dateStr ? new Date(dateStr).toISOString() : null;

    if (!byOrderId.has(orderId)) byOrderId.set(orderId, []);
    byOrderId.get(orderId)!.push({
      comment_id: commentId,
      order_id: orderId,
      pp_status_key: ppStatusKey,
      raw_to_status: toStatusName,
      occurred_at: occurredAt,
      note: content,
    });
    kept++;
  }

  console.log(`Parsed mrb_comments: ${kept} status-change entries kept, ${skipped} skipped`);
  console.log(`Orders with status history in comments: ${byOrderId.size}`);
  return byOrderId;
}

// ---------------------------------------------------------------------------
// Supabase helpers
// ---------------------------------------------------------------------------
// deno-lint-ignore no-explicit-any
type AnySupabase = any;

async function loadStatusKeyToIdMap(supabase: AnySupabase) {
  const { data, error } = await supabase
    .from("order_statuses")
    .select("id, status_key")
    .eq("is_active", true);
  if (error || !data) throw new Error(`Failed to load order_statuses: ${error?.message}`);
  return new Map<string, string>(
    (data as { status_key: string; id: string }[]).map((r) => [r.status_key, r.id]),
  );
}

async function loadTenantId(supabase: AnySupabase, slug: string): Promise<string> {
  const { data, error } = await supabase
    .from("tenants").select("id").eq("slug", slug).single();
  if (error || !data) throw new Error(`Tenant "${slug}" not found: ${error?.message}`);
  return (data as { id: string }).id;
}

// Load orders in chunks to avoid query size limits
async function loadOrdersByWooIds(
  supabase: AnySupabase,
  tenantId: string,
  wooOrderIds: string[],
  chunkSize = 200,
): Promise<Map<string, { id: string; woo_order_id: string }>> {
  const result = new Map<string, { id: string; woo_order_id: string }>();
  for (let i = 0; i < wooOrderIds.length; i += chunkSize) {
    const chunk = wooOrderIds.slice(i, i + chunkSize);
    const { data, error } = await supabase
      .from("orders")
      .select("id, metadata")
      .eq("tenant_id", tenantId)
      .in("metadata->>woo_order_id", chunk);
    if (error) throw new Error(`Orders query failed: ${error.message}`);
    for (const row of (data ?? []) as { id: string; metadata: Record<string, string> }[]) {
      const wooId = String(row.metadata?.woo_order_id ?? "");
      if (wooId) result.set(wooId, { id: row.id, woo_order_id: wooId });
    }
  }
  return result;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function main() {
  const { tenantSlug, dryRun, wooOrderIdFilter, gcsBucket, batchSize } = parseArgs();

  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!supabaseUrl || !serviceKey) {
    throw new Error("SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY must be set");
  }

  const supabase = createClient(supabaseUrl, serviceKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  console.log(`\n=== mrb_comments order timeline patch ===`);
  console.log(`Tenant: ${tenantSlug} | Dry-run: ${dryRun}`);
  if (wooOrderIdFilter) console.log(`Scoped to ${wooOrderIdFilter.size} WC order IDs`);

  // Step 1: download mrb_comments from GCS
  const latestFile = await findLatestGcsFile(gcsBucket, "data/mrb_comments/");
  const csvText = await downloadGzCsv(latestFile);

  // Step 2: parse into per-order entries
  const commentsByOrderId = parseMrbComments(csvText, wooOrderIdFilter);
  if (commentsByOrderId.size === 0) {
    console.log("No matching entries — nothing to do.");
    return;
  }

  // Step 3: load Supabase references
  const [statusKeyToId, tenantId] = await Promise.all([
    loadStatusKeyToIdMap(supabase),
    loadTenantId(supabase, tenantSlug),
  ]);
  console.log(`Tenant ID: ${tenantId}`);
  console.log(`Status keys available: ${statusKeyToId.size}`);

  // Step 4: find which woo_order_ids have matching orders in the DB
  const allWooIds = Array.from(commentsByOrderId.keys());
  console.log(`Looking up ${allWooIds.length} WC order IDs in Supabase…`);
  const ordersByWooId = await loadOrdersByWooIds(supabase, tenantId, allWooIds);
  console.log(`Matched ${ordersByWooId.size} orders in DB`);

  // Step 5: process each matched order
  const stats = { updated: 0, skipped_no_db_entry: 0, skipped_no_valid_statuses: 0, failed: 0 };
  const wooIdsToProcess = Array.from(ordersByWooId.keys());

  for (let i = 0; i < wooIdsToProcess.length; i += batchSize) {
    const chunk = wooIdsToProcess.slice(i, i + batchSize);
    const promises = chunk.map(async (wooId) => {
      const order = ordersByWooId.get(wooId)!;
      const comments = commentsByOrderId.get(wooId)!;

      // Deduplicate: keep first occurrence of each pp_status_key, sorted by occurred_at
      const sortedComments = [...comments].sort((a, b) => {
        if (!a.occurred_at) return -1;
        if (!b.occurred_at) return 1;
        return a.occurred_at < b.occurred_at ? -1 : a.occurred_at > b.occurred_at ? 1 : 0;
      });
      const seenKeys = new Set<string>();
      const historyEntries: { order_id: string; status_id: string; notes: string; created_at: string }[] = [];

      for (const entry of sortedComments) {
        if (seenKeys.has(entry.pp_status_key)) continue;
        const statusId = statusKeyToId.get(entry.pp_status_key);
        if (!statusId) continue;
        seenKeys.add(entry.pp_status_key);
        historyEntries.push({
          order_id: order.id,
          status_id: statusId,
          notes: entry.note,
          created_at: entry.occurred_at ?? new Date().toISOString(),
        });
      }

      if (historyEntries.length === 0) {
        stats.skipped_no_valid_statuses++;
        return;
      }

      if (dryRun) {
        console.log(`[DRY-RUN] Would update order ${wooId} (${order.id}) with ${historyEntries.length} timeline entries`);
        stats.updated++;
        return;
      }

      try {
        const { error: delError } = await supabase
          .from("order_status_history")
          .delete()
          .eq("order_id", order.id);
        if (delError) throw new Error(`Delete failed: ${delError.message}`);

        const { error: insError } = await supabase
          .from("order_status_history")
          .insert(historyEntries);
        if (insError) throw new Error(`Insert failed: ${insError.message}`);

        stats.updated++;
      } catch (err) {
        console.error(`Failed for woo_order ${wooId}:`, (err as Error).message);
        stats.failed++;
      }
    });

    await Promise.all(promises);

    if ((i + batchSize) % 500 === 0) {
      console.log(`Progress: ${i + chunk.length}/${wooIdsToProcess.length} processed`);
    }
  }

  console.log(`\n=== Done ===`);
  console.log(`Orders updated:               ${stats.updated}`);
  console.log(`Skipped (no DB entry):        ${stats.skipped_no_db_entry}`);
  console.log(`Skipped (no valid statuses):  ${stats.skipped_no_valid_statuses}`);
  console.log(`Failed:                       ${stats.failed}`);
  if (dryRun) console.log(`\n(dry-run — no changes written)`);
}

main().catch((err) => {
  console.error("Fatal:", err.message);
  Deno.exit(1);
});
