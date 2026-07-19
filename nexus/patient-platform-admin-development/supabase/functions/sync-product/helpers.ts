export function mapToStripeInterval(
  interval: string
): "day" | "week" | "month" | "year" {
  const map: Record<string, "day" | "week" | "month" | "year"> = {
    day: "day",
    week: "week",
    month: "month",
    year: "year",
  };
  return map[interval] || "month";
}
