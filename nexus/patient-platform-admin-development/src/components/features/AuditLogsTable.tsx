import { useState } from "react";
import { useQueries } from "@tanstack/react-query";
import { startOfDay, endOfDay } from "date-fns";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Calendar } from "@/components/ui/calendar";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Skeleton } from "@/components/ui/skeleton";
import { supabase } from "@/integrations/supabase/client";
import { cn } from "@/lib/utils";
import { DATE_FORMATS, PAGINATION } from "@/lib/constants";
import {
  Search,
  Eye,
  ChevronLeft,
  ChevronRight,
  Clock,
  User,
  FileText,
  ArrowRight,
  CalendarIcon,
  X,
} from "lucide-react";
import type { Json } from "@/integrations/supabase/types";
import { dateTime } from "@/lib/dayjs";

const IGNORED_DIFF_KEYS = new Set(["created_at", "updated_at"]);

interface AuditLogsTableProps {
  /** 'tenant' filters by tenant_id, 'platform' filters for NULL tenant_id */
  scope: "tenant" | "platform";
  /** Required when scope is 'tenant' */
  tenantId?: string;
  title?: string;
  description?: string;
  showHeader?: boolean;
}

function getActionBadgeVariant(
  action: string,
): "default" | "secondary" | "destructive" | "outline" {
  const lowerAction = action.toLowerCase();
  if (lowerAction.includes("create") || lowerAction.includes("add"))
    return "default";
  if (lowerAction.includes("update") || lowerAction.includes("edit"))
    return "secondary";
  if (lowerAction.includes("delete") || lowerAction.includes("remove"))
    return "destructive";
  return "outline";
}

function isJsonRecord(value: Json | unknown): value is Record<string, unknown> {
  return !!value && typeof value === "object" && !Array.isArray(value);
}

function buildDiffEntries(
  before: Json | null,
  after: Json | null,
  diff: Json | null,
): Array<[string, { before?: unknown; after?: unknown }]> {
  if (isJsonRecord(diff)) {
    const entries = Object.entries(diff)
      .filter(([key]) => !IGNORED_DIFF_KEYS.has(key))
      .map(([key, value]) => [
        key,
        isJsonRecord(value)
          ? { before: value.before, after: value.after }
          : { before: undefined, after: value },
      ] as [string, { before?: unknown; after?: unknown }]);

    if (entries.length > 0) return entries;
  }

  if (!isJsonRecord(before) || !isJsonRecord(after)) return [];

  const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
  return [...keys]
    .filter((key) => !IGNORED_DIFF_KEYS.has(key))
    .filter((key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]))
    .map((key) => [
      key,
      { before: before[key], after: after[key] },
    ]);
}

function formatFieldLabel(key: string) {
  return key.replace(/_/g, " ");
}

function formatAuditValue(value: unknown) {
  if (value === null || value === undefined || value === "") return "None";
  if (typeof value === "boolean") return value ? "Enabled" : "Disabled";
  if (Array.isArray(value)) return value.length ? value.join(", ") : "None";
  if (typeof value === "object") return JSON.stringify(value, null, 2);
  return String(value);
}

function DiffViewer({
  before,
  after,
  diff,
}: {
  before: Json | null;
  after: Json | null;
  diff: Json | null;
}) {
  const diffEntries = buildDiffEntries(before, after, diff);

  if (diffEntries.length > 0) {
    return (
      <div className="space-y-3">
        <h4 className="font-medium text-sm">Changes</h4>
        <div className="space-y-2">
          {diffEntries.map(([key, change]) => (
            <div key={key} className="rounded-md border p-3">
              <p className="text-sm font-medium capitalize mb-2">
                {formatFieldLabel(key)}
              </p>
              <div className="flex items-center gap-2 text-sm">
                <span className="px-2 py-1 bg-destructive/10 text-destructive rounded text-xs font-mono break-all whitespace-pre-wrap">
                  {formatAuditValue(change.before)}
                </span>
                <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                <span className="px-2 py-1 bg-green-500/10 text-green-700 dark:text-green-400 rounded text-xs font-mono break-all whitespace-pre-wrap">
                  {formatAuditValue(change.after)}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 gap-4">
      <div>
        <h4 className="font-medium text-sm mb-2">Before</h4>
        <pre className="text-xs bg-muted p-3 rounded-md overflow-auto max-h-64">
          {before ? JSON.stringify(before, null, 2) : "N/A"}
        </pre>
      </div>
      <div>
        <h4 className="font-medium text-sm mb-2">After</h4>
        <pre className="text-xs bg-muted p-3 rounded-md overflow-auto max-h-64">
          {after ? JSON.stringify(after, null, 2) : "N/A"}
        </pre>
      </div>
    </div>
  );
}

export function AuditLogsTable({
  scope,
  tenantId,
  title = "Activity History",
  description = "Track who changed what and when",
  showHeader = true,
}: AuditLogsTableProps) {
  const [search, setSearch] = useState("");
  const [entityTypeFilter, setEntityTypeFilter] = useState<string>("all");
  const [startDate, setStartDate] = useState<Date | undefined>(undefined);
  const [endDate, setEndDate] = useState<Date | undefined>(undefined);
  const [page, setPage] = useState(1);
  const pageSize = PAGINATION.DEFAULT_PAGE_SIZE;

  const queryEnabled = scope === "platform" || !!tenantId;

  const [{ data, isLoading, error }, { data: entityTypes }] = useQueries({
    queries: [
      {
        queryKey: [
          "audit-logs",
          scope,
          tenantId,
          page,
          search,
          entityTypeFilter,
          startDate,
          endDate,
        ],
        queryFn: async () => {
          let query = supabase
            .from("audit_logs")
            .select("*", { count: "exact" })
            .order("created_at", { ascending: false });

          // Scope filtering
          if (scope === "tenant" && tenantId) {
            query = query.eq("tenant_id", tenantId);
          } else if (scope === "platform") {
            query = query.is("tenant_id", null);
          }

          if (search) {
            query = query.or(
              `action.ilike.%${search}%,entity_type.ilike.%${search}%,actor_email.ilike.%${search}%`,
            );
          }

          if (entityTypeFilter && entityTypeFilter !== "all") {
            query = query.eq("entity_type", entityTypeFilter);
          }

          if (startDate) {
            query = query.gte(
              "created_at",
              startOfDay(startDate).toISOString(),
            );
          }

          if (endDate) {
            query = query.lte("created_at", endOfDay(endDate).toISOString());
          }

          const from = (page - 1) * pageSize;
          const to = from + pageSize - 1;
          query = query.range(from, to);

          const { data: logs, error, count } = await query;

          if (error) throw error;

          return { logs: logs as AuditLog[], count: count || 0 };
        },
        enabled: queryEnabled,
      },
      {
        // Get unique entity types for filter
        queryKey: ["audit-log-entity-types", scope, tenantId],
        queryFn: async () => {
          let query = supabase.from("audit_logs").select("entity_type");

          if (scope === "tenant" && tenantId) {
            query = query.eq("tenant_id", tenantId);
          } else if (scope === "platform") {
            query = query.is("tenant_id", null);
          }

          const { data, error } = await query;

          if (error) throw error;

          const unique = [...new Set(data.map((d) => d.entity_type))];
          return unique.sort();
        },
        enabled: queryEnabled,
      },
    ],
  });

  const totalPages = Math.ceil((data?.count || 0) / pageSize);

  const clearDateFilters = () => {
    setStartDate(undefined);
    setEndDate(undefined);
    setPage(1);
  };

  const hasDateFilters = startDate || endDate;

  return (
    <Card>
      {showHeader && (
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
      )}
      <CardContent>
        {/* Filters */}
        <div className="space-y-4 mb-6">
          {/* Row 1: Search and Entity Type */}
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by action, entity, or actor..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                className="pl-9"
              />
            </div>
            <Select
              value={entityTypeFilter}
              onValueChange={(value) => {
                setEntityTypeFilter(value);
                setPage(1);
              }}
            >
              <SelectTrigger className="w-full sm:w-48">
                <SelectValue placeholder="Filter by entity" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Entities</SelectItem>
                {entityTypes?.map((type) => (
                  <SelectItem key={type} value={type}>
                    {type}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Row 2: Date Range Filters */}
          <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <div className="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
              <span className="text-sm text-muted-foreground whitespace-nowrap">
                Date range:
              </span>

              {/* Start Date */}
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      "w-full sm:w-[160px] justify-start text-left font-normal",
                      !startDate && "text-muted-foreground",
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {startDate
                      ? dateTime(startDate).format("MMM D, YYYY")
                      : "Start date"}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar
                    mode="single"
                    selected={startDate}
                    onSelect={(date) => {
                      setStartDate(date);
                      setPage(1);
                    }}
                    disabled={(date) =>
                      (endDate ? date > endDate : false) ||
                      date > dateTime().toDate()
                    }
                    initialFocus
                    className={cn("p-3 pointer-events-auto")}
                  />
                </PopoverContent>
              </Popover>

              <span className="text-sm text-muted-foreground hidden sm:inline">
                to
              </span>

              {/* End Date */}
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      "w-full sm:w-[160px] justify-start text-left font-normal",
                      !endDate && "text-muted-foreground",
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {endDate
                      ? dateTime(endDate).format("MMM D, YYYY")
                      : "End date"}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar
                    mode="single"
                    selected={endDate}
                    onSelect={(date) => {
                      setEndDate(date);
                      setPage(1);
                    }}
                    disabled={(date) =>
                      (startDate ? date < startDate : false) ||
                      date > dateTime().toDate()
                    }
                    initialFocus
                    className={cn("p-3 pointer-events-auto")}
                  />
                </PopoverContent>
              </Popover>

              {hasDateFilters && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={clearDateFilters}
                  className="h-9 px-2"
                >
                  <X className="h-4 w-4 mr-1" />
                  Clear dates
                </Button>
              )}
            </div>
          </div>
        </div>

        {/* Table */}
        {isLoading ? (
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : error ? (
          <div className="text-center py-8 text-destructive">
            Failed to load audit logs. Please try again.
          </div>
        ) : !data?.logs.length ? (
          <div className="text-center py-8 text-muted-foreground">
            No audit logs found.
          </div>
        ) : (
          <>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Timestamp</TableHead>
                    <TableHead>Actor</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>Entity</TableHead>
                    <TableHead className="w-[100px]">Details</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.logs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell className="whitespace-nowrap">
                        <div className="flex items-center gap-2">
                          <Clock className="h-4 w-4 text-muted-foreground" />
                          <span className="text-sm">
                            {dateTime(log.created_at).format(
                              DATE_FORMATS.DISPLAY_WITH_TIME,
                            )}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <User className="h-4 w-4 text-muted-foreground" />
                          <span className="text-sm truncate max-w-[200px]">
                            {log.actor_email || "System"}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant={getActionBadgeVariant(log.action)}>
                          {log.action}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <FileText className="h-4 w-4 text-muted-foreground" />
                          <span className="font-medium">{log.entity_type}</span>
                          {log.entity_id && (
                            <span className="text-xs text-muted-foreground font-mono">
                              ({log.entity_id.slice(0, 8)}...)
                            </span>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Dialog>
                          <DialogTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <Eye className="h-4 w-4" />
                            </Button>
                          </DialogTrigger>
                          <DialogContent className="max-w-2xl">
                            <DialogHeader>
                              <DialogTitle>Audit Log Details</DialogTitle>
                              <DialogDescription>
                                {dateTime(log.created_at).format(
                                  DATE_FORMATS.DISPLAY_WITH_TIME,
                                )}
                              </DialogDescription>
                            </DialogHeader>
                            <ScrollArea className="max-h-[60vh]">
                              <div className="space-y-4 p-1">
                                <div className="grid grid-cols-2 gap-4">
                                  <div>
                                    <p className="text-sm text-muted-foreground">
                                      Actor
                                    </p>
                                    <p className="font-medium">
                                      {log.actor_email || "System"}
                                    </p>
                                  </div>
                                  <div>
                                    <p className="text-sm text-muted-foreground">
                                      Action
                                    </p>
                                    <Badge
                                      variant={getActionBadgeVariant(
                                        log.action,
                                      )}
                                    >
                                      {log.action}
                                    </Badge>
                                  </div>
                                  <div>
                                    <p className="text-sm text-muted-foreground">
                                      Entity Type
                                    </p>
                                    <p className="font-medium">
                                      {log.entity_type}
                                    </p>
                                  </div>
                                  <div>
                                    <p className="text-sm text-muted-foreground">
                                      Entity ID
                                    </p>
                                    <p className="font-mono text-sm">
                                      {log.entity_id || "N/A"}
                                    </p>
                                  </div>
                                  {log.request_id && (
                                    <div className="col-span-2">
                                      <p className="text-sm text-muted-foreground">
                                        Request ID
                                      </p>
                                      <p className="font-mono text-xs">
                                        {log.request_id}
                                      </p>
                                    </div>
                                  )}
                                </div>

                                {(log.before_data ||
                                  log.after_data ||
                                  log.diff) && (
                                  <div className="pt-4 border-t">
                                    <DiffViewer
                                      before={log.before_data}
                                      after={log.after_data}
                                      diff={log.diff}
                                    />
                                  </div>
                                )}
                              </div>
                            </ScrollArea>
                          </DialogContent>
                        </Dialog>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-between mt-4">
                <p className="text-sm text-muted-foreground">
                  Showing {(page - 1) * pageSize + 1} to{" "}
                  {Math.min(page * pageSize, data.count)} of {data.count}{" "}
                  entries
                </p>
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    disabled={page === 1}
                  >
                    <ChevronLeft className="h-4 w-4" />
                    Previous
                  </Button>
                  <span className="text-sm">
                    Page {page} of {totalPages}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                    disabled={page === totalPages}
                  >
                    Next
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
}
