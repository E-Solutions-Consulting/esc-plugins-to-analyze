export type MigrationEntityType = "patient" | "order" | "subscription";

export type MigrationStatusKey =
  | "not_migrated"
  | "migrated"
  | "stub_created"
  | "backfilled"
  | "product_unresolved"
  | "billing_handoff_pending"
  | "pp_managed_billing";

export interface MigrationInfo {
  isMigrated: boolean;
  status: MigrationStatusKey;
  label: string;
  date: string | null;
  dateLabel: string | null;
  sourceSystem: string | null;
  sourceId: string | null;
  warnings: {
    unresolvedProduct: boolean;
    billingHandoffPending: boolean;
  };
}

const STATUS_LABELS: Record<MigrationStatusKey, string> = {
  not_migrated: "Not migrated",
  migrated: "Migrated",
  stub_created: "Stub created",
  backfilled: "Backfilled",
  product_unresolved: "Product unresolved",
  billing_handoff_pending: "Billing handoff pending",
  pp_managed_billing: "PP-managed billing",
};

const SOURCE_ID_KEYS_BY_ENTITY: Record<MigrationEntityType, string[]> = {
  patient: [
    "legacy_brello_uid",
    "woo_customer_id",
    "source_id",
    "sourceId",
    "migration_source_id",
    "external_id",
  ],
  order: [
    "woo_order_id",
    "woo_parent_order_id",
    "source_order_id",
    "source_id",
    "sourceId",
    "migration_source_id",
    "external_id",
  ],
  subscription: [
    "woo_subscription_id",
    "source_subscription_id",
    "source_id",
    "sourceId",
    "migration_source_id",
    "external_id",
  ],
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function firstString(
  record: Record<string, unknown>,
  keys: string[],
): string | null {
  for (const key of keys) {
    const value = record[key];
    if (typeof value === "string" && value.trim()) return value.trim();
    if (typeof value === "number" && Number.isFinite(value)) {
      return String(value);
    }
  }

  return null;
}

function firstBoolean(record: Record<string, unknown>, keys: string[]) {
  return keys.some((key) => record[key] === true);
}

function firstFalseBoolean(record: Record<string, unknown>, keys: string[]) {
  return keys.some((key) => record[key] === false);
}

function normalizedString(value: unknown) {
  return typeof value === "string" ? value.trim().toLowerCase() : "";
}

function parseMigrationPhase(value: unknown) {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value !== "string" || !value.trim()) return null;

  const phase = Number(value);
  return Number.isFinite(phase) ? phase : null;
}

function hasStatusValue(
  metadata: Record<string, unknown>,
  values: string[],
): boolean {
  const normalizedValues = new Set(values);
  const keys = [
    "migration_status",
    "status",
    "migration_step",
    "billing_status",
    "billing_handoff_status",
    "billing_owner",
    "billing_managed_by",
  ];

  return keys.some((key) => {
    const value = normalizedString(metadata[key]).replace(/\s+/g, "_");
    return value && normalizedValues.has(value);
  });
}

function firstStringFromRecords(
  records: Record<string, unknown>[],
  keys: string[],
) {
  for (const record of records) {
    const value = firstString(record, keys);
    if (value) return value;
  }

  return null;
}

function firstBooleanFromRecords(
  records: Record<string, unknown>[],
  keys: string[],
) {
  return records.some((record) => firstBoolean(record, keys));
}

function firstFalseBooleanFromRecords(
  records: Record<string, unknown>[],
  keys: string[],
) {
  return records.some((record) => firstFalseBoolean(record, keys));
}

function hasStatusValueFromRecords(
  records: Record<string, unknown>[],
  values: string[],
) {
  return records.some((record) => hasStatusValue(record, values));
}

function getPhaseTwoMetadata(metadata: Record<string, unknown>) {
  const phaseTwo = metadata.migration_phase_2 ?? metadata.migration_phase2;
  return isRecord(phaseTwo) ? phaseTwo : null;
}

function getPhaseMetadata(metadata: Record<string, unknown>, phase: number) {
  const phaseMetadata =
    metadata[`migration_phase_${phase}`] ?? metadata[`migration_phase${phase}`];
  return isRecord(phaseMetadata) ? phaseMetadata : null;
}

function getNestedRecord(metadata: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = metadata[key];
    if (isRecord(value)) return value;
  }

  return null;
}

function getMigrationDate(metadata: Record<string, unknown>) {
  for (const phase of [4, 3, 2, 1]) {
    const phaseMetadata = getPhaseMetadata(metadata, phase);
    const phaseImportedAt = phaseMetadata
      ? firstString(phaseMetadata, [
          "imported_at",
          "migrated_at",
          "backfilled_at",
        ])
      : null;

    if (phaseImportedAt) {
      return { date: phaseImportedAt, dateLabel: "Migration Date" };
    }
  }

  const importedAt = firstString(metadata, [
    "imported_at",
    "migration_imported_at",
    "migrated_at",
    "backfilled_at",
  ]);

  if (!importedAt) return { date: null, dateLabel: null };

  return {
    date: importedAt,
    dateLabel: "Migration Date",
  };
}

export function deriveMigrationInfo(
  metadata: unknown,
  entityType: MigrationEntityType,
): MigrationInfo {
  const safeMetadata = isRecord(metadata) ? metadata : {};
  const phaseTwo = getPhaseTwoMetadata(safeMetadata);
  const phaseThree = getPhaseMetadata(safeMetadata, 3);
  const directPhaseFour = getPhaseMetadata(safeMetadata, 4);
  const phaseFour = getNestedRecord(safeMetadata, [
    "migration_phase_4",
    "migration_phase4",
    "billing_migration",
    "billing",
  ]);
  const metadataRecords = [safeMetadata, phaseTwo, phaseThree, phaseFour].filter(
    (record): record is Record<string, unknown> => record !== null,
  );
  const migrationPhase = parseMigrationPhase(safeMetadata.migration_phase);
  const sourceId = firstString(
    safeMetadata,
    SOURCE_ID_KEYS_BY_ENTITY[entityType],
  );
  const inferredSourceSystem =
    "woo_order_id" in safeMetadata ||
    "woo_parent_order_id" in safeMetadata ||
    "woo_subscription_id" in safeMetadata ||
    "woo_customer_id" in safeMetadata
      ? "woocommerce"
      : "legacy_brello_uid" in safeMetadata
        ? "brello"
        : null;
  const sourceSystem =
    firstStringFromRecords(metadataRecords, [
      "source_system",
      "migration_source",
      "source",
      "legacy_system",
    ]) || inferredSourceSystem;
  const migratedFromKnownKeys =
    safeMetadata.is_migrated === true ||
    migrationPhase !== null ||
    sourceId !== null ||
    phaseTwo !== null ||
    phaseThree !== null ||
    directPhaseFour !== null;

  if (entityType === "patient") {
    const { date, dateLabel } = getMigrationDate(safeMetadata);

    return {
      isMigrated: migratedFromKnownKeys,
      status: migratedFromKnownKeys ? "migrated" : "not_migrated",
      label: migratedFromKnownKeys
        ? STATUS_LABELS.migrated
        : STATUS_LABELS.not_migrated,
      date,
      dateLabel,
      sourceSystem,
      sourceId,
      warnings: {
        unresolvedProduct: false,
        billingHandoffPending: false,
      },
    };
  }

  const unresolvedProduct =
    firstBooleanFromRecords(metadataRecords, [
      "product_unresolved",
      "unresolved_product",
      "has_unresolved_product",
    ]) ||
    firstFalseBooleanFromRecords(metadataRecords, ["product_id_resolved"]) ||
    hasStatusValueFromRecords(metadataRecords, [
      "product_unresolved",
      "unresolved_product",
    ]);
  const billingHandoffPending =
    firstBooleanFromRecords(metadataRecords, [
      "billing_handoff_pending",
      "payment_handoff_pending",
    ]) ||
    hasStatusValueFromRecords(metadataRecords, [
      "billing_handoff_pending",
      "handoff_pending",
      "payment_handoff_pending",
    ]);
  const ppManagedBilling =
    firstBooleanFromRecords(metadataRecords, ["pp_managed_billing"]) ||
    hasStatusValueFromRecords(metadataRecords, [
      "pp_managed_billing",
      "patient_platform",
      "pp_managed",
    ]);

  let status: MigrationStatusKey = "not_migrated";
  if (migratedFromKnownKeys) status = "migrated";
  if (migrationPhase === 1) status = "stub_created";
  if (
    phaseTwo ||
    (migrationPhase !== null && migrationPhase >= 2) ||
    safeMetadata.backfilled_at
  ) {
    status = "backfilled";
  }
  if (phaseThree || migrationPhase === 3) status = "billing_handoff_pending";
  if (
    directPhaseFour ||
    (migrationPhase !== null && migrationPhase >= 4)
  ) {
    status = "pp_managed_billing";
  }
  if (billingHandoffPending) status = "billing_handoff_pending";
  if (ppManagedBilling) status = "pp_managed_billing";
  if (unresolvedProduct) status = "product_unresolved";

  const { date, dateLabel } = getMigrationDate(safeMetadata);

  return {
    isMigrated: status !== "not_migrated",
    status,
    label: STATUS_LABELS[status],
    date,
    dateLabel,
    sourceSystem,
    sourceId,
    warnings: {
      unresolvedProduct,
      billingHandoffPending,
    },
  };
}
