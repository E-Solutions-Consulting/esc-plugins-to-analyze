import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  actionRequiresTenantId,
  actionGrantsTenantAdmin,
  extractBearerToken,
  hasProfileUpdates,
  isValidManageRolesAction,
} from "./helpers.ts";
import type { ManageRolesAction } from "./helpers.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";

interface ManageRolesRequest {
  adminUserId: string;
  action: ManageRolesAction;
  tenantId?: string;
  isPrimary?: boolean;
  newPassword?: string;
  fullName?: string;
  avatarUrl?: string;
}

interface AuditLogEntry {
  action: string;
  entity_type: string;
  entity_id: string;
  before_data?: Record<string, unknown>;
  after_data?: Record<string, unknown>;
  tenant_id?: string | null;
}

serve(async (req: Request) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;

    const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey, {
      auth: { autoRefreshToken: false, persistSession: false },
    });

    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      throw new Error("Missing authorization header");
    }

    const accessToken = extractBearerToken(authHeader);
    if (!accessToken) {
      throw new Error("Missing access token");
    }

    // Validate caller by hitting GoTrue directly to avoid runtime session coupling.
    const userResponse = await fetch(`${supabaseUrl}/auth/v1/user`, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        apikey: supabaseServiceKey,
      },
    });

    if (!userResponse.ok) {
      throw new Error("Unauthorized");
    }

    const caller = await userResponse.json() as { id?: string };
    if (!caller?.id) {
      throw new Error("Unauthorized");
    }

    // Only platform superadmins can manage roles
    const { data: isSuperadmin } = await supabaseAdmin.rpc("is_platform_superadmin", {
      _auth_user_id: caller.id,
    });

    if (!isSuperadmin) {
      throw new Error("Only platform superadmins can manage roles");
    }

    // Get caller's admin_user_id for audit logging
    const { data: callerAdmin } = await supabaseAdmin
      .from("admin_users")
      .select("id, email")
      .eq("auth_user_id", caller.id)
      .single();

    const body: ManageRolesRequest = await req.json();
    const { adminUserId, action, tenantId, isPrimary, newPassword, fullName, avatarUrl } = body;

    if (!adminUserId || !action) {
      throw new Error("Missing required fields");
    }

    if (!isValidManageRolesAction(action)) {
      throw new Error("Invalid action");
    }

    // Fetch target user info for audit logging
    const { data: targetUser } = await supabaseAdmin
      .from("admin_users")
      .select("id, email, full_name, is_active, auth_user_id")
      .eq("id", adminUserId)
      .single();

    // Helper function to log audit entries
    const logAudit = async (entry: AuditLogEntry) => {
      try {
        await supabaseAdmin.from("audit_logs").insert({
          action: entry.action,
          entity_type: entry.entity_type,
          entity_id: entry.entity_id,
          before_data: entry.before_data || null,
          after_data: entry.after_data || null,
          actor_id: callerAdmin?.id || null,
          actor_email: callerAdmin?.email || null,
          tenant_id: entry.tenant_id || null,
        });
      } catch (auditError) {
        console.error("Failed to write audit log:", auditError);
      }
    };

    switch (action) {
      case "add_superadmin": {
        const { error } = await supabaseAdmin
          .from("user_roles")
          .upsert(
            { user_id: adminUserId, role: "platform_superadmin" },
            { onConflict: "user_id,role" }
          );
        if (error) throw new Error("Failed to add superadmin role: " + error.message);

        await logAudit({
          action: "grant_superadmin",
          entity_type: "admin_user",
          entity_id: adminUserId,
          after_data: { 
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            role: "platform_superadmin"
          },
        });
        break;
      }

      case "remove_superadmin": {
        const { error } = await supabaseAdmin
          .from("user_roles")
          .delete()
          .eq("user_id", adminUserId)
          .eq("role", "platform_superadmin");
        if (error) throw new Error("Failed to remove superadmin role: " + error.message);

        await logAudit({
          action: "revoke_superadmin",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: { 
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            role: "platform_superadmin"
          },
        });
        break;
      }

      case "add_customer_support": {
        const { error } = await supabaseAdmin
          .from("user_roles")
          .upsert(
            { user_id: adminUserId, role: "customer_support" },
            { onConflict: "user_id,role" },
          );
        if (error) {
          throw new Error("Failed to add customer support role: " + error.message);
        }

        await logAudit({
          action: "grant_customer_support",
          entity_type: "admin_user",
          entity_id: adminUserId,
          after_data: {
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            role: "customer_support",
          },
        });
        break;
      }

      case "remove_customer_support": {
        const { error } = await supabaseAdmin
          .from("user_roles")
          .delete()
          .eq("user_id", adminUserId)
          .eq("role", "customer_support");
        if (error) {
          throw new Error("Failed to remove customer support role: " + error.message);
        }

        await logAudit({
          action: "revoke_customer_support",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: {
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            role: "customer_support",
          },
        });
        break;
      }

      case "add_tenant":
      case "add_tenant_membership": {
        if (actionRequiresTenantId(action) && !tenantId) throw new Error("Tenant ID required");

        // Get tenant info for audit
        const { data: tenant } = await supabaseAdmin
          .from("tenants")
          .select("name, slug")
          .eq("id", tenantId)
          .single();

        const { data: customerSupportRole, error: customerSupportRoleError } =
          await supabaseAdmin
            .from("user_roles")
            .select("id")
            .eq("user_id", adminUserId)
            .eq("role", "customer_support")
            .maybeSingle();
        if (customerSupportRoleError) {
          throw new Error(
            "Failed to check customer support role: " + customerSupportRoleError.message,
          );
        }

        const grantTenantAdmin = actionGrantsTenantAdmin(
          action,
          Boolean(customerSupportRole),
        );

        if (grantTenantAdmin) {
          const { error: tenantAdminRoleError } = await supabaseAdmin
            .from("user_roles")
            .upsert(
              { user_id: adminUserId, role: "tenant_admin" },
              { onConflict: "user_id,role" }
            );
          if (tenantAdminRoleError) {
            throw new Error("Failed to add tenant admin role: " + tenantAdminRoleError.message);
          }
        }

        // Check if membership already exists
        const { data: existing } = await supabaseAdmin
          .from("tenant_memberships")
          .select("id")
          .eq("admin_user_id", adminUserId)
          .eq("tenant_id", tenantId)
          .maybeSingle();

        if (existing) {
          throw new Error("User is already a member of this tenant");
        }

        const { error } = await supabaseAdmin
          .from("tenant_memberships")
          .insert({
            admin_user_id: adminUserId,
            tenant_id: tenantId,
            is_primary: isPrimary || false,
          });
        if (error) throw new Error("Failed to add tenant membership: " + error.message);

        await logAudit({
          action: "add_tenant_membership",
          entity_type: "admin_user",
          entity_id: adminUserId,
          after_data: { 
            targetEmail: targetUser?.email,
            tenantName: tenant?.name,
            tenantSlug: tenant?.slug,
            isPrimary: isPrimary || false,
            grantTenantAdmin,
          },
          tenant_id: tenantId,
        });
        break;
      }

      case "remove_tenant": {
        if (actionRequiresTenantId(action) && !tenantId) throw new Error("Tenant ID required");

        // Get tenant info for audit
        const { data: tenant } = await supabaseAdmin
          .from("tenants")
          .select("name, slug")
          .eq("id", tenantId)
          .single();

        const { error } = await supabaseAdmin
          .from("tenant_memberships")
          .delete()
          .eq("admin_user_id", adminUserId)
          .eq("tenant_id", tenantId);
        if (error) throw new Error("Failed to remove tenant membership: " + error.message);

        // Check if user still has any tenant memberships
        const { data: remainingMemberships } = await supabaseAdmin
          .from("tenant_memberships")
          .select("id")
          .eq("admin_user_id", adminUserId);

        // If no more memberships and not a superadmin, remove tenant_admin role
        if (!remainingMemberships || remainingMemberships.length === 0) {
          const { data: hasSuper } = await supabaseAdmin
            .from("user_roles")
            .select("id")
            .eq("user_id", adminUserId)
            .eq("role", "platform_superadmin")
            .maybeSingle();

          if (!hasSuper) {
            await supabaseAdmin
              .from("user_roles")
              .delete()
              .eq("user_id", adminUserId)
              .eq("role", "tenant_admin");
          }
        }

        await logAudit({
          action: "remove_tenant_membership",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: { 
            targetEmail: targetUser?.email,
            tenantName: tenant?.name,
            tenantSlug: tenant?.slug
          },
          tenant_id: tenantId,
        });
        break;
      }

      case "deactivate_user": {
        const { error } = await supabaseAdmin
          .from("admin_users")
          .update({ is_active: false })
          .eq("id", adminUserId);
        if (error) throw new Error("Failed to deactivate user: " + error.message);

        await logAudit({
          action: "deactivate_account",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: { 
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            is_active: true
          },
          after_data: { 
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            is_active: false
          },
        });
        break;
      }

      case "activate_user": {
        const { error } = await supabaseAdmin
          .from("admin_users")
          .update({ is_active: true })
          .eq("id", adminUserId);
        if (error) throw new Error("Failed to activate user: " + error.message);

        await logAudit({
          action: "activate_account",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: { 
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            is_active: false
          },
          after_data: { 
            targetEmail: targetUser?.email,
            targetName: targetUser?.full_name,
            is_active: true
          },
        });
        break;
      }

      case "remove_from_tenant": {
        if (actionRequiresTenantId(action) && !tenantId) throw new Error("Tenant ID required");

        // Get tenant info for audit
        const { data: tenant } = await supabaseAdmin
          .from("tenants")
          .select("name, slug")
          .eq("id", tenantId)
          .single();
        
        // Remove from tenant_memberships
        const { error: membershipError } = await supabaseAdmin
          .from("tenant_memberships")
          .delete()
          .eq("admin_user_id", adminUserId)
          .eq("tenant_id", tenantId);
        if (membershipError) throw new Error("Failed to remove from tenant: " + membershipError.message);
        
        // Check remaining memberships
        const { data: remainingMemberships } = await supabaseAdmin
          .from("tenant_memberships")
          .select("id")
          .eq("admin_user_id", adminUserId);
        
        // Remove tenant_admin role if no more memberships and not a superadmin
        if (!remainingMemberships || remainingMemberships.length === 0) {
          const { data: hasSuper } = await supabaseAdmin
            .from("user_roles")
            .select("id")
            .eq("user_id", adminUserId)
            .eq("role", "platform_superadmin")
            .maybeSingle();
          
          if (!hasSuper) {
            await supabaseAdmin
              .from("user_roles")
              .delete()
              .eq("user_id", adminUserId)
              .eq("role", "tenant_admin");
          }
        }

        await logAudit({
          action: "remove_from_tenant",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: { 
            targetEmail: targetUser?.email,
            tenantName: tenant?.name,
            tenantSlug: tenant?.slug
          },
          tenant_id: tenantId,
        });
        break;
      }

      case "update_password": {
        if (!newPassword || newPassword.length < 8) {
          throw new Error("Password must be at least 8 characters");
        }

        if (!targetUser) {
          throw new Error("Admin user not found");
        }

        const { error: passwordError } = await supabaseAdmin.auth.admin.updateUserById(
          targetUser.auth_user_id,
          { password: newPassword }
        );
        if (passwordError) throw new Error("Failed to update password: " + passwordError.message);

        await logAudit({
          action: "reset_password",
          entity_type: "admin_user",
          entity_id: adminUserId,
          after_data: { 
            targetEmail: targetUser.email,
            targetName: targetUser.full_name,
            passwordResetBy: callerAdmin?.email
          },
        });
        break;
      }

      case "update_profile": {
        const updates: { full_name?: string; avatar_url?: string } = {};
        if (fullName !== undefined) updates.full_name = fullName;
        if (avatarUrl !== undefined) updates.avatar_url = avatarUrl;

        if (!hasProfileUpdates(fullName, avatarUrl)) {
          throw new Error("No profile fields to update");
        }

        const { error: profileError } = await supabaseAdmin
          .from("admin_users")
          .update(updates)
          .eq("id", adminUserId);
        if (profileError) throw new Error("Failed to update profile: " + profileError.message);

        await logAudit({
          action: "update_profile",
          entity_type: "admin_user",
          entity_id: adminUserId,
          before_data: { 
            targetEmail: targetUser?.email,
            full_name: targetUser?.full_name
          },
          after_data: { 
            targetEmail: targetUser?.email,
            ...updates
          },
        });
        break;
      }

      default:
        throw new Error("Invalid action");
    }

    return new Response(
      JSON.stringify({ success: true }),
      { status: 200, headers: { "Content-Type": "application/json", ...corsHeaders } }
    );
  } catch (error: unknown) {
    console.error("Error managing roles:", error);
    const message = error instanceof Error ? error.message : "An error occurred";
    return new Response(
      JSON.stringify({ error: message }),
      { status: 400, headers: { "Content-Type": "application/json", ...corsHeaders } }
    );
  }
});
