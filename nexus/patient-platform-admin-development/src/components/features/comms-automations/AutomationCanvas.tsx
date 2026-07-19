/**
 * Vertical flow canvas (Attentive/Customer.io style): Trigger at top, node cards
 * connected top-to-bottom, with an "+" affordance between steps to insert a node.
 * Branch nodes render their labelled children side by side.
 *
 * The graph is stored as nodes + edges; for the v1 builder we render the main
 * spine by walking edges from the trigger. Branch nodes show their true/false
 * (or cohort) children inline.
 */
import { Fragment } from "react";
import { Plus } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import { NODE_META, nodeSummary, PALETTE_GROUPS } from "./node-meta";
import type { CommsEdge, CommsNode, CommsNodeType } from "@/lib/comms-automations/types";

interface AutomationCanvasProps {
  nodes: CommsNode[];
  edges: CommsEdge[];
  selectedNodeId: string | null;
  enrolledCount: number;
  onSelectNode: (id: string) => void;
  onAddAfter: (sourceNodeId: string, type: CommsNodeType, branchLabel?: string | null) => void;
}

/** Dropdown of addable node types; calls onPick with the chosen type. */
function AddNodeMenu({
  trigger,
  onPick,
}: {
  trigger: React.ReactNode;
  onPick: (type: CommsNodeType) => void;
}) {
  const addable = Object.values(NODE_META).filter((m) => m.addable);
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
      <DropdownMenuContent align="center" className="w-52">
        {PALETTE_GROUPS.map((group, gi) => {
          const items = addable.filter((m) => m.group === group.key);
          if (!items.length) return null;
          return (
            <Fragment key={group.key}>
              {gi > 0 && <DropdownMenuSeparator />}
              <DropdownMenuLabel className="text-xs">{group.label}</DropdownMenuLabel>
              {items.map((m) => (
                <DropdownMenuItem key={m.type} onClick={() => onPick(m.type)}>
                  <m.icon className={cn("h-4 w-4 mr-2", m.color)} />
                  {m.label}
                </DropdownMenuItem>
              ))}
            </Fragment>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function NodeCard({
  node,
  selected,
  enrolledCount,
  onSelect,
}: {
  node: CommsNode;
  selected: boolean;
  enrolledCount: number;
  onSelect: () => void;
}) {
  const meta = NODE_META[node.node_type];
  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        "w-64 rounded-lg border bg-card px-4 py-3 text-left shadow-sm transition",
        "hover:border-primary/60 hover:shadow",
        selected && "border-primary ring-2 ring-primary/30",
      )}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <meta.icon className={cn("h-4 w-4", meta.color)} />
          <span className="text-sm font-medium">{meta.label}</span>
        </div>
        {node.node_type === "trigger" && (
          <Badge variant="secondary" className="tabular-nums">{enrolledCount}</Badge>
        )}
      </div>
      <p className="mt-1.5 text-xs text-muted-foreground line-clamp-2">
        {node.node_type === "trigger"
          ? "Entry point — configure the trigger"
          : nodeSummary(node.node_type, node.config)}
      </p>
    </button>
  );
}

function Connector({ onAdd }: { onAdd: (type: CommsNodeType) => void }) {
  return (
    <div className="flex flex-col items-center">
      <div className="h-4 w-px bg-border" />
      <AddNodeMenu
        onPick={onAdd}
        trigger={
          <Button
            variant="outline"
            size="icon"
            className="h-6 w-6 rounded-full"
            aria-label="Add step"
          >
            <Plus className="h-3.5 w-3.5" />
          </Button>
        }
      />
      <div className="h-4 w-px bg-border" />
    </div>
  );
}

export function AutomationCanvas({
  nodes,
  edges,
  selectedNodeId,
  enrolledCount,
  onSelectNode,
  onAddAfter,
}: AutomationCanvasProps) {
  const byId = new Map(nodes.map((n) => [n.id, n]));
  const trigger = nodes.find((n) => n.node_type === "trigger");

  if (!trigger) {
    return (
      <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
        No trigger node — something went wrong initialising this automation.
      </div>
    );
  }

  // Walk the main spine: follow the first/default edge from each node.
  const spine: CommsNode[] = [];
  const visited = new Set<string>();
  let current: CommsNode | undefined = trigger;
  while (current && !visited.has(current.id)) {
    visited.add(current.id);
    spine.push(current);
    if (current.node_type === "branch" || current.node_type === "multi_split") break;
    const out = edges
      .filter((e) => e.source_node_id === current!.id)
      .sort((a, b) => a.sort_order - b.sort_order)[0];
    current = out ? byId.get(out.target_node_id) : undefined;
  }

  const branchNode = spine[spine.length - 1];
  const isBranchTail =
    branchNode &&
    (branchNode.node_type === "branch" || branchNode.node_type === "multi_split");

  // Fresh automation: only the seeded trigger + exit, no real steps yet.
  const isEmpty = nodes.every(
    (n) => n.node_type === "trigger" || n.node_type === "exit",
  );

  const branchChildren = isBranchTail
    ? edges
        .filter((e) => e.source_node_id === branchNode.id)
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((e) => ({ label: e.branch_label, node: byId.get(e.target_node_id) }))
    : [];

  return (
    <div className="flex h-full justify-center overflow-auto py-10">
      <div className="flex flex-col items-center">
        {isEmpty && (
          <div className="mb-6 max-w-sm rounded-lg border border-dashed bg-background px-4 py-3 text-center text-sm text-muted-foreground">
            Start by clicking <strong>Trigger</strong> to choose what starts this journey, then use the
            <span className="mx-1 inline-flex h-4 w-4 items-center justify-center rounded-full border align-middle text-[10px]">+</span>
            below it to add your first Email, SMS, or n8n step.
          </div>
        )}
        {spine.map((node, i) => {
          const isLast = i === spine.length - 1;
          const showConnector = !isLast || !isBranchTail;
          return (
            <Fragment key={node.id}>
              <NodeCard
                node={node}
                selected={node.id === selectedNodeId}
                enrolledCount={enrolledCount}
                onSelect={() => onSelectNode(node.id)}
              />
              {showConnector && !isLast && <Connector onAdd={(t) => onAddAfter(node.id, t)} />}
              {isLast && !isBranchTail && node.node_type !== "exit" && (
                <Connector onAdd={(t) => onAddAfter(node.id, t)} />
              )}
            </Fragment>
          );
        })}

        {isBranchTail && (
          <>
            <div className="h-4 w-px bg-border" />
            <div className="flex items-start gap-10">
              {(branchNode.node_type === "branch"
                ? ["true", "false"]
                : branchChildren.map((c) => c.label ?? "")
              ).map((label) => {
                const child = branchChildren.find((c) => c.label === label)?.node;
                return (
                  <div key={label} className="flex flex-col items-center">
                    <Badge variant="outline" className="mb-2 capitalize">{label || "branch"}</Badge>
                    {child ? (
                      <NodeCard
                        node={child}
                        selected={child.id === selectedNodeId}
                        enrolledCount={0}
                        onSelect={() => onSelectNode(child.id)}
                      />
                    ) : (
                      <AddNodeMenu
                        onPick={(t) => onAddAfter(branchNode.id, t, label)}
                        trigger={
                          <Button variant="outline" size="sm" className="rounded-full">
                            <Plus className="h-3.5 w-3.5 mr-1" /> Add
                          </Button>
                        }
                      />
                    )}
                  </div>
                );
              })}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
