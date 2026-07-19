/**
 * Read-only visualisation of an n8n workflow graph.
 * n8n workflow JSON shape: { nodes: [{ name, type, position: [x,y] }], connections: {...} }.
 * We render the nodes at their n8n canvas positions with SVG connectors, scaled
 * to fit. Purely informational — editing happens in n8n itself.
 */
import { useMemo } from "react";
import { ExternalLink } from "lucide-react";

interface N8nGraphNode {
  name: string;
  type?: string;
  position?: [number, number];
}

interface N8nGraph {
  name?: string;
  nodes?: N8nGraphNode[];
  connections?: Record<string, { main?: Array<Array<{ node: string }>> }>;
}

interface N8nFlowVizProps {
  graph: unknown;
  openInN8nUrl?: string | null;
}

const NODE_W = 150;
const NODE_H = 44;
const PAD = 40;

function shortType(type?: string): string {
  if (!type) return "node";
  const parts = type.split(".");
  return parts[parts.length - 1];
}

export function N8nFlowViz({ graph, openInN8nUrl }: N8nFlowVizProps) {
  const g = graph as N8nGraph | null;

  const layout = useMemo(() => {
    const nodes = g?.nodes ?? [];
    if (nodes.length === 0) return null;

    const positioned = nodes.map((n, i) => ({
      name: n.name,
      type: shortType(n.type),
      x: n.position?.[0] ?? i * (NODE_W + 60),
      y: n.position?.[1] ?? 0,
    }));

    const minX = Math.min(...positioned.map((p) => p.x));
    const minY = Math.min(...positioned.map((p) => p.y));
    const norm = positioned.map((p) => ({ ...p, x: p.x - minX, y: p.y - minY }));

    const width = Math.max(...norm.map((p) => p.x)) + NODE_W + PAD * 2;
    const height = Math.max(...norm.map((p) => p.y)) + NODE_H + PAD * 2;

    const byName = new Map(norm.map((p) => [p.name, p]));
    const edges: Array<{ x1: number; y1: number; x2: number; y2: number }> = [];
    const connections = g?.connections ?? {};
    for (const [source, conn] of Object.entries(connections)) {
      const from = byName.get(source);
      if (!from) continue;
      for (const group of conn.main ?? []) {
        for (const target of group ?? []) {
          const to = byName.get(target.node);
          if (!to) continue;
          edges.push({
            x1: from.x + NODE_W + PAD,
            y1: from.y + NODE_H / 2 + PAD,
            x2: to.x + PAD,
            y2: to.y + NODE_H / 2 + PAD,
          });
        }
      }
    }

    return { nodes: norm, edges, width, height };
  }, [g]);

  if (!layout) {
    return (
      <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
        No graph available to visualise.
        {openInN8nUrl && (
          <a
            href={openInN8nUrl}
            target="_blank"
            rel="noreferrer"
            className="ml-1 inline-flex items-center gap-1 text-primary hover:underline"
          >
            Open in n8n <ExternalLink className="h-3 w-3" />
          </a>
        )}
      </div>
    );
  }

  return (
    <div className="rounded-md border bg-muted/30 overflow-auto">
      <div className="flex items-center justify-between px-3 py-2 border-b bg-background">
        <span className="text-sm font-medium">{g?.name ?? "n8n workflow"}</span>
        {openInN8nUrl && (
          <a
            href={openInN8nUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
          >
            Open in n8n <ExternalLink className="h-3 w-3" />
          </a>
        )}
      </div>
      <svg
        width="100%"
        viewBox={`0 0 ${layout.width} ${layout.height}`}
        className="max-h-80"
        preserveAspectRatio="xMidYMid meet"
      >
        <defs>
          <marker id="n8n-arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
            <path d="M0,0 L8,4 L0,8 Z" className="fill-muted-foreground" />
          </marker>
        </defs>
        {layout.edges.map((e, i) => (
          <path
            key={i}
            d={`M ${e.x1} ${e.y1} C ${e.x1 + 40} ${e.y1}, ${e.x2 - 40} ${e.y2}, ${e.x2} ${e.y2}`}
            className="stroke-muted-foreground/50"
            fill="none"
            strokeWidth={1.5}
            markerEnd="url(#n8n-arrow)"
          />
        ))}
        {layout.nodes.map((n) => (
          <g key={n.name} transform={`translate(${n.x + PAD}, ${n.y + PAD})`}>
            <rect
              width={NODE_W}
              height={NODE_H}
              rx={8}
              className="fill-background stroke-border"
              strokeWidth={1}
            />
            <text x={10} y={18} className="fill-foreground text-[11px] font-medium">
              {n.name.length > 18 ? `${n.name.slice(0, 18)}…` : n.name}
            </text>
            <text x={10} y={33} className="fill-muted-foreground text-[9px]">
              {n.type}
            </text>
          </g>
        ))}
      </svg>
    </div>
  );
}
