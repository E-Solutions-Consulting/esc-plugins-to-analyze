import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import {
  isAuthUserNotFoundError,
  isDuplicateEmailError,
  isValidEmailFormat,
} from "./helpers.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";

interface UpdateEmailRequest {
  patientId: string;
  newEmail: string;
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

    // Admin client for updating email
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
    const { patientId, newEmail }: UpdateEmailRequest = await req.json();

    if (!patientId || !newEmail) {
      return new Response(
        JSON.stringify({ error: "Missing required fields: patientId and newEmail" }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Validate email format
    if (!isValidEmailFormat(newEmail)) {
      return new Response(
        JSON.stringify({ error: "Invalid email format" }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Get the patient to find their auth_user_id and tenant_id
    const { data: patient, error: patientError } = await supabaseAdmin
      .from("patients")
      .select("auth_user_id, tenant_id, email")
      .eq("id", patientId)
      .single();

    if (patientError || !patient) {
      return new Response(
        JSON.stringify({ error: "Patient not found" }),
        { status: 404, headers: { ...corsHeaders, "Content-Type": "application/json" } }
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
        JSON.stringify({ error: "You do not have permission to update this patient's email" }),
        { status: 403, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // If patient has an auth account, update the auth user's email
    if (patient.auth_user_id) {
      const { error: updateError } = await supabaseAdmin.auth.admin.updateUserById(
        patient.auth_user_id,
        { email: newEmail }
      );

      if (updateError) {
        console.error("Error updating auth email:", updateError);
        
        // Check for duplicate email error
        if (isDuplicateEmailError(updateError.message)) {
          return new Response(
            JSON.stringify({ error: "This email is already in use by another account" }),
            { status: 409, headers: { ...corsHeaders, "Content-Type": "application/json" } }
          );
        }

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
          JSON.stringify({ error: updateError.message || "Failed to update auth email" }),
          { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }
    }

    // Update the patient record
    const { error: patientUpdateError } = await supabaseAdmin
      .from("patients")
      .update({ email: newEmail })
      .eq("id", patientId);

    if (patientUpdateError) {
      console.error("Error updating patient email:", patientUpdateError);
      
      // If we updated auth but failed on patient, try to rollback auth
      if (patient.auth_user_id) {
        await supabaseAdmin.auth.admin.updateUserById(
          patient.auth_user_id,
          { email: patient.email }
        );
      }
      
      return new Response(
        JSON.stringify({ error: patientUpdateError.message || "Failed to update patient email" }),
        { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Log the action
    console.log(`Email updated for patient ${patientId} from ${patient.email} to ${newEmail} by user ${callerUser.id}`);

    return new Response(
      JSON.stringify({ 
        success: true, 
        message: patient.auth_user_id 
          ? "Email updated for both patient record and login account" 
          : "Email updated for patient record"
      }),
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
