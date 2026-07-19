import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { isAuthUserNotFoundError, isValidPasswordStrength } from "./helpers.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";

interface UpdatePasswordRequest {
  patientId: string;
  newPassword: string;
}

serve(async (req: Request) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
  });

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    // Get auth token from request
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      return new Response(
        JSON.stringify({ error: "Missing authorization header" }),
        { status: 401, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Create Supabase clients
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY")!;

    // Client for checking caller's permissions
    const supabaseClient = createClient(supabaseUrl, supabaseAnonKey, {
      global: { headers: { Authorization: authHeader } },
    });

    // Admin client for updating password
    const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey, {
      auth: { autoRefreshToken: false, persistSession: false },
    });

    // Verify the caller is authenticated
    const { data: { user: callerUser }, error: authError } = await supabaseClient.auth.getUser();
    if (authError || !callerUser) {
      return new Response(
        JSON.stringify({ error: "Unauthorized" }),
        { status: 401, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Parse request body
    const { patientId, newPassword }: UpdatePasswordRequest = await req.json();

    if (!patientId || !newPassword) {
      return new Response(
        JSON.stringify({ error: "Missing required fields: patientId and newPassword" }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Validate password strength
    if (!isValidPasswordStrength(newPassword)) {
      return new Response(
        JSON.stringify({ error: "Password must be at least 8 characters long" }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Get the patient to find their auth_user_id and tenant_id
    const { data: patient, error: patientError } = await supabaseAdmin
      .from("patients")
      .select("auth_user_id, tenant_id")
      .eq("id", patientId)
      .single();

    if (patientError || !patient) {
      return new Response(
        JSON.stringify({ error: "Patient not found" }),
        { status: 404, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    if (!patient.auth_user_id) {
      return new Response(
        JSON.stringify({ error: "Patient does not have an associated login account" }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Check if caller is a platform superadmin or tenant admin for this patient's tenant
    const { data: isPlatformSuperadmin } = await supabaseAdmin.rpc(
      "is_platform_superadmin",
      { _auth_user_id: callerUser.id }
    );

    const { data: isTenantAdmin } = await supabaseAdmin.rpc(
      "is_tenant_admin",
      { _auth_user_id: callerUser.id, _tenant_id: patient.tenant_id }
    );

    if (!isPlatformSuperadmin && !isTenantAdmin) {
      return new Response(
        JSON.stringify({ error: "You do not have permission to update this patient's password" }),
        { status: 403, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Update the user's password using admin API
    const { error: updateError } = await supabaseAdmin.auth.admin.updateUserById(
      patient.auth_user_id,
      { password: newPassword }
    );

    if (updateError) {
      console.error("Error updating password:", updateError);
      
      // Provide a more specific error message for user not found
      if (isAuthUserNotFoundError(updateError)) {
        return new Response(
          JSON.stringify({ 
            error: "The authentication account for this patient no longer exists. The patient may need to be re-registered." 
          }),
          { status: 404, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }
      
      return new Response(
        JSON.stringify({ error: updateError.message || "Failed to update password" }),
        { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Log the action (without including the password)
    console.log(`Password updated for patient ${patientId} by user ${callerUser.id}`);

    return new Response(
      JSON.stringify({ success: true, message: "Password updated successfully" }),
      { status: 200, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  } catch (error) {
    console.error("Unexpected error:", error);
    return new Response(
      JSON.stringify({ error: "An unexpected error occurred" }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  }
});
