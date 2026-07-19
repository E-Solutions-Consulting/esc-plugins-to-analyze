import { Badge } from "@/components/ui/badge";
import { dateTime } from "@/lib/dayjs";
import {
  deriveMigrationInfo,
  type MigrationEntityType,
  type MigrationInfo,
} from "@/lib/migration-status";

type MigrationStatusProps = {
  metadata: unknown;
  entityType: MigrationEntityType;
  createdAt?: string | null;
  showDetails?: boolean;
};

function formatDate(value: string | null | undefined) {
  if (!value) return "—";
  const parsed = dateTime(value);
  return parsed.isValid() ? parsed.format("MMM D, YYYY") : value;
}

function getBadgeVariant(status: MigrationInfo["status"]) {
  if (status === "product_unresolved") return "destructive";
  if (status === "not_migrated") return "outline";
  if (status === "backfilled" || status === "pp_managed_billing") {
    return "default";
  }

  return "secondary";
}

export function MigrationBadge({
  metadata,
  entityType,
}: Pick<MigrationStatusProps, "metadata" | "entityType">) {
  const migration = deriveMigrationInfo(metadata, entityType);

  return (
    <Badge variant={getBadgeVariant(migration.status)}>{migration.label}</Badge>
  );
}

export function MigrationStatus({
  metadata,
  entityType,
  createdAt,
  showDetails = true,
}: MigrationStatusProps) {
  const migration = deriveMigrationInfo(metadata, entityType);
  const shouldUseCreatedAtAsTransition =
    migration.isMigrated &&
    (entityType === "patient" || entityType === "subscription");

  if (!showDetails) {
    return <MigrationBadge metadata={metadata} entityType={entityType} />;
  }

  return (
    <div className="flex flex-col gap-3 text-sm">
      <div className="flex items-center justify-between gap-4">
        <span className="text-muted-foreground">Migration Status</span>
        <MigrationBadge metadata={metadata} entityType={entityType} />
      </div>
      {migration.date ? (
        <div className="flex items-center justify-between gap-4">
          <span className="text-muted-foreground">{migration.dateLabel}</span>
          <span>{formatDate(migration.date)}</span>
        </div>
      ) : (
        createdAt && (
          <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">
              {shouldUseCreatedAtAsTransition
                ? "Migration Date"
                : "PP Created"}
            </span>
            <span>{formatDate(createdAt)}</span>
          </div>
        )
      )}
      <div className="flex items-center justify-between gap-4">
        <span className="text-muted-foreground">Source System</span>
        <span className="text-right">{migration.sourceSystem || "—"}</span>
      </div>
      <div className="flex items-center justify-between gap-4">
        <span className="text-muted-foreground">Source ID</span>
        <span className="max-w-48 break-all text-right font-mono text-xs">
          {migration.sourceId || "—"}
        </span>
      </div>
      {migration.warnings.unresolvedProduct && (
        <p className="text-sm text-destructive">
          Product mapping still needs review.
        </p>
      )}
      {migration.warnings.billingHandoffPending && (
        <p className="text-sm text-muted-foreground">
          Billing ownership handoff is still pending.
        </p>
      )}
    </div>
  );
}
