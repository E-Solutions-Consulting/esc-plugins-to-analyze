import { assertEquals } from "jsr:@std/assert@1";
import {
  getDeploymentEnvironment,
  isDevelopmentEnvironment,
  isProductionEnvironment,
  isStagingEnvironment,
} from "./environment.ts";

Deno.test("environment helpers recognize the configured Supabase projects", () => {
  const variableNames = ["APP_ENV", "ENVIRONMENT", "SUPABASE_ENV"] as const;
  const previousValues = new Map(
    variableNames.map((name) => [name, Deno.env.get(name)]),
  );

  try {
    for (const name of variableNames) Deno.env.delete(name);

    const developmentUrl = "https://sunzxjnbgtknqeivljtd.supabase.co";
    const stagingUrl = "https://rhzrxfckhogjppjsioyn.supabase.co";
    const productionUrl = "https://dfejvhgwqhywmtxyxkyo.supabase.co";
    const unknownUrl = "https://unknown-project.supabase.co";

    assertEquals(isDevelopmentEnvironment(developmentUrl), true);
    assertEquals(isStagingEnvironment(stagingUrl), true);
    assertEquals(isProductionEnvironment(productionUrl), true);
    assertEquals(isProductionEnvironment(unknownUrl), false);
    assertEquals(getDeploymentEnvironment(developmentUrl), "development");
    assertEquals(getDeploymentEnvironment(stagingUrl), "staging");
    assertEquals(getDeploymentEnvironment(productionUrl), "production");
    assertEquals(getDeploymentEnvironment(unknownUrl), null);
  } finally {
    for (const [name, value] of previousValues) {
      if (value === undefined) Deno.env.delete(name);
      else Deno.env.set(name, value);
    }
  }
});
