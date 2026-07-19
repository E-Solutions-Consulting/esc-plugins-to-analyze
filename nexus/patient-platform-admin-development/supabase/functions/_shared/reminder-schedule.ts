// Reminder scheduling utilities.
// Computes UTC delivery timestamps from a patient's local time + timezone,
// using only the built-in Intl API — no external date library required.

export type ReminderFrequency = "daily" | "weekly";

export interface ReminderScheduleInput {
  frequency: ReminderFrequency;
  /** 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat. Null/empty for daily. */
  repeat_days: number[] | null;
  /** Local time as "HH:MM" or "HH:MM:SS" */
  time_local: string;
  /** IANA timezone, e.g. "America/New_York" */
  timezone: string;
}

/**
 * Calculate the UTC delivery Date for each reminder occurrence within a window.
 *
 * @param reminder    Reminder schedule config
 * @param fromDate    Start of the window (exclusive — occurrences must be after this)
 * @param daysAhead   Number of calendar days ahead to scan (e.g. 30)
 * @returns Sorted array of UTC Dates
 */
export function calculateOccurrences(
  reminder: ReminderScheduleInput,
  fromDate: Date,
  daysAhead: number,
): Date[] {
  const [hours, minutes] = parseTimeLocal(reminder.time_local);
  const results: Date[] = [];

  for (let offset = 0; offset < daysAhead; offset++) {
    // Candidate UTC date: fromDate + offset days
    const candidateUtc = new Date(fromDate);
    candidateUtc.setUTCDate(candidateUtc.getUTCDate() + offset);

    if (reminder.frequency === "weekly") {
      const localDow = getDayOfWeekInTimezone(candidateUtc, reminder.timezone);
      if (!reminder.repeat_days?.includes(localDow)) continue;
    }

    // Compute the UTC instant for time_local on this local day
    const fireAt = localTimeToUtc(
      candidateUtc,
      hours,
      minutes,
      reminder.timezone,
    );

    // Only include future occurrences strictly after fromDate
    if (fireAt > fromDate) {
      results.push(fireAt);
    }
  }

  return results.sort((a, b) => a.getTime() - b.getTime());
}

/**
 * Parse "HH:MM" or "HH:MM:SS" into [hours, minutes].
 */
export function parseTimeLocal(timeLocal: string): [number, number] {
  const parts = timeLocal.split(":").map(Number);
  return [parts[0] ?? 0, parts[1] ?? 0];
}

/**
 * Format time_local + repeat_days into a human-readable schedule summary.
 * Examples:
 *   daily 09:00     → "Every day • 9:00 AM"
 *   weekly [1,3,5]  → "Mon, Wed, Fri • 9:00 AM"
 */
export function buildScheduleSummary(
  frequency: ReminderFrequency,
  repeatDays: number[] | null,
  timeLocal: string,
): string {
  const [hours, minutes] = parseTimeLocal(timeLocal);
  const timeStr = formatTime12h(hours, minutes);

  if (frequency === "daily") {
    return `Every day • ${timeStr}`;
  }

  const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  const sorted = [...(repeatDays ?? [])].sort((a, b) => a - b);
  const dayStr = sorted
    .map((d) => dayNames[d] ?? "")
    .filter(Boolean)
    .join(", ");
  return `${dayStr} • ${timeStr}`;
}

/**
 * Derive reminder title from category and optional medication name.
 */
export function deriveReminderTitle(
  category: string,
  medicationName: string | null,
): string {
  switch (category) {
    case "medication":
      return medicationName ?? "Medication";
    case "body":
      return "Body Measure";
    case "energy":
      return "Energy Check";
    case "weight":
      return "Weight Check";
    default:
      return "Reminder";
  }
}

// ── Private helpers ───────────────────────────────────────────────────────────

/**
 * Get the local day-of-week (0=Sun…6=Sat) for a UTC instant in a given timezone.
 */
function getDayOfWeekInTimezone(utcDate: Date, timezone: string): number {
  const formatted = new Intl.DateTimeFormat("en-US", {
    timeZone: timezone,
    weekday: "short",
  }).format(utcDate);

  const dayMap: Record<string, number> = {
    Sun: 0,
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
  };

  return dayMap[formatted] ?? 0;
}

/**
 * Convert a local HH:MM on the same calendar day as `dayRef` (in `timezone`) to UTC.
 *
 * Algorithm:
 *   1. Find the local calendar date for `dayRef` in the target timezone.
 *   2. Treat local-date + HH:MM as UTC to get an approximate UTC instant.
 *   3. Compute the UTC offset for that approximate instant.
 *   4. Subtract the offset to get the true UTC instant.
 *   5. Re-check the offset (handles DST boundary edge cases).
 */
function localTimeToUtc(
  dayRef: Date,
  localHours: number,
  localMinutes: number,
  timezone: string,
): Date {
  // Step 1: Get the local calendar date (y/m/d) for dayRef in the timezone
  const localDateParts = new Intl.DateTimeFormat("en-US", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(dayRef);

  const year = Number(getPart(localDateParts, "year"));
  const month = Number(getPart(localDateParts, "month")) - 1; // 0-based
  const day = Number(getPart(localDateParts, "day"));

  // Step 2: Approximate UTC — treat local datetime as if it were UTC
  const approxUtc = new Date(
    Date.UTC(year, month, day, localHours, localMinutes, 0),
  );

  // Step 3: Offset at approx instant (localTime - utcTime)
  const offset1 = getUtcOffsetMs(approxUtc, timezone);

  // Step 4: Adjusted UTC
  const adjusted = new Date(approxUtc.getTime() - offset1);

  // Step 5: Re-check offset at adjusted time (DST boundary guard)
  const offset2 = getUtcOffsetMs(adjusted, timezone);
  if (offset1 === offset2) return adjusted;

  return new Date(approxUtc.getTime() - offset2);
}

/**
 * Compute the UTC offset in ms for a UTC instant viewed in a timezone.
 * offset = localTime - utcTime (positive for UTC+ zones)
 */
function getUtcOffsetMs(utcDate: Date, timezone: string): number {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  }).formatToParts(utcDate);

  const localMs = Date.UTC(
    Number(getPart(parts, "year")),
    Number(getPart(parts, "month")) - 1,
    Number(getPart(parts, "day")),
    Number(getPart(parts, "hour")),
    Number(getPart(parts, "minute")),
    Number(getPart(parts, "second")),
  );

  return localMs - utcDate.getTime();
}

function getPart(
  parts: Intl.DateTimeFormatPart[],
  type: Intl.DateTimeFormatPartTypes,
): string {
  return parts.find((p) => p.type === type)?.value ?? "0";
}

function formatTime12h(hours: number, minutes: number): string {
  const period = hours < 12 ? "AM" : "PM";
  const h = hours % 12 === 0 ? 12 : hours % 12;
  const m = minutes.toString().padStart(2, "0");
  return `${h}:${m} ${period}`;
}
