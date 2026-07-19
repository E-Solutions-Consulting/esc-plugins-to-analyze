import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  canAssignPlatformSuperadminRole,
  EMAIL_ALREADY_ADMIN_MESSAGE,
  EMAIL_ALREADY_AUTH_ONLY_MESSAGE,
  EMAIL_ALREADY_PATIENT_MESSAGE,
  getCreateAdminErrorMessage,
  getMissingCreateAdminFields,
  isEmailAlreadyRegisteredError,
  shouldCheckTenantAdminAccess,
} from "./helpers.ts";
import type { CreateAdminRequest } from "./helpers.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";

serve(async (req: Request) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
  });

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;

    // Create admin client with service role
    const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey, {
      auth: {
        autoRefreshToken: false,
        persistSession: false,
      },
    });

    // Create regular client to check caller permissions
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      throw new Error("Missing authorization header");
    }

    const supabaseClient = createClient(supabaseUrl, Deno.env.get("SUPABASE_ANON_KEY")!, {
      global: { headers: { Authorization: authHeader } },
    });

    // Get the calling user
    const { data: { user: caller }, error: userError } = await supabaseClient.auth.getUser();
    if (userError || !caller) {
      throw new Error("Unauthorized");
    }

    // Check if caller is platform superadmin
    const { data: isSuperadmin } = await supabaseAdmin.rpc("is_platform_superadmin", {
      _auth_user_id: caller.id,
    });

    // Parse request body
    const body: CreateAdminRequest = await req.json();
    const email = body.email?.trim().toLowerCase();
    const { fullName, password, isPlatformSuperadmin, tenantId } = body;

    // Validate inputs
    const missingFields = getMissingCreateAdminFields(body);
    if (missingFields.length > 0) {
      throw new Error("Missing required fields");
    }

    // Only platform superadmins can create other superadmins
    if (!canAssignPlatformSuperadminRole(isPlatformSuperadmin, !!isSuperadmin)) {
      throw new Error("Only platform superadmins can create other superadmins");
    }

    // If creating tenant admin, check if caller has access to that tenant
    if (shouldCheckTenantAdminAccess(isPlatformSuperadmin, tenantId)) {
      if (!isSuperadmin) {
        const { data: isTenantAdmin } = await supabaseAdmin.rpc("is_tenant_admin", {
          _auth_user_id: caller.id,
          _tenant_id: tenantId,
        });

        if (!isTenantAdmin) {
          throw new Error("You don't have permission to add admins to this tenant");
        }
      }
    }

    const { data: existingAdminUser, error: existingAdminUserError } = await supabaseAdmin
      .from("admin_users")
      .select("id")
      .ilike("email", email)
      .maybeSingle();

    if (existingAdminUserError) {
      throw new Error("Failed to check existing admin users");
    }

    if (existingAdminUser) {
      throw new Error(EMAIL_ALREADY_ADMIN_MESSAGE);
    }

    const { data: existingPatient, error: existingPatientError } = await supabaseAdmin
      .from("patients")
      .select("id, auth_user_id")
      .ilike("email", email)
      .not("auth_user_id", "is", null)
      .limit(1)
      .maybeSingle();

    if (existingPatientError) {
      throw new Error("Failed to check existing patient accounts");
    }

    if (existingPatient) {
      throw new Error(EMAIL_ALREADY_PATIENT_MESSAGE);
    }

    // Create the auth user
    const { data: authData, error: createError } = await supabaseAdmin.auth.admin.createUser({
      email,
      password,
      email_confirm: true,
      user_metadata: { full_name: fullName },
    });

    if (createError) {
      if (isEmailAlreadyRegisteredError(createError.message)) {
        throw new Error(EMAIL_ALREADY_AUTH_ONLY_MESSAGE);
      }

      throw new Error(createError.message);
    }

    const newUserId = authData.user.id;

    // The admin_users record is created automatically by the handle_new_user trigger
    // Wait a moment for the trigger to execute
    await new Promise((resolve) => setTimeout(resolve, 500));

    // Get the admin_user_id
    const { data: adminUser, error: adminUserError } = await supabaseAdmin
      .from("admin_users")
      .select("id")
      .eq("auth_user_id", newUserId)
      .single();

    if (adminUserError || !adminUser) {
      throw new Error("Failed to create admin user record");
    }

    // Assign roles
    if (isPlatformSuperadmin) {
      // Add platform_superadmin role
      const { error: roleError } = await supabaseAdmin
        .from("user_roles")
        .insert({
          user_id: adminUser.id,
          role: "platform_superadmin",
        });

      if (roleError) {
        throw new Error("Failed to assign superadmin role");
      }
    }

    if (tenantId) {
      // Add tenant_admin role if not already present
      const { error: roleError } = await supabaseAdmin
        .from("user_roles")
        .upsert(
          {
            user_id: adminUser.id,
            role: "tenant_admin",
          },
          { onConflict: "user_id,role" }
        );

      if (roleError) {
        console.error("Role error:", roleError);
      }

      // Create tenant membership
      const { error: membershipError } = await supabaseAdmin
        .from("tenant_memberships")
        .insert({
          admin_user_id: adminUser.id,
          tenant_id: tenantId,
          is_primary: false,
        });

      if (membershipError) {
        throw new Error("Failed to create tenant membership");
      }
    }

    return new Response(
      JSON.stringify({
        success: true,
        userId: newUserId,
        adminUserId: adminUser.id,
      }),
      {
        status: 200,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      }
    );
  } catch (error: unknown) {
    console.error("Error creating admin:", error);
    const message = getCreateAdminErrorMessage(error);
    return new Response(
      JSON.stringify({ error: message }),
      {
        status: 400,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      }
    );
  }
});
