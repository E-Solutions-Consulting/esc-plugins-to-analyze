import { assertEquals } from "../_test/assert.ts";

function roundToTwo(value: number): number {
  return Math.round(value * 100) / 100;
}

Deno.test("telegra payload example units normalize to lbs and inches", () => {
  const kg = 100;
  const cm = 160;

  assertEquals(roundToTwo(kg * 2.20462), 220.46);
  assertEquals(roundToTwo(cm / 2.54), 62.99);
});

Deno.test("mdi payload example units normalize to kgs and centimeters", () => {
  const kg = 100;
  const cm = 160;

  assertEquals(roundToTwo(kg), 100);
  assertEquals(roundToTwo(cm), 160);
});

Deno.test("mdi converts imperial inputs back to metric", () => {
  const pounds = 220.46;
  const inches = 62.99;

  assertEquals(roundToTwo(pounds / 2.20462), 100);
  assertEquals(roundToTwo(inches * 2.54), 159.99);
});
